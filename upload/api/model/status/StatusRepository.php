<?php
declare(strict_types=1);

namespace Api\Model\Status;

use Api\System\Library\Database\Builder\QueryBuilder;
use PDO;
use Api\System\Library\Support\LikeEscaper;

final class StatusRepository
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
            ->select(['public_id', 'scope', 'code', 'title', 'color', 'sort_order', 'is_active', 'created_at', 'updated_at'])
            ->orderBy('sort_order', 'ASC')
            ->orderBy('created_at', 'ASC')
            ->limit($limit)
            ->offset($offset)
            ->get();

        return [$items, $total, $page, $limit];
    }

    private function buildListQuery(array $filters): QueryBuilder
    {
        $query = (new QueryBuilder($this->pdo))
            ->from('statuses');

        if (!empty($filters['scope'])) {
            $query->where('scope', '=', (string)$filters['scope']);
        }

        if (isset($filters['is_active']) && $filters['is_active'] !== '') {
            $query->where('is_active', '=', (int)((string)$filters['is_active'] === '1'));
        }

        if (!empty($filters['search'])) {
            $search = '%' . LikeEscaper::escape((string)$filters['search']) . '%';
            $query->whereRaw('(code LIKE ? OR title LIKE ?)', [$search, $search]);
        }

        return $query;
    }

    public function findByPublicId(string $publicId): ?array
    {
        return (new QueryBuilder($this->pdo))
            ->from('statuses')
            ->where('public_id', '=', $publicId)
            ->first();
    }

    public function findByScopeAndCode(string $scope, string $code): ?array
    {
        return (new QueryBuilder($this->pdo))
            ->from('statuses')
            ->where('scope', '=', $scope)
            ->where('code', '=', $code)
            ->first();
    }

    public function create(array $payload): void
    {
        (new QueryBuilder($this->pdo))
            ->from('statuses')
            ->insert($payload);
    }

    public function updateByPublicId(string $publicId, array $set): bool
    {
        if ($set === []) {
            return false;
        }

        return (new QueryBuilder($this->pdo))
            ->from('statuses')
            ->where('public_id', '=', $publicId)
            ->update($set) > 0;
    }

    public function deleteByPublicId(string $publicId): bool
    {
        return (new QueryBuilder($this->pdo))
            ->from('statuses')
            ->where('public_id', '=', $publicId)
            ->delete() > 0;
    }

    public function usageCount(string $scope, string $code): int
    {
        return match ($scope) {
            'task' => $this->usageCountTaskScope($code),
            'project' => $this->usageCountProjectScope($code),
            'worklog_activity' => $this->usageCountWorklogActivityScope($code),
            default => 0,
        };
    }

    public function remapUsage(string $scope, string $fromCode, string $toCode): int
    {
        return match ($scope) {
            'task' => $this->remapTaskScope($fromCode, $toCode),
            'project' => $this->remapProjectScope($fromCode, $toCode),
            'worklog_activity' => $this->remapWorklogActivityScope($fromCode, $toCode),
            default => 0,
        };
    }

    private function usageCountTaskScope(string $code): int
    {
        return (new QueryBuilder($this->pdo))
            ->from('tasks')
            ->where('status_code', '=', $code)
            ->count();
    }

    private function usageCountProjectScope(string $code): int
    {
        return (new QueryBuilder($this->pdo))
            ->from('projects')
            ->where('status_code', '=', $code)
            ->count();
    }

    private function remapTaskScope(string $fromCode, string $toCode): int
    {
        $updatedAt = gmdate('Y-m-d H:i:s');
        return (new QueryBuilder($this->pdo))
            ->from('tasks')
            ->where('status_code', '=', $fromCode)
            ->update([
                'status_code' => $toCode,
                'updated_at' => $updatedAt,
            ]);
    }

    private function remapProjectScope(string $fromCode, string $toCode): int
    {
        return (new QueryBuilder($this->pdo))
            ->from('projects')
            ->where('status_code', '=', $fromCode)
            ->update([
                'status_code' => $toCode,
                'updated_at' => gmdate('Y-m-d H:i:s'),
            ]);
    }

    /**
     * Activity codes live in work_logs, tasks (as default) and rate_card_lines.
     * A code that is referenced anywhere counts as "in use" and cannot be
     * deleted without a remap target.
     */
    private function usageCountWorklogActivityScope(string $code): int
    {
        $workLogs = (new QueryBuilder($this->pdo))
            ->from('work_logs')
            ->where('activity_code', '=', $code)
            ->count();

        $tasks = (new QueryBuilder($this->pdo))
            ->from('tasks')
            ->where('activity_code', '=', $code)
            ->whereNull('deleted_at')
            ->count();

        $lines = (new QueryBuilder($this->pdo))
            ->from('rate_card_lines')
            ->where('activity_code', '=', $code)
            ->whereNull('deleted_at')
            ->count();

        return (int)$workLogs + (int)$tasks + (int)$lines;
    }

    private function remapWorklogActivityScope(string $fromCode, string $toCode): int
    {
        $updatedAt = gmdate('Y-m-d H:i:s');
        $affected = 0;

        $affected += (new QueryBuilder($this->pdo))
            ->from('work_logs')
            ->where('activity_code', '=', $fromCode)
            ->update(['activity_code' => $toCode]);

        $affected += (new QueryBuilder($this->pdo))
            ->from('tasks')
            ->where('activity_code', '=', $fromCode)
            ->whereNull('deleted_at')
            ->update([
                'activity_code' => $toCode,
                'updated_at' => $updatedAt,
            ]);

        $affected += (new QueryBuilder($this->pdo))
            ->from('rate_card_lines')
            ->where('activity_code', '=', $fromCode)
            ->whereNull('deleted_at')
            ->update([
                'activity_code' => $toCode,
                'updated_at' => $updatedAt,
            ]);

        return (int)$affected;
    }

    public function countActiveTasksInStatus(string $statusCode): int
    {
        return (int)(new QueryBuilder($this->pdo))
            ->from('tasks')
            ->where('status_code', '=', $statusCode)
            ->whereNull('deleted_at')
            ->count();
    }
}
