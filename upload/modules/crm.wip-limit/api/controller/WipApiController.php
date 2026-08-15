<?php
declare(strict_types=1);

namespace Module\Crm\WipLimit\Controller;

use Api\System\Library\Container;
use Api\System\Library\Http\JsonResponse;
use Module\Crm\WipLimit\Service\WipLimitService;

final class WipApiController
{
    public function __construct(private readonly Container $container) {}

    public function list(array $params = []): JsonResponse
    {
        return JsonResponse::success('WIP_LIMITS_LIST', 'OK', [
            'items' => $this->service()->listLimits(),
        ]);
    }

    public function get(array $params = []): JsonResponse
    {
        $userId = (int)($params['user_id'] ?? 0);
        if ($userId <= 0) {
            return JsonResponse::error('INVALID_PARAM', 'user_id is required', 400);
        }

        return JsonResponse::success('WIP_LIMIT', 'OK', [
            'limit' => $this->service()->getLimit($userId),
        ]);
    }

    public function set(array $params = []): JsonResponse
    {
        $body = $this->readBody();
        if (empty($params['user_id'])) {
            $params = array_merge($params, $body);
        }

        $userId = (int)($params['user_id'] ?? 0);
        $maxTasks = (int)($params['max_tasks'] ?? 0);

        if ($userId <= 0) {
            return JsonResponse::error('INVALID_PARAM', 'user_id is required', 400);
        }
        if ($maxTasks < 1) {
            return JsonResponse::error('INVALID_PARAM', 'max_tasks must be >= 1', 400);
        }

        $this->service()->setLimit($userId, $maxTasks);

        return JsonResponse::success('WIP_LIMIT_SET', 'Limit updated');
    }

    public function delete(array $params = []): JsonResponse
    {
        $userId = (int)($params['user_id'] ?? 0);
        if ($userId <= 0) {
            return JsonResponse::error('INVALID_PARAM', 'user_id is required', 400);
        }

        $this->service()->deleteLimit($userId);

        return JsonResponse::success('WIP_LIMIT_DELETED', 'Limit removed');
    }

    public function summary(array $params = []): JsonResponse
    {
        return JsonResponse::success('WIP_SUMMARY', 'OK', [
            'items' => $this->service()->getSummary(),
        ]);
    }

    private function service(): WipLimitService
    {
        $moduleConfig = $this->container->get('module.config');
        return new WipLimitService(
            $this->container->get('db.pdo'),
            $moduleConfig->getAll('crm.wip-limit'),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function readBody(): array
    {
        try {
            $request = $this->container->get('request');
            $raw = $request->rawBody;
            if ($raw === '' || !is_string($raw)) {
                return [];
            }

            $decoded = json_decode($raw, true);
            return is_array($decoded) ? $decoded : [];
        } catch (\Throwable $e) {
            return [];
        }
    }
}
