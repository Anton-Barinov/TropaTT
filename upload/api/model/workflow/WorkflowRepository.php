<?php
declare(strict_types=1);

namespace Api\Model\Workflow;

use Api\System\Library\Database\Builder\QueryBuilder;
use Api\System\Library\Security\UrlSafetyValidator;
use PDO;

final class WorkflowRepository
{
    public function __construct(
        private readonly PDO $pdo,
        private readonly ?UrlSafetyValidator $urlSafety = null
    ) {
    }

    public function listRules(array $filters): array
    {
        $page = max(1, (int)($filters['page'] ?? 1));
        $limit = min(100, max(1, (int)($filters['limit'] ?? 20)));
        $offset = ($page - 1) * $limit;

        // Fail-closed scope: keep the -1 sentinel from accessScope() so an actor
        // without a valid id (id <= 0) matches nothing instead of widening to
        // "no scope". Empty array = root (no restriction). See applyCreatorScope
        // in SearchRepository for the same convention.
        $creatorIds = is_array($filters['created_by_user_ids'] ?? null)
            ? array_values(array_unique(array_map('intval', $filters['created_by_user_ids'])))
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

        // Fail-closed scope: keep the -1 sentinel from accessScope() so an actor
        // without a valid id (id <= 0) matches nothing instead of widening to
        // "no scope". Empty array = root (no restriction). See applyCreatorScope
        // in SearchRepository for the same convention.
        $creatorIds = is_array($filters['created_by_user_ids'] ?? null)
            ? array_values(array_unique(array_map('intval', $filters['created_by_user_ids'])))
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

    public function taskIdByPublicId(string $taskPublicId): ?int
    {
        $row = (new QueryBuilder($this->pdo))
            ->from('tasks')
            ->select(['id'])
            ->where('public_id', '=', $taskPublicId)
            ->whereNull('deleted_at')
            ->first();
        $id = $row['id'] ?? false;

        return $id !== false ? (int)$id : null;
    }

    public function userIdByPublicId(string $userPublicId): ?int
    {
        $row = (new QueryBuilder($this->pdo))
            ->from('users')
            ->select(['id'])
            ->where('public_id', '=', $userPublicId)
            ->first();
        $id = $row['id'] ?? false;

        return $id !== false ? (int)$id : null;
    }

    public function projectIdByPublicId(string $projectPublicId): ?int
    {
        $row = (new QueryBuilder($this->pdo))
            ->from('projects')
            ->select(['id'])
            ->where('public_id', '=', $projectPublicId)
            ->first();
        $id = $row['id'] ?? false;

        return $id !== false ? (int)$id : null;
    }

    /**
     * Manager user IDs of every team the given user is a member of.
     * @return int[]
     */
    public function findManagerIdsByMember(int $memberUserId): array
    {
        if ($memberUserId <= 0) {
            return [];
        }

        $rows = (new QueryBuilder($this->pdo))
            ->from('teams')
            ->select(['manager_user_id'])
            ->whereRaw('member_user_ids LIKE ?', ['%"' . $memberUserId . '"%'])
            ->get();

        $ids = [];
        foreach ($rows as $row) {
            $managerId = (int)($row['manager_user_id'] ?? 0);
            if ($managerId > 0) {
                $ids[] = $managerId;
            }
        }

        return array_values(array_unique($ids));
    }

    /**
     * Manager of the project the task belongs to (the tasks table itself has
     * no manager column — the task's manager is the project manager).
     */
    public function taskManagerUserId(string $taskPublicId): ?int
    {
        $row = (new QueryBuilder($this->pdo))
            ->from('tasks t')
            ->leftJoin('projects p', 'p.id', '=', 't.project_id')
            ->select(['p.manager_user_id'])
            ->where('t.public_id', '=', $taskPublicId)
            ->first();
        $id = $row['manager_user_id'] ?? 0;

        return $id > 0 ? (int)$id : null;
    }

    public function updateTaskField(string $taskPublicId, string $field, mixed $value): void
    {
        (new QueryBuilder($this->pdo))
            ->from('tasks')
            ->where('public_id', '=', $taskPublicId)
            ->update([$field => $value, 'updated_at' => gmdate('Y-m-d H:i:s')]);
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
        $taskId = $this->taskIdByPublicId($taskPublicId);
        (new QueryBuilder($this->pdo))->from('comments')->insert([
            'public_id' => $pid,
            'task_id' => $taskId,
            'project_id' => null,
            'author_user_id' => null,
            'body' => '[' . $source . '] ' . $text,
            'visibility' => 'internal',
            'created_at' => gmdate('Y-m-d H:i:s'),
            'updated_at' => gmdate('Y-m-d H:i:s'),
        ]);
    }

    public function createFollowUpTask(string $title, ?int $assigneeId, ?int $projectId, string $sourceTaskPublicId, ?int $creatorUserId, string $description = ''): void
    {
        $pid = 'tsk_' . bin2hex(random_bytes(8));
        $now = gmdate('Y-m-d H:i:s');
        (new QueryBuilder($this->pdo))->from('tasks')->insert([
            'public_id' => $pid,
            'title' => $title,
            'description' => $description,
            'status_code' => 'new',
            'priority_code' => 'normal',
            'assignee_user_id' => $assigneeId,
            'project_id' => $projectId,
            'creator_user_id' => $creatorUserId,
            'created_at' => $now,
            'updated_at' => $now,
            'row_version' => 1,
        ]);

        $parentTaskId = $this->taskIdByPublicId($sourceTaskPublicId);
        $childTaskId = $this->taskIdByPublicId($pid);
        if ($parentTaskId !== null && $childTaskId !== null) {
            (new QueryBuilder($this->pdo))->from('task_relations')->insert([
                'public_id' => 'trl_' . bin2hex(random_bytes(8)),
                'parent_task_id' => $parentTaskId,
                'child_task_id' => $childTaskId,
                'relation_type' => 'subtask',
                'sort_order' => 10,
                'legacy_subtask_public_id' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function createReminder(?int $userId, ?int $taskId, string $remindAt): void
    {
        if ($userId === null || $userId <= 0) {
            return;
        }

        (new QueryBuilder($this->pdo))->from('reminders')->insert([
            'public_id' => 'rmd_' . bin2hex(random_bytes(8)),
            'user_id' => $userId,
            'task_id' => $taskId,
            'remind_at' => $remindAt,
            'status' => 'pending',
            'created_at' => gmdate('Y-m-d H:i:s'),
        ]);
    }

    public function callWebhookAsync(string $url, array $context): void
    {
        $validator = $this->urlSafety ?? new UrlSafetyValidator();
        $validated = $validator->validateProviderUrl($url, true, ['https']);
        if (!(bool)($validated['ok'] ?? false)) {
            error_log('[WorkflowRepository] SSRF blocked: ' . ($validated['code'] ?? 'UNKNOWN') . ' url=' . $url);
            return;
        }
        $resolvedIps = (array)($validated['resolved_ips'] ?? []);

        $payload = json_encode($context, JSON_UNESCAPED_UNICODE);
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_TIMEOUT => 5,
            CURLOPT_CONNECTTIMEOUT => 3,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
            CURLOPT_REDIR_PROTOCOLS => 0,
        ]);

        // SEC-002: DNS pinning — force cURL to use validated IP
        if (!empty($resolvedIps) && defined('CURLOPT_RESOLVE')) {
            $host = (string)(parse_url($url, PHP_URL_HOST) ?: '');
            $scheme = strtolower((string)(parse_url($url, PHP_URL_SCHEME) ?: 'https'));
            $port = (int)(parse_url($url, PHP_URL_PORT) ?: ($scheme === 'https' ? 443 : 80));
            if ($host !== '') {
                $resolveEntry = $host . ':' . $port . ':' . trim((string)$resolvedIps[0]);
                curl_setopt($ch, CURLOPT_RESOLVE, [$resolveEntry]);
            }
        }

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
