<?php
declare(strict_types=1);

namespace Api\Model\Status;

use Api\System\Library\Database\Builder\QueryBuilder;
use PDO;
use Api\System\Library\Support\LikeEscaper;
use Api\System\Library\Support\TaskStatusSemantics;

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
            ->select(['public_id', 'scope', 'code', 'title', 'color', 'sort_order', 'is_active', 'is_closed', 'created_at', 'updated_at'])
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
        if ((int)($filters['organization_id'] ?? 0) > 0) {
            $query->where('organization_id', '=', (int)$filters['organization_id']);
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

    public function findByPublicId(string $publicId, ?int $organizationId = null): ?array
    {
        $query = (new QueryBuilder($this->pdo))
            ->from('statuses')
            ->where('public_id', '=', $publicId);
        if ($organizationId !== null && $organizationId > 0) $query->where('organization_id', '=', $organizationId);
        return $query->first();
    }

    public function findByScopeAndCode(string $scope, string $code, ?int $organizationId = null): ?array
    {
        $query = (new QueryBuilder($this->pdo))
            ->from('statuses')
            ->where('scope', '=', $scope)
            ->where('code', '=', $code);
        if ($organizationId !== null && $organizationId > 0) $query->where('organization_id', '=', $organizationId);
        return $query->first();
    }

    public function create(array $payload): void
    {
        (new QueryBuilder($this->pdo))
            ->from('statuses')
            ->insert($payload);

        TaskStatusSemantics::resetCache($this->pdo);
    }

    public function updateByPublicId(string $publicId, array $set, ?int $organizationId = null): bool
    {
        if ($set === []) {
            return false;
        }

        $query = (new QueryBuilder($this->pdo))
            ->from('statuses')
            ->where('public_id', '=', $publicId);
        if ($organizationId !== null && $organizationId > 0) $query->where('organization_id', '=', $organizationId);
        $updated = $query->update($set) > 0;

        TaskStatusSemantics::resetCache($this->pdo);

        return $updated;
    }

    public function deleteByPublicId(string $publicId, ?int $organizationId = null): bool
    {
        $query = (new QueryBuilder($this->pdo))
            ->from('statuses')
            ->where('public_id', '=', $publicId);
        if ($organizationId !== null && $organizationId > 0) $query->where('organization_id', '=', $organizationId);
        $deleted = $query->delete() > 0;

        TaskStatusSemantics::resetCache($this->pdo);

        return $deleted;
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
