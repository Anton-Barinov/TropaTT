<?php
declare(strict_types=1);

namespace Api\Model\Workflow;

use Api\System\Library\Database\Builder\QueryBuilder;
use PDO;

final class WorkflowRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function listRules(array $filters): array
    {
        $page = max(1, (int)($filters['page'] ?? 1));
        $limit = min(100, max(1, (int)($filters['limit'] ?? 20)));
        $offset = ($page - 1) * $limit;

        $creatorIds = is_array($filters['created_by_user_ids'] ?? null)
            ? array_values(array_filter(array_map('intval', $filters['created_by_user_ids']), static fn(int $id): bool => $id > 0))
            : [];
        $total = $this->buildRulesListQuery($filters, $creatorIds)->count();
        $items = $this->buildRulesListQuery($filters, $creatorIds)
            ->select(['public_id', 'title', 'trigger_code', 'action_code', 'payload', 'is_enabled', 'created_by_user_id', 'created_at', 'updated_at'])
            ->orderBy('updated_at', 'DESC')
            ->orderBy('public_id', 'DESC')
            ->limit($limit)
            ->offset($offset)
            ->get();

        return [$items, $total, $page, $limit];
    }

    /** @param array<int,int> $creatorIds */
    private function buildRulesListQuery(array $filters, array $creatorIds): QueryBuilder
    {
        $query = (new QueryBuilder($this->pdo))
            ->from('automation_rules');

        if (!empty($filters['trigger_code'])) {
            $query->where('trigger_code', '=', (string)$filters['trigger_code']);
        }

        if (!empty($filters['action_code'])) {
            $query->where('action_code', '=', (string)$filters['action_code']);
        }

        if (isset($filters['is_enabled']) && $filters['is_enabled'] !== '') {
            $query->where('is_enabled', '=', ((int)$filters['is_enabled'] === 1) ? 1 : 0);
        }

        if (!empty($filters['search'])) {
            $search = '%' . (string)$filters['search'] . '%';
            $query->whereRaw('(title LIKE ? OR trigger_code LIKE ? OR action_code LIKE ?)', [$search, $search, $search]);
        }

        if ($creatorIds !== []) {
            if (!empty($filters['include_unowned'])) {
                $placeholders = implode(',', array_fill(0, count($creatorIds), '?'));
                $query->whereRaw('(created_by_user_id IS NULL OR created_by_user_id IN (' . $placeholders . '))', $creatorIds);
            } else {
                $query->whereIn('created_by_user_id', $creatorIds);
            }
        }

        return $query;
    }

    public function findRuleByPublicId(string $publicId): ?array
    {
        $row = (new QueryBuilder($this->pdo))
            ->from('automation_rules')
            ->select(['id', 'public_id', 'title', 'trigger_code', 'action_code', 'payload', 'is_enabled', 'created_by_user_id', 'created_at', 'updated_at'])
            ->where('public_id', '=', $publicId)
            ->first();

        return $row ?: null;
    }

    public function createRule(array $payload): void
    {
        (new QueryBuilder($this->pdo))
            ->from('automation_rules')
            ->insert($payload);
    }

    public function updateRuleByPublicId(string $publicId, array $set): bool
    {
        if ($set === []) {
            return false;
        }

        return (new QueryBuilder($this->pdo))
            ->from('automation_rules')
            ->where('public_id', '=', $publicId)
            ->update($set) > 0;
    }

    public function deleteRuleByPublicId(string $publicId): bool
    {
        return (new QueryBuilder($this->pdo))
            ->from('automation_rules')
            ->where('public_id', '=', $publicId)
            ->delete() > 0;
    }

    public function createRun(array $payload): void
    {
        (new QueryBuilder($this->pdo))
            ->from('automation_runs')
            ->insert($payload);
    }

    public function listRuns(array $filters): array
    {
        $page = max(1, (int)($filters['page'] ?? 1));
        $limit = min(100, max(1, (int)($filters['limit'] ?? 20)));
        $offset = ($page - 1) * $limit;

        $creatorIds = is_array($filters['created_by_user_ids'] ?? null)
            ? array_values(array_filter(array_map('intval', $filters['created_by_user_ids']), static fn(int $id): bool => $id > 0))
            : [];
        $total = $this->buildRunsListQuery($filters, $creatorIds)->count();
        $items = $this->buildRunsListQuery($filters, $creatorIds)
            ->select([
                'r.public_id',
                'r.status',
                'r.error',
                'r.created_at',
                'ar.public_id AS rule_public_id',
                'ar.title AS rule_title',
                'ar.trigger_code',
                'ar.action_code',
            ])
            ->orderBy('r.created_at', 'DESC')
            ->orderBy('r.public_id', 'DESC')
            ->limit($limit)
            ->offset($offset)
            ->get();

        return [$items, $total, $page, $limit];
    }

    /** @param array<int,int> $creatorIds */
    private function buildRunsListQuery(array $filters, array $creatorIds): QueryBuilder
    {
        $query = (new QueryBuilder($this->pdo))
            ->from('automation_runs r')
            ->join('automation_rules ar', 'ar.id', '=', 'r.rule_id');

        if (!empty($filters['status'])) {
            $query->where('r.status', '=', (string)$filters['status']);
        }

        if (!empty($filters['rule_public_id'])) {
            $query->where('ar.public_id', '=', (string)$filters['rule_public_id']);
        }

        if ($creatorIds !== []) {
            if (!empty($filters['include_unowned'])) {
                $placeholders = implode(',', array_fill(0, count($creatorIds), '?'));
                $query->whereRaw('(ar.created_by_user_id IS NULL OR ar.created_by_user_id IN (' . $placeholders . '))', $creatorIds);
            } else {
                $query->whereIn('ar.created_by_user_id', $creatorIds);
            }
        }

        return $query;
    }

    /** @return array<int,array<string,mixed>> */
    public function findEnabledRulesByTrigger(string $triggerCode): array
    {
        return (new QueryBuilder($this->pdo))
            ->from('automation_rules')
            ->where('trigger_code', '=', $triggerCode)
            ->where('is_enabled', '=', 1)
            ->get();
    }

    public function updateTaskField(string $taskPublicId, string $field, mixed $value): void
    {
        (new QueryBuilder($this->pdo))
            ->from('tasks')
            ->where('public_id', '=', $taskPublicId)
            ->update([$field => $value]);
    }

    /** @param array<int,int> $userIds */
    public function createNotifications(array $userIds, string $title, string $body, string $taskPublicId): void
    {
        foreach ($userIds as $uid) {
            $pid = 'ntf_' . bin2hex(random_bytes(8));
            (new QueryBuilder($this->pdo))->from('notifications')->insert([
                'public_id' => $pid,
                'user_id' => (int)$uid,
                'category' => 'workflow',
                'title' => $title,
                'body' => $body,
                'entity_type' => 'task',
                'entity_public_id' => $taskPublicId,
                'action_code' => 'workflow',
                'is_read' => 0,
                'created_at' => gmdate('Y-m-d H:i:s'),
            ]);
        }
    }

    public function createTaskComment(string $taskPublicId, string $text, string $source): void
    {
        $pid = 'cmt_' . bin2hex(random_bytes(8));
        (new QueryBuilder($this->pdo))->from('comments')->insert([
            'public_id' => $pid,
            'entity_type' => 'task',
            'entity_public_id' => $taskPublicId,
            'body' => '[' . $source . '] ' . $text,
            'visibility' => 'internal',
            'created_at' => gmdate('Y-m-d H:i:s'),
        ]);
    }

    public function createFollowUpTask(string $title, ?int $assigneeId, ?int $projectId, string $sourceTaskPublicId): void
    {
        $pid = 'task_' . bin2hex(random_bytes(8));
        $now = gmdate('Y-m-d H:i:s');
        (new QueryBuilder($this->pdo))->from('tasks')->insert([
            'public_id' => $pid,
            'title' => $title,
            'status_code' => 'new',
            'priority_code' => 'normal',
            'assignee_user_id' => $assigneeId,
            'project_id' => $projectId,
            'created_by_user_id' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    public function callWebhookAsync(string $url, array $context): void
    {
        $payload = json_encode($context, JSON_UNESCAPED_UNICODE);
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_TIMEOUT => 5,
            CURLOPT_CONNECTTIMEOUT => 3,
        ]);
        curl_exec($ch);
        curl_close($ch);
    }

    public function escalateSla(string $taskPublicId): void
    {
        (new QueryBuilder($this->pdo))
            ->from('tasks')
            ->where('public_id', '=', $taskPublicId)
            ->update(['sla_breached' => 1]);
    }
}
