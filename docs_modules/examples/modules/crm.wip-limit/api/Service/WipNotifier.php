<?php
declare(strict_types=1);

namespace Module\Crm\WipLimit\Service;

use Api\System\Library\Module\ModuleNotificationDispatcher;
use PDO;

/**
 * Turns a task lifecycle event into one or more WIP over-limit notifications.
 *
 * The core dispatches task.status_changed / task.assignee_changed through the
 * module HookManager; this notifier resolves the task's project, team and
 * assignee, evaluates each scope's live WIP against its limit and notifies the
 * relevant managers (or the assignee) via the core module notification channel.
 */
final class WipNotifier
{
    private const DEDUP_WINDOW_MINUTES = 15;

    public function __construct(
        private readonly WipLimitService $service,
        private readonly ModuleNotificationDispatcher $dispatcher,
        private readonly PDO $pdo,
        private readonly array $config,
        private readonly int $organizationId,
    ) {
    }

    /**
     * @param array<string, mixed> $context Event context from the core dispatcher.
     */
    public function onTaskChanged(array $context, string $event): void
    {
        if (!(bool)($this->config['notify_on_exceed'] ?? true)) {
            return;
        }

        $scope = $this->service->resolveTaskScope((string)($context['task_public_id'] ?? ''));
        if ($scope === null) {
            return;
        }

        // Team and project loads change only when a task enters/leaves a working
        // status, so they are evaluated on status changes.
        if ($event === 'task.status_changed') {
            $this->checkProject($scope);
            $this->checkTeam($scope);
        }

        // A user's personal load changes on status change and on reassignment.
        $this->checkUser($scope);
    }

    /**
     * @param array<string, mixed> $scope
     */
    private function checkProject(array $scope): void
    {
        $projectId = (int)($scope['project_id'] ?? 0);
        if ($projectId <= 0) {
            return;
        }

        $limit = $this->service->getScopeLimit('project', $projectId);
        $current = $this->service->getProjectWipCount($projectId);
        if ($current <= $limit) {
            return;
        }

        $name = (string)($scope['project_title'] ?? '');
        $title = 'WIP limit exceeded: project "' . ($name !== '' ? $name : '#' . $projectId) . '"';
        $body = sprintf('%d task(s) in progress (limit %d).', $current, $limit);

        $this->send($title, $body, [(int)($scope['project_manager_id'] ?? 0)]);
    }

    /**
     * @param array<string, mixed> $scope
     */
    private function checkTeam(array $scope): void
    {
        $teamId = (int)($scope['team_id'] ?? 0);
        if ($teamId <= 0) {
            return;
        }

        $limit = $this->service->getScopeLimit('team', $teamId);
        $current = $this->service->getTeamWipCount($teamId);
        if ($current <= $limit) {
            return;
        }

        $name = (string)($scope['team_title'] ?? '');
        $title = 'WIP limit exceeded: team "' . ($name !== '' ? $name : '#' . $teamId) . '"';
        $body = sprintf('%d task(s) in progress (limit %d).', $current, $limit);

        $this->send($title, $body, [(int)($scope['team_manager_id'] ?? 0)]);
    }

    /**
     * @param array<string, mixed> $scope
     */
    private function checkUser(array $scope): void
    {
        $assigneeId = (int)($scope['assignee_user_id'] ?? 0);
        if ($assigneeId <= 0) {
            return;
        }

        $status = (string)($scope['status_code'] ?? '');
        if ($status === '' || !in_array($status, $this->service->getWipStatusCodes(), true)) {
            return;
        }

        $limit = $this->service->getUserLimit($assigneeId);
        $current = $this->service->getUserWipCount($assigneeId);
        if ($current <= $limit) {
            return;
        }

        $name = (string)($scope['assignee_name'] ?? '');
        $title = 'WIP limit exceeded: ' . ($name !== '' ? $name : ('#' . $assigneeId));
        $body = sprintf('%d task(s) in progress (limit %d).', $current, $limit);

        $this->send($title, $body, [$assigneeId, (int)($scope['project_manager_id'] ?? 0)]);
    }

    /**
     * @param array<int, int> $recipients
     */
    private function send(string $title, string $body, array $recipients): void
    {
        $recipients = $this->filterWorkspaceRecipients($recipients);
        $recipients = $this->dedupe($title, $recipients);
        if ($recipients === []) {
            return;
        }

        $this->dispatcher->notify(
            'crm.wip-limit',
            $recipients,
            $title,
            $body,
            'warning',
            'index.php?route=module-wip-limit'
        );
    }

    /**
     * @param array<int, int> $recipients
     * @return array<int, int>
     */
    private function filterWorkspaceRecipients(array $recipients): array
    {
        if ($this->organizationId <= 0 || $recipients === []) {
            return [];
        }

        $recipients = array_values(array_unique(array_filter(array_map('intval', $recipients), static fn(int $id): bool => $id > 0)));
        if ($recipients === []) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($recipients), '?'));
        try {
            $stmt = $this->pdo->prepare(
                "SELECT user_id FROM organization_memberships WHERE organization_id = ? AND user_id IN ({$placeholders})"
            );
            $stmt->execute(array_merge([$this->organizationId], $recipients));
            return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN) ?: []);
        } catch (\Throwable $e) {
            error_log('[WipNotifier::filterWorkspaceRecipients] ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Drop recipients who already received the same notification within the dedup
     * window, so a persistent overload does not spam managers on every event.
     *
     * @param array<int, int> $recipients
     * @return array<int, int>
     */
    private function dedupe(string $title, array $recipients): array
    {
        if ($recipients === []) {
            return [];
        }

        // The core dispatcher stores created_at via date(), so keep the same clock
        // (server-local) for the dedup window comparison.
        $since = date('Y-m-d H:i:s', time() - self::DEDUP_WINDOW_MINUTES * 60);
        $out = [];

        foreach ($recipients as $userId) {
            $userId = (int)$userId;
            if ($userId <= 0) {
                continue;
            }

            try {
                $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM notifications WHERE user_id = ? AND title = ? AND created_at >= ?');
                $stmt->execute([$userId, $title, $since]);
                if ((int)$stmt->fetchColumn() === 0) {
                    $out[] = $userId;
                }
            } catch (\Throwable $e) {
                error_log('[WipNotifier::dedupe] ' . $e->getMessage());
                $out[] = $userId;
            }
        }

        return array_values(array_unique($out));
    }
}
