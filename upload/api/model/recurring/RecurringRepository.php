<?php
declare(strict_types=1);

namespace Api\Model\Recurring;

use Api\System\Library\Database\Builder\QueryBuilder;
use Api\System\Library\Language\LanguageManager;
use PDO;
use Api\System\Library\Support\LikeEscaper;

final class RecurringRepository
{
    private LanguageManager $lang;

    public function __construct(private readonly PDO $pdo, ?LanguageManager $lang = null)
    {
        $this->lang = $lang ?? new LanguageManager(__DIR__ . '/../../language');
    }

    private function t(string $key, string $default = ''): string
    {
        return $this->lang->get($key, $default !== '' ? $default : $key);
    }

    public function list(array $filters, int $actorId = 0): array
    {
        $page = max(1, (int)($filters['page'] ?? 1));
        $limit = min(100, max(1, (int)($filters['limit'] ?? 20)));
        $offset = ($page - 1) * $limit;

        $total = $this->buildListQuery($filters, $actorId)->count();
        $items = $this->buildListQuery($filters, $actorId)
            ->select(['public_id', 'title', 'entity_type', 'entity_public_id', 'rrule', 'is_active', 'last_processed_at', 'created_at', 'updated_at'])
            ->orderBy('updated_at', 'DESC')
            ->orderBy('public_id', 'DESC')
            ->limit($limit)
            ->offset($offset)
            ->get();

        return [$items, $total, $page, $limit];
    }

    private function buildListQuery(array $filters, int $actorId = 0): QueryBuilder
    {
        $query = (new QueryBuilder($this->pdo))
            ->from('recurring_rules');

        // A recurring rule must not become a side-channel for another user's
        // private Google event. The owner can see their own rule; root is not a
        // bypass for this deliberately private data.
        $query->whereRaw(
            "(entity_type <> 'calendar_event' OR NOT EXISTS (SELECT 1 FROM calendar_events ce WHERE ce.public_id = recurring_rules.entity_public_id AND ce.source_type IN ('google_calendar', 'yandex_calendar') AND ce.source_owner_user_id <> ?))",
            [$actorId]
        );

        if (!empty($filters['entity_type'])) {
            $query->where('entity_type', '=', (string)$filters['entity_type']);
        }

        if (!empty($filters['entity_public_id'])) {
            $query->where('entity_public_id', '=', (string)$filters['entity_public_id']);
        }

        if (isset($filters['is_active']) && $filters['is_active'] !== '') {
            $query->where('is_active', '=', ((int)$filters['is_active'] === 1) ? 1 : 0);
        }

        if (!empty($filters['search'])) {
            $search = '%' . LikeEscaper::escape((string)$filters['search']) . '%';
            $query->whereRaw('(entity_public_id LIKE ? OR rrule LIKE ?)', [$search, $search]);
        }

        return $query;
    }

    public function findByPublicId(string $publicId, int $actorId = 0): ?array
    {
        $row = (new QueryBuilder($this->pdo))
            ->from('recurring_rules')
            ->select(['public_id', 'title', 'entity_type', 'entity_public_id', 'rrule', 'is_active', 'last_processed_at', 'created_at', 'updated_at'])
            ->where('public_id', '=', $publicId)
            ->whereRaw(
                "(entity_type <> 'calendar_event' OR NOT EXISTS (SELECT 1 FROM calendar_events ce WHERE ce.public_id = recurring_rules.entity_public_id AND ce.source_type IN ('google_calendar', 'yandex_calendar') AND ce.source_owner_user_id <> ?))",
                [$actorId]
            )
            ->first();

        return $row ?: null;
    }

    public function create(array $payload): void
    {
        (new QueryBuilder($this->pdo))
            ->from('recurring_rules')
            ->insert($payload);
    }

    public function updateByPublicId(string $publicId, array $set, int $actorId = 0): bool
    {
        if ($set === []) {
            return false;
        }

        return (new QueryBuilder($this->pdo))
            ->from('recurring_rules')
            ->where('public_id', '=', $publicId)
            ->whereRaw(
                "(entity_type <> 'calendar_event' OR NOT EXISTS (SELECT 1 FROM calendar_events ce WHERE ce.public_id = recurring_rules.entity_public_id AND ce.source_type IN ('google_calendar', 'yandex_calendar') AND ce.source_owner_user_id <> ?))",
                [$actorId]
            )
            ->update($set) > 0;
    }

    public function deleteByPublicId(string $publicId, int $actorId = 0): bool
    {
        return (new QueryBuilder($this->pdo))
            ->from('recurring_rules')
            ->where('public_id', '=', $publicId)
            ->whereRaw(
                "(entity_type <> 'calendar_event' OR NOT EXISTS (SELECT 1 FROM calendar_events ce WHERE ce.public_id = recurring_rules.entity_public_id AND ce.source_type IN ('google_calendar', 'yandex_calendar') AND ce.source_owner_user_id <> ?))",
                [$actorId]
            )
            ->delete() > 0;
    }

    public function canUseEntity(string $entityType, string $entityPublicId, int $actorId = 0): bool
    {
        if (trim($entityType) !== 'calendar_event') return true;
        $stmt = $this->pdo->prepare("SELECT source_owner_user_id FROM calendar_events WHERE public_id = :public_id AND source_type IN ('google_calendar', 'yandex_calendar') LIMIT 1");
        $stmt->execute(['public_id' => trim($entityPublicId)]);
        $ownerId = $stmt->fetchColumn();
        return $ownerId === false || (int)$ownerId === $actorId;
    }

    public function resolveEntityTitle(string $entityType, string $entityPublicId): ?string
    {
        $entityType = trim($entityType);
        $entityPublicId = trim($entityPublicId);
        if ($entityType === '' || $entityPublicId === '') {
            return null;
        }

        if ($entityType === 'task') {
            return $this->fetchSingleTitle('SELECT title FROM tasks WHERE public_id = ? LIMIT 1', [$entityPublicId]);
        }

        if ($entityType === 'project') {
            return $this->fetchSingleTitle('SELECT title FROM projects WHERE public_id = ? LIMIT 1', [$entityPublicId]);
        }

        if ($entityType === 'calendar_event') {
            // Private external-calendar events are intentionally not resolvable
            // through the generic recurring subsystem. This prevents a title
            // leak through recurring-rule normalization, including for root.
            return $this->fetchSingleTitle("SELECT title FROM calendar_events WHERE public_id = ? AND (source_type IS NULL OR source_type NOT IN ('google_calendar', 'yandex_calendar')) LIMIT 1", [$entityPublicId]);
        }

        if ($entityType === 'reminder') {
            $title = $this->fetchSingleTitle(
                'SELECT CONCAT(?, COALESCE(t.title, r.public_id)) AS title
                 FROM reminders r
                 LEFT JOIN tasks t ON t.id = r.task_id
                 WHERE r.public_id = ?
                 LIMIT 1',
                [$this->t('recurring/messages.entity_reminder') . ': ', $entityPublicId]
            );
            if ($title !== null) {
                return $title;
            }

            $taskTitle = $this->fetchSingleTitle('SELECT title FROM tasks WHERE public_id = ? LIMIT 1', [$entityPublicId]);
            return $taskTitle !== null ? $this->t('recurring/messages.entity_reminder') . ': ' . $taskTitle : null;
        }

        return null;
    }

    private function fetchSingleTitle(string $sql, array $params): ?string
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $value = $stmt->fetchColumn();
        if ($value === false) {
            return null;
        }

        $value = trim((string)$value);
        return $value !== '' ? $value : null;
    }
}
