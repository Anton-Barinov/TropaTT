<?php
declare(strict_types=1);

namespace Api\Model\Sla;

use Api\System\Library\Database\Builder\QueryBuilder;
use PDO;

final class SlaRepository
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
            ->select(['public_id', 'title', 'response_minutes', 'resolve_minutes', 'escalation_payload', 'created_at', 'updated_at'])
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
            ->from('sla_policies');

        if (!empty($filters['search'])) {
            $query->where('title', 'LIKE', '%' . (string)$filters['search'] . '%');
        }

        return $query;
    }

    public function findByPublicId(string $publicId): ?array
    {
        $row = (new QueryBuilder($this->pdo))
            ->from('sla_policies')
            ->select(['public_id', 'title', 'response_minutes', 'resolve_minutes', 'escalation_payload', 'created_at', 'updated_at'])
            ->where('public_id', '=', $publicId)
            ->first();

        return $row ?: null;
    }

    public function create(array $payload): void
    {
        (new QueryBuilder($this->pdo))
            ->from('sla_policies')
            ->insert($payload);
    }

    public function updateByPublicId(string $publicId, array $set): bool
    {
        if ($set === []) {
            return false;
        }

        return (new QueryBuilder($this->pdo))
            ->from('sla_policies')
            ->where('public_id', '=', $publicId)
            ->update($set) > 0;
    }

    public function deleteByPublicId(string $publicId): bool
    {
        return (new QueryBuilder($this->pdo))
            ->from('sla_policies')
            ->where('public_id', '=', $publicId)
            ->delete() > 0;
    }

    public function reportSummary(): array
    {
        $summary = (new QueryBuilder($this->pdo))
            ->from('sla_policies')
            ->select([
                'COUNT(*) AS policies_total',
                'AVG(response_minutes) AS avg_response_minutes',
                'AVG(resolve_minutes) AS avg_resolve_minutes',
            ])
            ->first() ?: [];

        $tasksOverdue = (new QueryBuilder($this->pdo))
            ->from('tasks')
            ->whereNotNull('due_at')
            ->where('due_at', '<', gmdate('Y-m-d H:i:s'))
            ->whereNull('deleted_at')
            ->whereNull('archived_at')
            ->count();

        return [
            'policies_total' => (int)($summary['policies_total'] ?? 0),
            'avg_response_minutes' => isset($summary['avg_response_minutes']) ? (float)$summary['avg_response_minutes'] : 0.0,
            'avg_resolve_minutes' => isset($summary['avg_resolve_minutes']) ? (float)$summary['avg_resolve_minutes'] : 0.0,
            'tasks_overdue' => $tasksOverdue,
        ];
    }

    public function findTaskByPublicId(string $publicId): ?array
    {
        return (new QueryBuilder($this->pdo))->from('tasks')->where('public_id', '=', $publicId)->whereNull('deleted_at')->first();
    }

    public function updateTaskSla(int $taskId, int $slaId, string $responseDeadline, string $resolveDeadline): void
    {
        (new QueryBuilder($this->pdo))->from('tasks')->where('id', '=', $taskId)->update([
            'sla_policy_id' => $slaId,
            'sla_response_deadline' => $responseDeadline,
            'sla_resolve_deadline' => $resolveDeadline,
        ]);
    }

    public function markBreached(string $now): int
    {
        return (new QueryBuilder($this->pdo))->from('tasks')
            ->whereNotNull('sla_policy_id')
            ->where('sla_resolve_deadline', '<', $now)
            ->where('sla_breached', '=', 0)
            ->whereIn('status_code', ['in_progress', 'review'])
            ->update(['sla_breached' => 1]);
    }
}
