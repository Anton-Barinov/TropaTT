<?php
declare(strict_types=1);

namespace Api\Model\Recurring;

use Api\System\Library\Database\Builder\QueryBuilder;
use PDO;

final class RecurringRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function list(array $filters): array
    {
        $page = max(1, (int)($filters['page'] ?? 1));
        $limit = min(100, max(1, (int)($filters['limit'] ?? 20)));
        $offset = ($page - 1) * $limit;

        $total = $this->buildListQuery($filters)->count();
        $items = $this->buildListQuery($filters)
            ->select(['public_id', 'title', 'entity_type', 'entity_public_id', 'rrule', 'is_active', 'last_processed_at', 'created_at', 'updated_at'])
            ->orderBy('updated_at', 'DESC')
            ->orderBy('public_id', 'DESC')
            ->limit($limit)
            ->offset($offset)
            ->get();

        return [$items, $total, $page, $limit];
    }

    private function buildListQuery(array $filters): QueryBuilder
    {
        $query = (new QueryBuilder($this->pdo))
            ->from('recurring_rules');

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
            $search = '%' . (string)$filters['search'] . '%';
            $query->whereRaw('(entity_public_id LIKE ? OR rrule LIKE ?)', [$search, $search]);
        }

        return $query;
    }

    public function findByPublicId(string $publicId): ?array
    {
        $row = (new QueryBuilder($this->pdo))
            ->from('recurring_rules')
            ->select(['public_id', 'title', 'entity_type', 'entity_public_id', 'rrule', 'is_active', 'last_processed_at', 'created_at', 'updated_at'])
            ->where('public_id', '=', $publicId)
            ->first();

        return $row ?: null;
    }

    public function create(array $payload): void
    {
        (new QueryBuilder($this->pdo))
            ->from('recurring_rules')
            ->insert($payload);
    }

    public function updateByPublicId(string $publicId, array $set): bool
    {
        if ($set === []) {
            return false;
        }

        return (new QueryBuilder($this->pdo))
            ->from('recurring_rules')
            ->where('public_id', '=', $publicId)
            ->update($set) > 0;
    }

    public function deleteByPublicId(string $publicId): bool
    {
        return (new QueryBuilder($this->pdo))
            ->from('recurring_rules')
            ->where('public_id', '=', $publicId)
            ->delete() > 0;
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
            return $this->fetchSingleTitle('SELECT title FROM calendar_events WHERE public_id = ? LIMIT 1', [$entityPublicId]);
        }

        if ($entityType === 'reminder') {
            $title = $this->fetchSingleTitle(
                'SELECT CONCAT(\'Напоминание: \', COALESCE(t.title, r.public_id)) AS title
                 FROM reminders r
                 LEFT JOIN tasks t ON t.id = r.task_id
                 WHERE r.public_id = ?
                 LIMIT 1',
                [$entityPublicId]
            );
            if ($title !== null) {
                return $title;
            }

            $taskTitle = $this->fetchSingleTitle('SELECT title FROM tasks WHERE public_id = ? LIMIT 1', [$entityPublicId]);
            return $taskTitle !== null ? 'Напоминание по задаче: ' . $taskTitle : null;
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
