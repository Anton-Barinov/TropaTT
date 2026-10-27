<?php
declare(strict_types=1);

namespace Api\Model\Calendar;

use Api\System\Library\Database\Builder\QueryBuilder;
use PDO;

final class CalendarEventRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function listByUser(int $userId, bool $isRoot, array $filters, ?int $organizationId = null): array
    {
        $page = max(1, (int)($filters['page'] ?? 1));
        $limit = min(100, max(1, (int)($filters['limit'] ?? 20)));
        $offset = ($page - 1) * $limit;

        $total = $this->buildListByUserQuery($userId, $isRoot, $filters, $organizationId)->count();
        $items = $this->buildListByUserQuery($userId, $isRoot, $filters, $organizationId)
            ->select([
                'e.public_id',
                'e.owner_user_id',
                'e.title',
                'e.description',
                'e.starts_at',
                'e.ends_at',
                'e.created_at',
                'e.updated_at',
                'p.public_id AS project_public_id',
                'p.title AS project_title',
                't.public_id AS task_public_id',
                't.title AS task_title',
            ])
            ->orderBy('e.starts_at', 'ASC')
            ->limit($limit)
            ->offset($offset)
            ->get();

        return [$items, $total, $page, $limit];
    }

    private function buildListByUserQuery(int $userId, bool $isRoot, array $filters, ?int $organizationId = null): QueryBuilder
    {
        $query = (new QueryBuilder($this->pdo))
            ->from('calendar_events e')
            ->leftJoin('projects p', 'p.id', '=', 'e.project_id')
            ->leftJoin('tasks t', 't.id', '=', 'e.task_id');

        if (!$isRoot) {
            $query->whereRaw('(e.owner_user_id = ? OR (t.assignee_user_id IS NOT NULL AND t.assignee_user_id = ?) OR EXISTS (SELECT 1 FROM calendar_event_attendees cea WHERE cea.event_id = e.id AND cea.user_id = ?))', [$userId, $userId, $userId]);
        } else {
            // Root users may see normal shared CRM events, but never another
            // user's private external-calendar event.
            $query->whereRaw("(e.source_type IS NULL OR e.source_type NOT IN ('google_calendar', 'yandex_calendar', 'outlook_calendar') OR e.source_owner_user_id = ?)", [$userId]);
        }
        if ($organizationId !== null && $organizationId > 0) {
            $query->where('e.organization_id', '=', $organizationId);
        }

        if (!empty($filters['from'])) {
            $query->where('e.starts_at', '>=', (string)$filters['from']);
        }

        if (!empty($filters['to'])) {
            $query->where('e.ends_at', '<=', (string)$filters['to']);
        }

        return $query;
    }

    /**
     * Persist a calendar event while serializing against public appointment bookings.
     * Returns 0 when the event would overlap a confirmed public booking. When called
     * inside a transaction, locking and insertion join that transaction (for example
     * a module outbox transaction); otherwise the repository owns a short transaction.
     *
     * @param array<string,mixed> $payload
     * @param list<int> $conflictUserIds Additional attendee calendars to protect
     */
    public function create(array $payload, array $conflictUserIds = []): int
    {
        $ownerId = (int)($payload['owner_user_id'] ?? 0);
        $organizationId = (int)($payload['organization_id'] ?? 0);
        $startsAt = (string)($payload['starts_at'] ?? '');
        $endsAt = (string)($payload['ends_at'] ?? '');
        $protectedUsers = array_values(array_unique(array_filter(array_map('intval', array_merge([$ownerId], $conflictUserIds)), static fn(int $id): bool => $id > 0)));
        sort($protectedUsers, SORT_NUMERIC);
        $ownsTransaction = !$this->pdo->inTransaction();
        $driver = (string)$this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        try {
            if ($ownsTransaction) {
                if ($driver === 'sqlite') $this->pdo->exec('BEGIN IMMEDIATE');
                else $this->pdo->beginTransaction();
            }
            if ($organizationId > 0 && $protectedUsers !== [] && $startsAt !== '' && $endsAt !== '') {
                $this->lockBookingUsers($protectedUsers, $driver);
                if ($this->hasPublicBookingOverlap($organizationId, $protectedUsers, $startsAt, $endsAt)) {
                    if ($ownsTransaction && $this->pdo->inTransaction()) $this->pdo->rollBack();
                    return 0;
                }
            }
            (new QueryBuilder($this->pdo))->from('calendar_events')->insert($payload);
            $id = (int)$this->pdo->lastInsertId();
            if ($ownsTransaction) $this->pdo->commit();
            return $id;
        } catch (\Throwable $e) {
            if ($ownsTransaction && $this->pdo->inTransaction()) $this->pdo->rollBack();
            throw $e;
        }
    }

    /** @param list<int> $userIds */
    private function lockBookingUsers(array $userIds, string $driver): void
    {
        $placeholders = [];
        $params = [];
        foreach ($userIds as $index => $userId) {
            $key = ':user_' . $index;
            $placeholders[] = $key;
            $params[$key] = $userId;
        }
        $sql = 'SELECT id FROM users WHERE id IN (' . implode(',', $placeholders) . ') ORDER BY id';
        if ($driver === 'mysql') $sql .= ' FOR UPDATE';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
    }

    /** Acquire the same per-user calendar serialization lock used by every writer. */
    public function lockConflictUsers(array $userIds): void
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $userIds), static fn(int $id): bool => $id > 0)));
        sort($ids, SORT_NUMERIC);
        if ($ids !== []) $this->lockBookingUsers($ids, (string)$this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME));
    }

    /** Caller must hold lockConflictUsers() for the same IDs until its transaction commits. */
    public function hasPublicBookingConflict(int $organizationId, array $userIds, string $startsAt, string $endsAt): bool
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $userIds), static fn(int $id): bool => $id > 0)));
        if ($organizationId < 1 || $ids === [] || $startsAt >= $endsAt) return true;
        return $this->hasPublicBookingOverlap($organizationId, $ids, $startsAt, $endsAt);
    }

    /** @param list<int> $userIds */
    private function hasPublicBookingOverlap(int $organizationId, array $userIds, string $startsAt, string $endsAt, ?int $excludeEventId = null): bool
    {
        $placeholders = [];
        $params = [':org_id' => $organizationId, ':end_at' => $endsAt];
        foreach ($userIds as $index => $userId) {
            $key = ':protected_user_' . $index;
            $placeholders[] = $key;
            $params[$key] = $userId;
        }
        $users = implode(',', $placeholders);
        // Booking rows snapshot their buffer at booking time. Query a 4-hour
        // lookback (the configuration cap), then apply the padded interval in
        // PHP so SQLite and MySQL share identical date semantics.
        $lookback = (new \DateTimeImmutable($startsAt, new \DateTimeZone('UTC')))->modify('-240 minutes')->format('Y-m-d H:i:s');
        $params[':lookback'] = $lookback;
        $snapshotState = $this->bookingBufferSnapshotState();
        // If schema introspection is unavailable, never silently drop the booking
        // buffer: reserve the maximum supported window until the schema is readable.
        // A missing column can mean a pre-migration deployment with existing bookings.
        // Reserve the maximum supported padding until snapshots can be read.
        $snapshotSelect = $snapshotState === true ? 'COALESCE(s.booking_buffer_minutes, 0)' : '240';
        $snapshotJoin = $snapshotState === true ? ' LEFT JOIN crm_public_form_submissions s ON s.event_public_id = e.public_id AND s.organization_id = e.organization_id' : '';
        $sql = "SELECT e.id, e.starts_at, e.ends_at, $snapshotSelect AS buffer_minutes FROM calendar_events e$snapshotJoin WHERE e.organization_id = :org_id AND e.source_type = 'public_form_booking' AND e.starts_at < :end_at AND e.ends_at > :lookback AND (e.owner_user_id IN ($users) OR EXISTS (SELECT 1 FROM calendar_event_attendees a WHERE a.event_id = e.id AND a.user_id IN ($users)))";
        if ($excludeEventId !== null) { $sql .= ' AND e.id <> :exclude_id'; $params[':exclude_id'] = $excludeEventId; }
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $candidateStart = (new \DateTimeImmutable($startsAt, new \DateTimeZone('UTC')))->getTimestamp();
        $candidateEnd = (new \DateTimeImmutable($endsAt, new \DateTimeZone('UTC')))->getTimestamp();
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $booking) {
            $buffer = max(0, min(240, (int)($booking['buffer_minutes'] ?? 0)));
            $bookingStart = (new \DateTimeImmutable((string)$booking['starts_at'], new \DateTimeZone('UTC')))->getTimestamp() - ($buffer * 60);
            $bookingEnd = (new \DateTimeImmutable((string)$booking['ends_at'], new \DateTimeZone('UTC')))->getTimestamp() + ($buffer * 60);
            if ($bookingStart < $candidateEnd && $bookingEnd > $candidateStart) return true;
        }
        return false;
    }

    /** true = column exists; false = confirmed absent; null = metadata cannot be read. */
    private function bookingBufferSnapshotState(): ?bool
    {
        try {
            if ($this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite') {
                $stmt = $this->pdo->prepare("SELECT 1 FROM sqlite_master WHERE type = 'table' AND name = 'crm_public_form_submissions' LIMIT 1");
                $stmt->execute();
                if ($stmt->fetchColumn() === false) return false;
                foreach ($this->pdo->query('PRAGMA table_info(crm_public_form_submissions)')->fetchAll(PDO::FETCH_ASSOC) as $column) {
                    if (($column['name'] ?? '') === 'booking_buffer_minutes') return true;
                }
                return false;
            }
            $stmt = $this->pdo->prepare("SELECT 1 FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'crm_public_form_submissions' AND column_name = 'booking_buffer_minutes' LIMIT 1");
            $stmt->execute();
            return $stmt->fetchColumn() !== false;
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Update a normal event under the same booking lock used by create().
     * Returns updated, unchanged, or booking_conflict.
     * @param array<string,mixed> $set
     */
    public function updateWithBookingGuard(string $publicId, int $userId, bool $isRoot, array $set, ?int $organizationId = null, ?array $newAttendeeUserIds = null): string
    {
        if ($set === [] && $newAttendeeUserIds === null) return 'unchanged';
        $find = $this->pdo->prepare('SELECT id, owner_user_id, starts_at, ends_at, organization_id FROM calendar_events WHERE public_id = :public_id LIMIT 1');
        $find->execute([':public_id' => $publicId]);
        $event = $find->fetch(PDO::FETCH_ASSOC);
        if (!is_array($event)) return 'unchanged';
        $eventId = (int)$event['id'];
        $orgId = (int)($organizationId ?? $event['organization_id'] ?? 0);
        $ownerId = (int)($event['owner_user_id'] ?? 0);
        $ownsTransaction = !$this->pdo->inTransaction();
        $driver = (string)$this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        try {
            if ($ownsTransaction) {
                if ($driver === 'sqlite') $this->pdo->exec('BEGIN IMMEDIATE');
                else $this->pdo->beginTransaction();
            }
            $initialAttendees = $this->getAttendees($eventId);
            $protected = [$ownerId];
            foreach ($initialAttendees as $attendee) if ((int)($attendee['user_id'] ?? 0) > 0) $protected[] = (int)$attendee['user_id'];
            if ($newAttendeeUserIds !== null) $protected = array_merge($protected, $newAttendeeUserIds);
            $protected = array_values(array_unique(array_filter($protected, static fn(int $id): bool => $id > 0)));
            sort($protected, SORT_NUMERIC);
            if ($orgId > 0 && $protected !== []) $this->lockBookingUsers($protected, $driver);

            // A competing update may have changed the event between the first
            // read and acquisition of user locks. Re-read under the serialization
            // locks and, on MySQL, lock the authoritative event row as well.
            $refreshSql = 'SELECT id, owner_user_id, starts_at, ends_at, organization_id FROM calendar_events WHERE public_id = :public_id';
            $params = [':public_id' => $publicId];
            if ($organizationId !== null && $organizationId > 0) {
                $refreshSql .= ' AND organization_id = :organization_id';
                $params[':organization_id'] = $organizationId;
            }
            // MySQL/MariaDB require LIMIT before the locking clause.
            $refreshSql .= ' LIMIT 1';
            if ($driver === 'mysql') $refreshSql .= ' FOR UPDATE';
            $refresh = $this->pdo->prepare($refreshSql);
            $refresh->execute($params);
            $freshEvent = $refresh->fetch(PDO::FETCH_ASSOC);
            if (!is_array($freshEvent) || (!$isRoot && (int)$freshEvent['owner_user_id'] !== $userId)) {
                if ($ownsTransaction && $this->pdo->inTransaction()) $this->pdo->rollBack();
                return 'unchanged';
            }
            if ((int)$freshEvent['id'] !== $eventId
                || (int)$freshEvent['owner_user_id'] !== $ownerId
                || (int)$freshEvent['organization_id'] !== $orgId) {
                if ($ownsTransaction && $this->pdo->inTransaction()) $this->pdo->rollBack();
                return 'booking_conflict';
            }
            // This must be a MySQL locking/current read, not a consistent
            // snapshot read: REPEATABLE READ may otherwise hide attendee rows
            // committed while the owner lock was being awaited.
            $freshAttendeeUserIds = $this->attendeeUserIdsForUpdate($eventId, $driver);
            $freshProtected = [$ownerId];
            foreach ($freshAttendeeUserIds as $attendeeUserId) if ($attendeeUserId > 0) $freshProtected[] = $attendeeUserId;
            if ($newAttendeeUserIds !== null) $freshProtected = array_merge($freshProtected, $newAttendeeUserIds);
            $freshProtected = array_values(array_unique(array_filter($freshProtected, static fn(int $id): bool => $id > 0)));
            if (array_diff($freshProtected, $protected) !== []) {
                // A new attendee was added by a concurrent writer after our first
                // read. Do not acquire out-of-order locks mid-transaction; fail
                // closed so the caller can retry with a fresh attendee snapshot.
                if ($ownsTransaction && $this->pdo->inTransaction()) $this->pdo->rollBack();
                return 'booking_conflict';
            }
            $startsAt = (string)($set['starts_at'] ?? $freshEvent['starts_at']);
            $endsAt = (string)($set['ends_at'] ?? $freshEvent['ends_at']);
            if ($orgId > 0 && $this->hasPublicBookingOverlap($orgId, $freshProtected, $startsAt, $endsAt, $eventId)) {
                if ($ownsTransaction && $this->pdo->inTransaction()) $this->pdo->rollBack();
                return 'booking_conflict';
            }
            $ok = $this->updateByPublicId($publicId, $userId, $isRoot, $set, $organizationId);
            if ($newAttendeeUserIds !== null) $this->syncAttendees($eventId, $newAttendeeUserIds);
            if ($ownsTransaction) $this->pdo->commit();
            return $ok ? 'updated' : 'unchanged';
        } catch (\Throwable $e) {
            if ($ownsTransaction && $this->pdo->inTransaction()) $this->pdo->rollBack();
            throw $e;
        }
    }

    public function findByPublicId(string $publicId, int $userId, bool $isRoot, ?int $organizationId = null): ?array
    {
        $query = (new QueryBuilder($this->pdo))
            ->from('calendar_events e')
            ->leftJoin('projects p', 'p.id', '=', 'e.project_id')
            ->leftJoin('tasks t', 't.id', '=', 'e.task_id')
            ->select([
                'e.id',
                'e.public_id',
                'e.owner_user_id',
                'e.title',
                'e.description',
                'e.starts_at',
                'e.ends_at',
                'e.project_id',
                'e.task_id',
                'e.created_at',
                'e.updated_at',
                'p.public_id AS project_public_id',
                'p.title AS project_title',
                't.public_id AS task_public_id',
                't.title AS task_title',
            ])
            ->where('e.public_id', '=', $publicId);

        if (!$isRoot) {
            $query->whereRaw('(e.owner_user_id = ? OR (t.assignee_user_id IS NOT NULL AND t.assignee_user_id = ?) OR EXISTS (SELECT 1 FROM calendar_event_attendees cea WHERE cea.event_id = e.id AND cea.user_id = ?))', [$userId, $userId, $userId]);
        } else {
            $query->whereRaw("(e.source_type IS NULL OR e.source_type NOT IN ('google_calendar', 'yandex_calendar', 'outlook_calendar') OR e.source_owner_user_id = ?)", [$userId]);
        }
        if ($organizationId !== null && $organizationId > 0) $query->where('e.organization_id', '=', $organizationId);

        $row = $query->first();
        if ($row) {
            $row['attendees'] = $this->getAttendees((int)$row['id']);
        }
        return $row;
    }

    public function updateByPublicId(string $publicId, int $userId, bool $isRoot, array $set, ?int $organizationId = null): bool
    {
        if ($set === []) {
            return false;
        }

        $query = (new QueryBuilder($this->pdo))
            ->from('calendar_events')
            ->where('public_id', '=', $publicId);

        if (!$isRoot) {
            $query->where('owner_user_id', '=', $userId);
        } else {
            $query->whereRaw("(source_type IS NULL OR source_type NOT IN ('google_calendar', 'yandex_calendar', 'outlook_calendar') OR source_owner_user_id = ?)", [$userId]);
        }
        if ($organizationId !== null && $organizationId > 0) $query->where('organization_id', '=', $organizationId);

        return $query->update($set) > 0;
    }

    public function deleteByPublicId(string $publicId, int $userId, bool $isRoot, ?int $organizationId = null): bool
    {
        $query = (new QueryBuilder($this->pdo))
            ->from('calendar_events')
            ->where('public_id', '=', $publicId);

        if (!$isRoot) {
            $query->where('owner_user_id', '=', $userId);
        } else {
            $query->whereRaw("(source_type IS NULL OR source_type NOT IN ('google_calendar', 'yandex_calendar', 'outlook_calendar') OR source_owner_user_id = ?)", [$userId]);
        }
        if ($organizationId !== null && $organizationId > 0) $query->where('organization_id', '=', $organizationId);

        return $query->delete() > 0;
    }

    public function listInRange(int $userId, bool $isRoot, string $startAt, string $endAt, ?int $organizationId = null): array
    {
        $query = (new QueryBuilder($this->pdo))
            ->from('calendar_events e')
            ->leftJoin('projects p', 'p.id', '=', 'e.project_id')
            ->leftJoin('tasks t', 't.id', '=', 'e.task_id')
            ->select([
                'e.public_id',
                'e.owner_user_id',
                'e.title',
                'e.description',
                'e.starts_at',
                'e.ends_at',
                'p.public_id AS project_public_id',
                'p.title AS project_title',
                't.public_id AS task_public_id',
                't.title AS task_title',
            ])
            ->where('e.starts_at', '<=', $endAt)
            ->where('e.ends_at', '>=', $startAt);

        if (!$isRoot) {
            $query->whereRaw('(e.owner_user_id = ? OR (t.assignee_user_id IS NOT NULL AND t.assignee_user_id = ?) OR EXISTS (SELECT 1 FROM calendar_event_attendees cea WHERE cea.event_id = e.id AND cea.user_id = ?))', [$userId, $userId, $userId]);
        } else {
            $query->whereRaw("(e.source_type IS NULL OR e.source_type NOT IN ('google_calendar', 'yandex_calendar', 'outlook_calendar') OR e.source_owner_user_id = ?)", [$userId]);
        }
        if ($organizationId !== null && $organizationId > 0) $query->where('e.organization_id', '=', $organizationId);

        return $query
            ->orderBy('e.starts_at', 'ASC')
            ->get();
    }

    public function findUpcomingForUser(int $userId, string $from, string $to): array
    {
        return (new QueryBuilder($this->pdo))
            ->from('calendar_events e')
            ->leftJoin('projects p', 'p.id', '=', 'e.project_id')
            ->leftJoin('tasks t', 't.id', '=', 'e.task_id')
            ->select([
                'e.id',
                'e.public_id',
                'e.owner_user_id',
                'e.title',
                'e.description',
                'e.starts_at',
                'e.ends_at',
                'e.created_at',
                'p.public_id AS project_public_id',
                'p.title AS project_title',
                't.public_id AS task_public_id',
                't.title AS task_title',
            ])
            ->whereRaw('(e.owner_user_id = ? OR (t.assignee_user_id IS NOT NULL AND t.assignee_user_id = ?) OR EXISTS (SELECT 1 FROM calendar_event_attendees cea WHERE cea.event_id = e.id AND cea.user_id = ?))', [$userId, $userId, $userId])
            ->where('e.starts_at', '>=', $from)
            ->where('e.starts_at', '<=', $to)
            ->orderBy('e.starts_at', 'ASC')
            ->get();
    }

    public function syncAttendees(int $eventId, array $userIds): void
    {
        if ($eventId <= 0) {
            return;
        }
        $this->pdo->prepare('DELETE FROM calendar_event_attendees WHERE event_id = ?')->execute([$eventId]);
        if ($userIds === []) {
            return;
        }
        $stmt = $this->pdo->prepare('INSERT INTO calendar_event_attendees (event_id, user_id, status, created_at) VALUES (?, ?, ?, ?)');
        $now = gmdate('Y-m-d H:i:s');
        foreach (array_unique(array_filter(array_map('intval', $userIds), static fn(int $id): bool => $id > 0)) as $userId) {
            $stmt->execute([$eventId, $userId, 'accepted', $now]);
        }
    }

    /**
     * Read attendee IDs with a current locking read after the owner/event locks.
     * InnoDB REPEATABLE READ locking reads see the latest committed version and
     * lock the indexed event_id range against a concurrent attendee insertion.
     * @return list<int>
     */
    private function attendeeUserIdsForUpdate(int $eventId, string $driver): array
    {
        if ($eventId <= 0) return [];
        $sql = 'SELECT user_id FROM calendar_event_attendees WHERE event_id = :event_id ORDER BY user_id';
        if ($driver === 'mysql') $sql .= ' FOR UPDATE';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':event_id' => $eventId]);
        return array_values(array_unique(array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [])));
    }

    public function getAttendees(int $eventId): array
    {
        if ($eventId <= 0) {
            return [];
        }
        try {
            $stmt = $this->pdo->prepare('
                SELECT u.id, u.public_id, u.name, u.email, cea.status, cea.created_at
                FROM calendar_event_attendees cea
                JOIN users u ON u.id = cea.user_id
                WHERE cea.event_id = ?
                ORDER BY cea.created_at ASC
            ');
            $stmt->execute([$eventId]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (\Throwable) {
            return [];
        }
    }

    public function listTasksDueInRange(int $userId, bool $isRoot, string $startAt, string $endAt, ?int $organizationId = null): array
    {
        $query = (new QueryBuilder($this->pdo))
            ->from('tasks t')
            ->leftJoin('projects p', 'p.id', '=', 't.project_id')
            ->select([
                't.public_id',
                't.title',
                't.status_code',
                't.priority_code',
                't.due_at',
                'p.public_id AS project_public_id',
                'p.title AS project_title',
            ])
            ->whereNull('t.deleted_at')
            ->whereNull('t.archived_at')
            ->whereNotNull('t.due_at')
            ->where('t.due_at', '>=', $startAt)
            ->where('t.due_at', '<=', $endAt);

        if (!$isRoot) {
            $query->whereRaw(
                '(t.creator_user_id = ? OR t.assignee_user_id = ? OR p.created_by_user_id = ? OR p.manager_user_id = ?)',
                [$userId, $userId, $userId, $userId]
            );
        }
        if ($organizationId !== null && $organizationId > 0) $query->where('t.organization_id', '=', $organizationId);

        return $query
            ->orderBy('t.due_at', 'ASC')
            ->get();
    }
}
