<?php
declare(strict_types=1);

namespace Module\Crm\WipLimit\Service;

use PDO;

/**
 * Live WIP-limit engine.
 *
 * The single source of truth is the `tasks` table: current WIP counts are always
 * computed on the fly. This avoids the drift that a denormalized counter
 * (`crm_wip_counts`) introduced when tasks were created, deleted or reassigned
 * outside the status-change hook.
 */
final class WipLimitService
{
    private const DEFAULT_LIMIT = 5;
    private const DEFAULT_TEAM_LIMIT = 10;
    private const DEFAULT_PROJECT_LIMIT = 10;
    private const DEFAULT_WIP_STATUSES = ['in_progress', 'review'];
    private const SCOPE_TEAM = 'team';
    private const SCOPE_PROJECT = 'project';

    /** @var array<string, mixed> */
    private array $config;

    /**
     * @param array<string, mixed> $config Module config (merged with defaults).
     */
    public function __construct(
        private readonly PDO $pdo,
        array $config = [],
    ) {
        $this->config = array_replace(self::defaults(), $config);
    }

    /**
     * @return array<string, mixed>
     */
    private static function defaults(): array
    {
        return [
            'default_limit' => self::DEFAULT_LIMIT,
            'team_default_limit' => self::DEFAULT_TEAM_LIMIT,
            'project_default_limit' => self::DEFAULT_PROJECT_LIMIT,
            'enforce_on_status' => self::DEFAULT_WIP_STATUSES,
            'notify_on_exceed' => true,
            'excluded_role_ids' => [],
        ];
    }

    /**
     * Status codes that count toward a user's WIP.
     *
     * @return array<int, string>
     */
    public function getWipStatusCodes(): array
    {
        $codes = $this->config['enforce_on_status'] ?? self::DEFAULT_WIP_STATUSES;
        if (!is_array($codes)) {
            $codes = [$codes];
        }

        $result = [];
        foreach ($codes as $code) {
            $code = trim((string)$code);
            if ($code !== '') {
                $result[] = $code;
            }
        }

        return array_values(array_unique($result));
    }

    public function getDefaultLimit(): int
    {
        $limit = (int)($this->config['default_limit'] ?? self::DEFAULT_LIMIT);
        return $limit >= 1 ? $limit : self::DEFAULT_LIMIT;
    }

    public function getUserLimit(int $userId): int
    {
        if ($userId <= 0) {
            return $this->getDefaultLimit();
        }

        try {
            $stmt = $this->pdo->prepare('SELECT max_tasks FROM crm_wip_limits WHERE user_id = :uid AND is_active = 1');
            $stmt->execute(['uid' => $userId]);
            $value = $stmt->fetchColumn();
            return $value !== false && (int)$value >= 1 ? (int)$value : $this->getDefaultLimit();
        } catch (\Throwable $e) {
            error_log('[WipLimitService::getUserLimit] User ' . $userId . ': ' . $e->getMessage());
            return $this->getDefaultLimit();
        }
    }

    public function getUserWipCount(int $userId): int
    {
        if ($userId <= 0) {
            return 0;
        }

        $codes = $this->getWipStatusCodes();
        if ($codes === []) {
            return 0;
        }

        try {
            $placeholders = implode(',', array_fill(0, count($codes), '?'));
            $sql = "SELECT COUNT(*) FROM tasks WHERE assignee_user_id = ? AND status_code IN ({$placeholders}) AND deleted_at IS NULL";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute(array_merge([$userId], $codes));
            return (int)$stmt->fetchColumn();
        } catch (\Throwable $e) {
            error_log('[WipLimitService::getUserWipCount] User ' . $userId . ': ' . $e->getMessage());
            return 0;
        }
    }

