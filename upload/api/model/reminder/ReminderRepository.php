<?php
declare(strict_types=1);

namespace Api\Model\Reminder;

use Api\System\Library\Database\Builder\QueryBuilder;
use PDO;
use Api\System\Library\Support\LikeEscaper;

final class ReminderRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function listByUser(int $userId, array $filters, ?int $organizationId = null): array
    {
        $page = max(1, (int)($filters['page'] ?? 1));
        $limit = min(100, max(1, (int)($filters['limit'] ?? 20)));
        $offset = ($page - 1) * $limit;

        $total = $this->buildListQuery($userId, $filters, $organizationId)->count();
        $items = $this->buildListQuery($userId, $filters, $organizationId)
            ->select([
                'r.public_id',
                'r.remind_at',
                'r.status',
                'r.created_at',
                't.public_id AS task_public_id',
                't.title AS task_title',
            ])
            ->orderBy('r.remind_at', 'ASC')
            ->limit($limit)
            ->offset($offset)
            ->get();

        return [$items, $total, $page, $limit];
    }

    private function buildListQuery(int $userId, array $filters, ?int $organizationId = null): QueryBuilder
    {
        $query = (new QueryBuilder($this->pdo))
            ->from('reminders r')
            ->leftJoin('tasks t', 't.id', '=', 'r.task_id')
            ->where('r.user_id', '=', $userId);
        if ($organizationId !== null && $organizationId > 0) $query->where('r.organization_id', '=', $organizationId);

        if (!empty($filters['status'])) {
            $query->where('r.status', '=', trim((string)$filters['status']));
        }

        if (!empty($filters['task_public_id'])) {
            $query->where('t.public_id', '=', trim((string)$filters['task_public_id']));
        }

        if (!empty($filters['from'])) {
            $query->where('r.remind_at', '>=', (string)$filters['from']);
        }

        if (!empty($filters['to'])) {
            $query->where('r.remind_at', '<=', (string)$filters['to']);
        }

        if (!empty($filters['search'])) {
            $search = '%' . LikeEscaper::escape(trim((string)$filters['search'])) . '%';
            $query->whereRaw(
                '(r.public_id LIKE ? OR t.public_id LIKE ? OR t.title LIKE ? OR r.remind_at LIKE ?)',
                [$search, $search, $search, $search]
            );
        }

        return $query;
    }

    public function create(array $payload): void
    {
        if (array_key_exists('organization_id', $payload) && !$this->organizationColumnExists()) {
            unset($payload['organization_id']);
        }
        (new QueryBuilder($this->pdo))
            ->from('reminders')
            ->insert($payload);
    }

    private function organizationColumnExists(): bool
    {
        try {
            $driver = (string)$this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
            return match ($driver) {
                'sqlite' => (bool)$this->pdo->query("SELECT 1 FROM pragma_table_info('reminders') WHERE name = 'organization_id'")->fetchColumn(),
                'pgsql' => (bool)$this->pdo->query("SELECT 1 FROM information_schema.columns WHERE table_schema = current_schema() AND table_name = 'reminders' AND column_name = 'organization_id'")->fetchColumn(),
                default => (bool)$this->pdo->query("SELECT 1 FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'reminders' AND column_name = 'organization_id'")->fetchColumn(),
            };
        } catch (\Throwable) {
            return false;
        }
    }

    public function findByPublicId(string $publicId): ?array
    {
        return (new QueryBuilder($this->pdo))
            ->from('reminders')
            ->select(['public_id', 'user_id', 'task_id', 'remind_at', 'status', 'created_at'])
            ->where('public_id', '=', $publicId)
            ->first();
    }

    public function findByPublicIdForUser(string $publicId, int $userId, ?int $organizationId = null): ?array
    {
        $query = (new QueryBuilder($this->pdo))
            ->from('reminders r')
            ->leftJoin('tasks t', 't.id', '=', 'r.task_id')
            ->select([
                'r.public_id',
                'r.remind_at',
                'r.status',
                'r.created_at',
                't.public_id AS task_public_id',
                't.title AS task_title',
            ])
            ->where('r.public_id', '=', $publicId)
            ->where('r.user_id', '=', $userId);
        if ($organizationId !== null && $organizationId > 0) $query->where('r.organization_id', '=', $organizationId);
        return $query->first();
    }

    public function updateByPublicIdForUser(string $publicId, int $userId, array $set, ?int $organizationId = null): bool
    {
        if ($set === []) {
            return false;
        }

        $query = (new QueryBuilder($this->pdo))
            ->from('reminders')
            ->where('public_id', '=', $publicId)
            ->where('user_id', '=', $userId);
        if ($organizationId !== null && $organizationId > 0) $query->where('organization_id', '=', $organizationId);
        return $query->update($set) > 0;
    }

    public function deleteByPublicIdForUser(string $publicId, int $userId, ?int $organizationId = null): bool
    {
        $query = (new QueryBuilder($this->pdo))
            ->from('reminders')
            ->where('public_id', '=', $publicId)
            ->where('user_id', '=', $userId);
        if ($organizationId !== null && $organizationId > 0) $query->where('organization_id', '=', $organizationId);
        return $query->delete() > 0;
    }

    public function countPendingDueUntil(int $userId, string $until, ?int $organizationId = null): int
    {
        $query = (new QueryBuilder($this->pdo))
            ->from('reminders')
            ->where('user_id', '=', $userId)
            ->whereRaw('status IN (?, ?)', ['new', 'pending'])
            ->where('remind_at', '<=', $until);
        if ($organizationId !== null && $organizationId > 0) $query->where('organization_id', '=', $organizationId);
        return $query->count();
    }

    public function listInRange(int $userId, string $startAt, string $endAt, ?int $organizationId = null): array
    {
        $query = (new QueryBuilder($this->pdo))
            ->from('reminders r')
            ->leftJoin('tasks t', 't.id', '=', 'r.task_id')
            ->select([
                'r.public_id',
                'r.remind_at',
                'r.status',
                'r.created_at',
                't.public_id AS task_public_id',
                't.title AS task_title',
            ])
            ->where('r.user_id', '=', $userId)
            ->where('r.remind_at', '>=', $startAt)
            ->where('r.remind_at', '<=', $endAt);
        if ($organizationId !== null && $organizationId > 0) $query->where('r.organization_id', '=', $organizationId);
        return $query->orderBy('r.remind_at', 'ASC')->get();
    }

    /** @return array<int,array<string,mixed>> */
    public function listDueActiveByUser(int $userId, string $until, int $limit = 100, ?int $organizationId = null): array
    {
        if ($userId <= 0) {
            return [];
        }

        $safeLimit = min(500, max(1, $limit));

        $query = (new QueryBuilder($this->pdo))
            ->from('reminders r')
            ->leftJoin('tasks t', 't.id', '=', 'r.task_id')
            ->select([
                'r.public_id',
                'r.remind_at',
                'r.status',
                'r.created_at',
                't.public_id AS task_public_id',
                't.title AS task_title',
            ])
            ->where('r.user_id', '=', $userId)
            ->whereRaw('r.status IN (?, ?)', ['new', 'pending'])
            ->where('r.remind_at', '<=', $until);
        if ($organizationId !== null && $organizationId > 0) $query->where('r.organization_id', '=', $organizationId);
        return $query->orderBy('r.remind_at', 'ASC')
            ->limit($safeLimit)
            ->get();
    }
}
