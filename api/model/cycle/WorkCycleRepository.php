<?php
declare(strict_types=1);

namespace Api\Model\Cycle;

use Api\System\Library\Database\Builder\QueryBuilder;
use PDO;

final class WorkCycleRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function list(array $filters, int $actorUserId, bool $isRoot): array
    {
        $sortWhitelist = ['start_at', 'end_at', 'created_at', 'updated_at', 'title', 'status', 'sort_order'];
        $sort = in_array(($filters['sort'] ?? ''), $sortWhitelist, true) ? (string)$filters['sort'] : 'start_at';
        $order = strtoupper((string)($filters['order'] ?? 'DESC')) === 'ASC' ? 'ASC' : 'DESC';
        $limit = min(100, max(1, (int)($filters['limit'] ?? 20)));
        $page = max(1, (int)($filters['page'] ?? 1));
        $offset = ($page - 1) * $limit;

        $qb = (new QueryBuilder($this->pdo))
            ->from('work_cycles wc')
            ->leftJoin('users o', 'o.id', '=', 'wc.owner_user_id')
            ->leftJoin('projects p', 'p.id', '=', 'wc.project_id')
            ->select([
                'wc.*',
                'o.public_id AS owner_user_public_id',
                'o.full_name AS owner_name',
                'o.login AS owner_login',
                'p.public_id AS project_public_id',
                'p.title AS project_title',
                "(SELECT COUNT(*) FROM cycle_tasks ct WHERE ct.cycle_id = wc.id AND ct.deleted_at IS NULL) AS tasks_count",
                "(SELECT COUNT(*) FROM cycle_tasks ct INNER JOIN tasks t ON t.id = ct.task_id WHERE ct.cycle_id = wc.id AND ct.deleted_at IS NULL AND t.status_code IN ('done','closed','archived')) AS completed_tasks_count",
                "(SELECT COUNT(*) FROM cycle_tasks ct INNER JOIN tasks t ON t.id = ct.task_id WHERE ct.cycle_id = wc.id AND ct.deleted_at IS NULL AND t.status_code NOT IN ('done','closed','archived')) AS open_tasks_count",
            ]);

        if (!empty($filters['project_public_id'])) {
            $qb->where('p.public_id', '=', (string)$filters['project_public_id']);
        }

        if (!empty($filters['status'])) {
            $qb->where('wc.status', '=', (string)$filters['status']);
        }

        if (!empty($filters['owner_user_public_id'])) {
            $qb->where('o.public_id', '=', (string)$filters['owner_user_public_id']);
        }

        if (!empty($filters['q'])) {
            $term = '%' . (string)$filters['q'] . '%';
            $qb->whereRaw('(wc.title LIKE ? OR wc.description LIKE ?)', [$term, $term]);
        }

        if (!empty($filters['start_from'])) {
            $qb->where('wc.start_at', '>=', (string)$filters['start_from']);
        }

        if (!empty($filters['start_to'])) {
            $qb->where('wc.start_at', '<=', (string)$filters['start_to']);
        }

        if (!empty($filters['end_from'])) {
            $qb->where('wc.end_at', '>=', (string)$filters['end_from']);
        }

        if (!empty($filters['end_to'])) {
            $qb->where('wc.end_at', '<=', (string)$filters['end_to']);
        }

        if (empty($filters['archived']) || $filters['archived'] !== '1') {
            $qb->whereNull('wc.archived_at');
        }

        $qb->whereNull('wc.deleted_at');

        if (!$isRoot && $actorUserId > 0) {
            $qb->whereRaw('(wc.created_by_user_id = ? OR wc.owner_user_id = ? OR wc.project_id IN (SELECT p2.id FROM projects p2 WHERE p2.manager_user_id = ? OR p2.created_by_user_id = ?))', [$actorUserId, $actorUserId, $actorUserId, $actorUserId]);
        }

        $total = $qb->count();

        $items = (clone $qb)
            ->orderBy('wc.' . $sort, $order)
            ->limit($limit)
            ->offset($offset)
            ->get();

        // Compute progress_percent and time_state
        $items = array_map(function (array $item): array {
            $total = max(0, (int)($item['tasks_count'] ?? 0));
            $completed = max(0, (int)($item['completed_tasks_count'] ?? 0));
            $item['progress_percent'] = $total > 0 ? (int)round(($completed / $total) * 100) : 0;
            $item['time_state'] = $this->computeTimeState($item);
            return $item;
        }, $items);

        return [
            'items' => $items,
            'total' => $total,
            'page' => $page,
            'limit' => $limit,
        ];
    }

    public function findByPublicId(string $publicId): ?array
    {
        $row = (new QueryBuilder($this->pdo))
            ->from('work_cycles wc')
            ->leftJoin('users o', 'o.id', '=', 'wc.owner_user_id')
            ->leftJoin('projects p', 'p.id', '=', 'wc.project_id')
            ->select([
                'wc.*',
                'o.public_id AS owner_user_public_id',
                'o.full_name AS owner_name',
                'o.login AS owner_login',
                'p.public_id AS project_public_id',
                'p.title AS project_title',
            ])
            ->where('wc.public_id', '=', $publicId)
            ->whereNull('wc.deleted_at')
            ->first();

        if (!$row) {
            return null;
        }

        $total = (int)(new QueryBuilder($this->pdo))
            ->from('cycle_tasks')
            ->where('cycle_id', '=', (int)$row['id'])
            ->whereNull('deleted_at')
            ->count();

        $completed = (int)(new QueryBuilder($this->pdo))
            ->from('cycle_tasks ct')
            ->innerJoin('tasks t', 't.id', '=', 'ct.task_id')
            ->where('ct.cycle_id', '=', (int)$row['id'])
            ->whereNull('ct.deleted_at')
            ->whereRaw("t.status_code IN (?, ?, ?)", ['done', 'closed', 'archived'])
            ->count();

        $row['tasks_count'] = $total;
        $row['completed_tasks_count'] = $completed;
        $row['open_tasks_count'] = $total - $completed;
        $row['progress_percent'] = $total > 0 ? (int)round(($completed / $total) * 100) : 0;
        $row['time_state'] = $this->computeTimeState($row);

        return $row;
    }

    public function findById(int $id): ?array
    {
        $row = (new QueryBuilder($this->pdo))
            ->from('work_cycles')
            ->where('id', '=', $id)
            ->whereNull('deleted_at')
            ->first();

        return $row !== false ? $row : null;
    }

    public function create(array $payload): array
    {
        (new QueryBuilder($this->pdo))
            ->from('work_cycles')
            ->insert($payload);

        return $payload;
    }

    public function updateByPublicId(string $publicId, array $set): bool
    {
        if ($set === []) {
            return false;
        }

        $set['row_version'] = new \Api\System\Library\Database\Builder\Expression('row_version + 1');
        $set['updated_at'] = gmdate('Y-m-d H:i:s');

        return (new QueryBuilder($this->pdo))
            ->from('work_cycles')
            ->where('public_id', '=', $publicId)
            ->whereNull('deleted_at')
            ->update($set) > 0;
    }

    public function softDeleteByPublicId(string $publicId, string $deletedAt): bool
    {
        return (new QueryBuilder($this->pdo))
            ->from('work_cycles')
            ->where('public_id', '=', $publicId)
            ->whereNull('deleted_at')
            ->update([
                'deleted_at' => $deletedAt,
                'updated_at' => $deletedAt,
                'row_version' => new \Api\System\Library\Database\Builder\Expression('row_version + 1'),
            ]) > 0;
    }

    public function archiveByPublicId(string $publicId, string $archivedAt): bool
    {
        return (new QueryBuilder($this->pdo))
            ->from('work_cycles')
            ->where('public_id', '=', $publicId)
            ->whereNull('deleted_at')
            ->update([
                'archived_at' => $archivedAt,
                'updated_at' => $archivedAt,
                'row_version' => new \Api\System\Library\Database\Builder\Expression('row_version + 1'),
            ]) > 0;
    }

    public function projectIdByPublicId(string $projectPublicId): ?int
    {
        $row = (new QueryBuilder($this->pdo))
            ->from('projects')
            ->select(['id'])
            ->where('public_id', '=', $projectPublicId)
            ->first();

        return isset($row['id']) ? (int)$row['id'] : null;
    }

    public function userIdByPublicId(string $userPublicId): ?int
    {
        $row = (new QueryBuilder($this->pdo))
            ->from('users')
            ->select(['id'])
            ->where('public_id', '=', $userPublicId)
            ->first();

        return isset($row['id']) ? (int)$row['id'] : null;
    }

    private function computeTimeState(array $cycle): string
    {
        $now = time();
        $start = isset($cycle['start_at']) ? strtotime((string)$cycle['start_at']) : null;
        $end = isset($cycle['end_at']) ? strtotime((string)$cycle['end_at']) : null;

        if ($start !== false && $start > $now) {
            return 'not_started';
        }
        if ($end !== false && $end < $now) {
            return 'ended';
        }
        return 'running';
    }
}