    /**
     * Per-user summary with live counts, ordered by overload (over limit first).
     *
     * @return array<int, array<string, mixed>>
     */
    public function getSummary(): array
    {
        try {
            $excludedRoleIds = $this->getExcludedRoleIds();

            $sql = 'SELECT u.id AS user_id, u.full_name, u.login
                    FROM users u
                    WHERE u.deleted_at IS NULL AND u.is_active = 1';
            $params = [];

            if ($excludedRoleIds !== []) {
                $placeholders = implode(',', array_fill(0, count($excludedRoleIds), '?'));
                $sql .= " AND u.id NOT IN (SELECT DISTINCT ur.user_id FROM user_roles ur WHERE ur.role_id IN ({$placeholders}))";
                $params = $excludedRoleIds;
            }

            $sql .= ' ORDER BY u.full_name ASC, u.id ASC';

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            $users = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

            $items = [];
            foreach ($users as $user) {
                $userId = (int)($user['user_id'] ?? 0);
                $limit = $this->getUserLimit($userId);
                $current = $this->getUserWipCount($userId);
                $items[] = [
                    'user_id' => $userId,
                    'full_name' => (string)($user['full_name'] ?? ''),
                    'login' => (string)($user['login'] ?? ''),
                    'limit_value' => $limit,
                    'current_count' => $current,
                    'at_limit' => $current >= $limit ? 1 : 0,
                    'over_limit' => $current > $limit ? 1 : 0,
                ];
            }

            usort($items, static function (array $a, array $b): int {
                if ((int)$a['over_limit'] !== (int)$b['over_limit']) {
                    return (int)$b['over_limit'] <=> (int)$a['over_limit'];
                }
                return (int)$b['current_count'] <=> (int)$a['current_count'];
            });

            return $items;
        } catch (\Throwable $e) {
            error_log('[WipLimitService::getSummary] ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Configured limits with a live current_count attached.
     *
     * @return array<int, array<string, mixed>>
     */
    public function listLimits(): array
    {
        try {
            $stmt = $this->pdo->query(
                'SELECT l.id, l.user_id, l.max_tasks, l.is_active, l.created_at,
                        u.full_name, u.login
                 FROM crm_wip_limits l
                 LEFT JOIN users u ON u.id = l.user_id
                 ORDER BY l.user_id'
            );
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

            $items = [];
            foreach ($rows as $row) {
                $row['current_count'] = $this->getUserWipCount((int)($row['user_id'] ?? 0));
                $items[] = $row;
            }

            return $items;
        } catch (\Throwable $e) {
            error_log('[WipLimitService::listLimits] ' . $e->getMessage());
            return [];
        }
    }

    /**
     * WIP status of the task's assignee, resolved from a task public id.
     * Used by the task detail sidebar to show and edit the assignee's limit
     * inline, without the client having to join core task data.
     *
     * @return array<string, mixed>|null Null when the task does not exist.
     */
    public function getAssigneeWip(string $taskPublicId): ?array
    {
        if ($taskPublicId === '') {
            return null;
        }

        try {
            $stmt = $this->pdo->prepare(
                'SELECT t.assignee_user_id, u.public_id, u.full_name, u.login
                 FROM tasks t
                 LEFT JOIN users u ON u.id = t.assignee_user_id
                 WHERE t.public_id = ? AND t.deleted_at IS NULL'
            );
            $stmt->execute([$taskPublicId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($row === false) {
                return null;
            }

            $assigneeId = (int)($row['assignee_user_id'] ?? 0);
            if ($assigneeId <= 0) {
                return [
                    'has_assignee' => false,
                    'user_id' => 0,
                    'public_id' => '',
                    'full_name' => '',
                    'login' => '',
                    'limit_value' => $this->getDefaultLimit(),
                    'current_count' => 0,
                    'at_limit' => 0,
                    'over_limit' => 0,
                ];
            }

            $limit = $this->getUserLimit($assigneeId);
            $current = $this->getUserWipCount($assigneeId);

            return [
                'has_assignee' => true,
                'user_id' => $assigneeId,
                'public_id' => (string)($row['public_id'] ?? ''),
                'full_name' => (string)($row['full_name'] ?? ''),
                'login' => (string)($row['login'] ?? ''),
                'limit_value' => $limit,
                'current_count' => $current,
                'at_limit' => $current >= $limit ? 1 : 0,
                'over_limit' => $current > $limit ? 1 : 0,
            ];
        } catch (\Throwable $e) {
            error_log('[WipLimitService::getAssigneeWip] ' . $e->getMessage());
            return null;
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getLimit(int $userId): ?array
    {
        if ($userId <= 0) {
            return null;
        }

        try {
            $stmt = $this->pdo->prepare('SELECT l.* FROM crm_wip_limits l WHERE l.user_id = :uid');
            $stmt->execute(['uid' => $userId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($row === false) {
                return null;
            }

            $row['current_count'] = $this->getUserWipCount($userId);
            return $row;
        } catch (\Throwable $e) {
            error_log('[WipLimitService::getLimit] ' . $e->getMessage());
            return null;
        }
    }

    public function setLimit(int $userId, int $maxTasks): void
    {
        try {
            $stmt = $this->pdo->prepare('INSERT INTO crm_wip_limits (user_id, max_tasks) VALUES (:uid, :max) ON DUPLICATE KEY UPDATE max_tasks = VALUES(max_tasks), updated_at = NOW()');
            $stmt->execute(['uid' => $userId, 'max' => $maxTasks]);
        } catch (\Throwable $e) {
            error_log('[WipLimitService::setLimit] Upsert failed, falling back: ' . $e->getMessage());
            $this->pdo->prepare('DELETE FROM crm_wip_limits WHERE user_id = :uid')->execute(['uid' => $userId]);
            $this->pdo->prepare('INSERT INTO crm_wip_limits (user_id, max_tasks) VALUES (:uid, :max)')->execute(['uid' => $userId, 'max' => $maxTasks]);
        }
    }

    public function deleteLimit(int $userId): void
    {
        $this->pdo->prepare('DELETE FROM crm_wip_limits WHERE user_id = :uid')->execute(['uid' => $userId]);
    }

    /**
     * Soft enforcement, invoked from the task.status_changed / task.assignee_changed
     * hooks. Recomputes the live count for the assignee and logs when the limit
     * is exceeded. The module page always shows the same live numbers.
     *
     * @param array<string, mixed> $context
     */
    public function enforce(array $context): void
    {
        $newStatus = (string)($context['new_status'] ?? $context['status_code'] ?? '');
        $assigneeId = (int)($context['assignee_id'] ?? $context['new_assignee_id'] ?? 0);

        if ($assigneeId <= 0) {
            return;
        }
        if ($newStatus === '' || !in_array($newStatus, $this->getWipStatusCodes(), true)) {
            return;
        }

        $limit = $this->getUserLimit($assigneeId);
        $current = $this->getUserWipCount($assigneeId);

        if ($current > $limit && (bool)($this->config['notify_on_exceed'] ?? true)) {
            error_log(sprintf(
                '[WipLimitService] User %d exceeded WIP limit: %d/%d (task %s)',
                $assigneeId,
                $current,
                $limit,
                (string)($context['task_public_id'] ?? '')
            ));
        }
    }

    /**
     * Tasks in the given project that currently count toward WIP.
     */
    public function getProjectWipCount(int $projectId): int
    {
        if ($projectId <= 0) {
            return 0;
        }

        $codes = $this->getWipStatusCodes();
        if ($codes === []) {
            return 0;
        }

        try {
            $placeholders = implode(',', array_fill(0, count($codes), '?'));
            $sql = "SELECT COUNT(*) FROM tasks t INNER JOIN projects p ON p.id = t.project_id WHERE t.project_id = ? AND t.status_code IN ({$placeholders}) AND t.deleted_at IS NULL AND p.archived_at IS NULL";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute(array_merge([$projectId], $codes));
            return (int)$stmt->fetchColumn();
        } catch (\Throwable $e) {
            error_log('[WipLimitService::getProjectWipCount] Project ' . $projectId . ': ' . $e->getMessage());
            return 0;
        }
    }

    /**
     * Tasks in the given team that currently count toward WIP. A task belongs to
     * a team through its project's team_public_id.
     */
    public function getTeamWipCount(int $teamId): int
    {
        if ($teamId <= 0) {
            return 0;
        }

        $codes = $this->getWipStatusCodes();
        if ($codes === []) {
            return 0;
        }

        try {
            $placeholders = implode(',', array_fill(0, count($codes), '?'));
            $sql = "SELECT COUNT(*) FROM tasks t INNER JOIN projects p ON p.id = t.project_id INNER JOIN teams tm ON tm.public_id = p.team_public_id WHERE tm.id = ? AND t.status_code IN ({$placeholders}) AND t.deleted_at IS NULL AND p.archived_at IS NULL";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute(array_merge([$teamId], $codes));
            return (int)$stmt->fetchColumn();
        } catch (\Throwable $e) {
            error_log('[WipLimitService::getTeamWipCount] Team ' . $teamId . ': ' . $e->getMessage());
            return 0;
        }
    }

    /**
     * Configured limit for a team/project scope, falling back to the per-scope
     * default when no explicit limit is set.
     */
    public function getScopeLimit(string $scopeType, int $scopeId): int
    {
        if ($scopeId <= 0 || !$this->isValidScopeType($scopeType)) {
            return $this->getDefaultScopeLimit($scopeType);
        }

        try {
            $stmt = $this->pdo->prepare('SELECT max_tasks FROM crm_wip_scope_limits WHERE scope_type = :t AND scope_id = :id AND is_active = 1');
            $stmt->execute(['t' => $scopeType, 'id' => $scopeId]);
            $value = $stmt->fetchColumn();
            return $value !== false && (int)$value >= 1 ? (int)$value : $this->getDefaultScopeLimit($scopeType);
        } catch (\Throwable $e) {
            error_log('[WipLimitService::getScopeLimit] ' . $e->getMessage());
            return $this->getDefaultScopeLimit($scopeType);
        }
    }

    public function setScopeLimit(string $scopeType, int $scopeId, int $maxTasks): void
    {
        if ($scopeId <= 0 || $maxTasks < 1 || !$this->isValidScopeType($scopeType)) {
            return;
        }

        try {
            $stmt = $this->pdo->prepare('INSERT INTO crm_wip_scope_limits (scope_type, scope_id, max_tasks) VALUES (:t, :id, :max) ON DUPLICATE KEY UPDATE max_tasks = VALUES(max_tasks), updated_at = NOW()');
            $stmt->execute(['t' => $scopeType, 'id' => $scopeId, 'max' => $maxTasks]);
        } catch (\Throwable $e) {
            error_log('[WipLimitService::setScopeLimit] Upsert failed, falling back: ' . $e->getMessage());
            $this->pdo->prepare('DELETE FROM crm_wip_scope_limits WHERE scope_type = :t AND scope_id = :id')->execute(['t' => $scopeType, 'id' => $scopeId]);
            $this->pdo->prepare('INSERT INTO crm_wip_scope_limits (scope_type, scope_id, max_tasks) VALUES (:t, :id, :max)')->execute(['t' => $scopeType, 'id' => $scopeId, 'max' => $maxTasks]);
        }
    }

    public function deleteScopeLimit(string $scopeType, int $scopeId): void
    {
        if ($scopeId <= 0 || !$this->isValidScopeType($scopeType)) {
            return;
        }

        $this->pdo->prepare('DELETE FROM crm_wip_scope_limits WHERE scope_type = :t AND scope_id = :id')->execute(['t' => $scopeType, 'id' => $scopeId]);
    }

    /**
     * Resolve a scope public id (team/project) to its numeric id.
     */
    public function resolveScopeId(string $scopeType, string $scopePublicId): ?int
    {
        if ($scopePublicId === '' || !$this->isValidScopeType($scopeType)) {
            return null;
        }

        try {
            $table = $scopeType === self::SCOPE_TEAM ? 'teams' : 'projects';
            $stmt = $this->pdo->prepare("SELECT id FROM {$table} WHERE public_id = ? LIMIT 1");
            $stmt->execute([$scopePublicId]);
            $id = $stmt->fetchColumn();
            return $id !== false ? (int)$id : null;
        } catch (\Throwable $e) {
            error_log('[WipLimitService::resolveScopeId] ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Resolve the project/team/assignee a task belongs to, plus manager ids, in a
     * single query. Used by the event notification path to evaluate team and
     * project WIP limits without extra round-trips.
     *
     * @return array<string, mixed>|null
     */
    public function resolveTaskScope(string $taskPublicId): ?array
    {
        if ($taskPublicId === '') {
            return null;
        }

        try {
            $stmt = $this->pdo->prepare(
                'SELECT t.id AS task_id, t.assignee_user_id, t.status_code,
                        u.full_name AS assignee_name, u.login AS assignee_login,
                        p.id AS project_id, p.public_id AS project_public_id, p.title AS project_title, p.manager_user_id AS project_manager_id,
                        tm.id AS team_id, tm.public_id AS team_public_id, tm.title AS team_title, tm.manager_user_id AS team_manager_id
                 FROM tasks t
                 LEFT JOIN users u ON u.id = t.assignee_user_id
                 LEFT JOIN projects p ON p.id = t.project_id
                 LEFT JOIN teams tm ON tm.public_id = p.team_public_id
                 WHERE t.public_id = ? AND t.deleted_at IS NULL'
            );
            $stmt->execute([$taskPublicId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($row === false) {
                return null;
            }

            return [
                'task_id' => (int)($row['task_id'] ?? 0),
                'assignee_user_id' => (int)($row['assignee_user_id'] ?? 0),
                'assignee_name' => (string)($row['assignee_name'] ?? ''),
                'assignee_login' => (string)($row['assignee_login'] ?? ''),
                'status_code' => (string)($row['status_code'] ?? ''),
                'project_id' => (int)($row['project_id'] ?? 0),
                'project_public_id' => (string)($row['project_public_id'] ?? ''),
                'project_title' => (string)($row['project_title'] ?? ''),
                'project_manager_id' => (int)($row['project_manager_id'] ?? 0),
                'team_id' => (int)($row['team_id'] ?? 0),
                'team_public_id' => (string)($row['team_public_id'] ?? ''),
                'team_title' => (string)($row['team_title'] ?? ''),
                'team_manager_id' => (int)($row['team_manager_id'] ?? 0),
            ];
        } catch (\Throwable $e) {
            error_log('[WipLimitService::resolveTaskScope] ' . $e->getMessage());
            return null;
        }
    }

    /**
     * All teams or projects with their live WIP count and configured limit.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getScopeSummary(string $scopeType): array
    {
        if (!$this->isValidScopeType($scopeType)) {
            return [];
        }

        try {
            if ($scopeType === self::SCOPE_TEAM) {
                $sql = 'SELECT id, public_id, title, manager_user_id FROM teams ORDER BY title ASC, id ASC';
            } else {
                $sql = 'SELECT id, public_id, title, manager_user_id FROM projects WHERE archived_at IS NULL ORDER BY title ASC, id ASC';
            }

            $stmt = $this->pdo->query($sql);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

            $items = [];
            foreach ($rows as $row) {
                $id = (int)($row['id'] ?? 0);
                $limit = $this->getScopeLimit($scopeType, $id);
                $current = $scopeType === self::SCOPE_TEAM ? $this->getTeamWipCount($id) : $this->getProjectWipCount($id);
                $items[] = [
                    'scope_type' => $scopeType,
                    'scope_id' => $id,
                    'public_id' => (string)($row['public_id'] ?? ''),
                    'title' => (string)($row['title'] ?? ''),
                    'manager_user_id' => (int)($row['manager_user_id'] ?? 0),
                    'limit_value' => $limit,
                    'current_count' => $current,
                    'at_limit' => $current >= $limit ? 1 : 0,
                    'over_limit' => $current > $limit ? 1 : 0,
                ];
            }

            usort($items, static function (array $a, array $b): int {
                if ((int)$a['over_limit'] !== (int)$b['over_limit']) {
                    return (int)$b['over_limit'] <=> (int)$a['over_limit'];
                }
                return (int)$b['current_count'] <=> (int)$a['current_count'];
            });

            return $items;
        } catch (\Throwable $e) {
            error_log('[WipLimitService::getScopeSummary] ' . $e->getMessage());
            return [];
        }
    }

    private function isValidScopeType(string $scopeType): bool
    {
        return $scopeType === self::SCOPE_TEAM || $scopeType === self::SCOPE_PROJECT;
    }

    private function getDefaultScopeLimit(string $scopeType): int
    {
        if ($scopeType === self::SCOPE_TEAM) {
            $limit = (int)($this->config['team_default_limit'] ?? self::DEFAULT_TEAM_LIMIT);
            return $limit >= 1 ? $limit : self::DEFAULT_TEAM_LIMIT;
        }

        $limit = (int)($this->config['project_default_limit'] ?? self::DEFAULT_PROJECT_LIMIT);
        return $limit >= 1 ? $limit : self::DEFAULT_PROJECT_LIMIT;
    }

    /**
     * @return array<int, int>
     */
    private function getExcludedRoleIds(): array
    {
        $ids = $this->config['excluded_role_ids'] ?? [];
        if (!is_array($ids)) {
            $ids = [$ids];
        }

        $result = [];
        foreach ($ids as $id) {
            $id = (int)$id;
            if ($id > 0) {
                $result[] = $id;
            }
        }

        return array_values(array_unique($result));
    }
}
