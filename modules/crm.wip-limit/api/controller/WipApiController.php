<?php
declare(strict_types=1);

namespace Module\Crm\WipLimit\Controller;

use Api\System\Library\Container;
use Api\System\Library\Http\JsonResponse;
use PDO;

final class WipApiController
{
    public function __construct(private readonly Container $container) {}

    public function list(array $params = []): JsonResponse
    {
        $pdo = $this->container->get('db.pdo');
        try {
            $stmt = $pdo->query("
                SELECT l.id, l.user_id, l.max_tasks, l.is_active, l.created_at,
                       u.full_name, u.login,
                       COALESCE(c.current_count, 0) as current_count
                FROM crm_wip_limits l
                LEFT JOIN users u ON u.id = l.user_id
                LEFT JOIN crm_wip_counts c ON c.user_id = l.user_id
                ORDER BY l.user_id
            ");
            $items = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
            return JsonResponse::success('WIP_LIMITS_LIST', 'OK', ['items' => $items]);
        } catch (\Throwable $e) {
            return JsonResponse::success('WIP_LIMITS_LIST', 'OK', ['items' => []]);
        }
    }

    public function get(array $params = []): JsonResponse
    {
        $userId = (int)($params['user_id'] ?? 0);
        if ($userId <= 0) {
            return JsonResponse::error('INVALID_PARAM', 'user_id is required', 400);
        }

        $pdo = $this->container->get('db.pdo');
        $stmt = $pdo->prepare("SELECT l.*, COALESCE(c.current_count, 0) as current_count FROM crm_wip_limits l LEFT JOIN crm_wip_counts c ON c.user_id = l.user_id WHERE l.user_id = :uid");
        $stmt->execute(['uid' => $userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return JsonResponse::success('WIP_LIMIT', 'OK', ['limit' => $row ?: null]);
    }

    public function set(array $params = []): JsonResponse
    {
        if (empty($params['user_id'])) {
            $req = $this->container->get('request');
            $raw = $req->rawBody;
            if ($raw !== '') {
                $decoded = json_decode($raw, true);
                if (is_array($decoded)) {
                    $params = array_merge($params, $decoded);
                }
            }
        }
        $userId = (int)($params['user_id'] ?? 0);
        $maxTasks = (int)($params['max_tasks'] ?? 0);
        if ($userId <= 0) {
            return JsonResponse::error('INVALID_PARAM', 'user_id is required', 400);
        }
        if ($maxTasks < 1) {
            return JsonResponse::error('INVALID_PARAM', 'max_tasks must be >= 1', 400);
        }

        $pdo = $this->container->get('db.pdo');
        try {
            $stmt = $pdo->prepare("INSERT INTO crm_wip_limits (user_id, max_tasks) VALUES (:uid, :max) ON DUPLICATE KEY UPDATE max_tasks = VALUES(max_tasks), updated_at = NOW()");
            $stmt->execute(['uid' => $userId, 'max' => $maxTasks]);
        } catch (\Throwable) {
            $pdo->prepare("DELETE FROM crm_wip_limits WHERE user_id = :uid")->execute(['uid' => $userId]);
            $pdo->prepare("INSERT INTO crm_wip_limits (user_id, max_tasks) VALUES (:uid, :max)")->execute(['uid' => $userId, 'max' => $maxTasks]);
        }

        return JsonResponse::success('WIP_LIMIT_SET', 'Limit updated');
    }

    public function delete(array $params = []): JsonResponse
    {
        $userId = (int)($params['user_id'] ?? 0);
        if ($userId <= 0) {
            return JsonResponse::error('INVALID_PARAM', 'user_id is required', 400);
        }

        $pdo = $this->container->get('db.pdo');
        $pdo->prepare("DELETE FROM crm_wip_limits WHERE user_id = :uid")->execute(['uid' => $userId]);

        return JsonResponse::success('WIP_LIMIT_DELETED', 'Limit removed');
    }

    public function summary(array $params = []): JsonResponse
    {
        $pdo = $this->container->get('db.pdo');
        try {
            $stmt = $pdo->query("
                SELECT u.id as user_id, u.full_name, u.login,
                       COALESCE(l.max_tasks, 5) as limit_value,
                       COALESCE(c.current_count, 0) as current_count,
                       CASE WHEN COALESCE(c.current_count, 0) >= COALESCE(l.max_tasks, 5) THEN 1 ELSE 0 END as at_limit
                FROM users u
                LEFT JOIN crm_wip_limits l ON l.user_id = u.id AND l.is_active = 1
                LEFT JOIN crm_wip_counts c ON c.user_id = u.id
                WHERE u.deleted_at IS NULL AND u.is_active = 1
                ORDER BY at_limit DESC, current_count DESC
                LIMIT 50
            ");
            $items = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
            return JsonResponse::success('WIP_SUMMARY', 'OK', ['items' => $items]);
        } catch (\Throwable $e) {
            return JsonResponse::success('WIP_SUMMARY', 'OK', ['items' => []]);
        }
    }
}
