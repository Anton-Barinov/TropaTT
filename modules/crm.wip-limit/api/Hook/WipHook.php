<?php
declare(strict_types=1);

namespace Module\Crm\WipLimit\Hook;

use Api\System\Library\Container;

final class WipHook
{
    public static function onTaskStatusChanged(Container $container, array $payload): array
    {
        $taskId = (int)($payload['task_id'] ?? 0);
        $newStatus = (string)($payload['new_status'] ?? '');
        $assigneeId = (int)($payload['assignee_id'] ?? 0);

        if ($taskId <= 0 || $assigneeId <= 0) {
            return $payload;
        }

        try {
            $pdo = $container->get('db.pdo');
            $mc = $container->get('module.config');
            $config = $mc->getAll('crm.wip-limit');
            $enforcedStatuses = $config['enforce_on_status'] ?? ['in_progress', 'review'];
            $defaultLimit = (int)($config['default_limit'] ?? 5);

            if (!in_array($newStatus, $enforcedStatuses, true)) {
                static::recalculateWipCount($pdo, $assigneeId);
                return $payload;
            }

            $limit = static::getUserLimit($pdo, $assigneeId, $defaultLimit);
            $current = static::getCurrentWipCount($pdo, $assigneeId);
            $current++;

            static::updateWipCount($pdo, $assigneeId, $current);

            if ($current > $limit) {
                error_log("[WIP-Limit] User {$assigneeId} exceeded WIP limit: {$current}/{$limit}");
            }
        } catch (\Throwable $e) {
            error_log("[WIP-Limit] Hook error: " . $e->getMessage());
        }

        return $payload;
    }

    private static function getUserLimit(\PDO $pdo, int $userId, int $defaultLimit): int
    {
        try {
            $stmt = $pdo->prepare("SELECT max_tasks FROM crm_wip_limits WHERE user_id = :uid AND is_active = 1");
            $stmt->execute(['uid' => $userId]);
            $result = $stmt->fetchColumn();
            return $result !== false ? (int)$result : $defaultLimit;
        } catch (\Throwable) {
            return $defaultLimit;
        }
    }

    private static function getCurrentWipCount(\PDO $pdo, int $userId): int
    {
        try {
            $stmt = $pdo->prepare("SELECT current_count FROM crm_wip_counts WHERE user_id = :uid");
            $stmt->execute(['uid' => $userId]);
            $result = $stmt->fetchColumn();
            return $result !== false ? (int)$result : 0;
        } catch (\Throwable) {
            return 0;
        }
    }

    private static function updateWipCount(\PDO $pdo, int $userId, int $count): void
    {
        try {
            $stmt = $pdo->prepare("INSERT INTO crm_wip_counts (user_id, current_count) VALUES (:uid, :cnt) ON DUPLICATE KEY UPDATE current_count = VALUES(current_count), updated_at = NOW()");
            $stmt->execute(['uid' => $userId, 'cnt' => $count]);
        } catch (\Throwable) {
            $pdo->prepare("DELETE FROM crm_wip_counts WHERE user_id = :uid")->execute(['uid' => $userId]);
            $pdo->prepare("INSERT INTO crm_wip_counts (user_id, current_count) VALUES (:uid, :cnt)")->execute(['uid' => $userId, 'cnt' => $count]);
        }
    }

    private static function recalculateWipCount(\PDO $pdo, int $userId): void
    {
        try {
            $stmt = $pdo->prepare("
                SELECT COUNT(*) FROM tasks 
                WHERE assignee_user_id = :uid 
                AND status_code IN (SELECT code FROM task_statuses WHERE is_active = 1 AND code IN ('in_progress','review'))
                AND deleted_at IS NULL
            ");
            $stmt->execute(['uid' => $userId]);
            $count = (int)$stmt->fetchColumn();
            static::updateWipCount($pdo, $userId, $count);
        } catch (\Throwable) {
        }
    }
}
