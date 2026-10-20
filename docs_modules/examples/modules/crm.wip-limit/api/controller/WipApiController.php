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
        $service = $this->service();
        if ($service instanceof JsonResponse) {
            return $service;
        }
        return JsonResponse::success('WIP_LIMITS_LIST', 'OK', [
            'items' => $service->listLimits(),
        ]);
    }

    public function get(array $params = []): JsonResponse
    {
        $service = $this->service();
        if ($service instanceof JsonResponse) {
            return $service;
        }
        $userId = (int)($params['user_id'] ?? 0);
        if ($userId <= 0) {
            return JsonResponse::error('INVALID_PARAM', 'user_id is required', 400);
        }

        return JsonResponse::success('WIP_LIMIT', 'OK', [
            'limit' => $service->getLimit($userId),
        ]);
    }

    public function getForTask(array $params = []): JsonResponse
    {
        $service = $this->service();
        if ($service instanceof JsonResponse) {
            return $service;
        }
        $taskPublicId = trim((string)($params['task_public_id'] ?? ''));
        if ($taskPublicId === '') {
            return JsonResponse::error('INVALID_PARAM', 'task_public_id is required', 400);
        }

        $wip = $service->getAssigneeWip($taskPublicId);
        if ($wip === null) {
            return JsonResponse::error('TASK_NOT_FOUND', 'Task not found', 404);
        }

        $wip['can_manage'] = $this->actorCanManage();
        return JsonResponse::success('WIP_TASK_ASSIGNEE', 'OK', $wip);
    }

    public function set(array $params = []): JsonResponse
    {
        $service = $this->service();
        if ($service instanceof JsonResponse) {
            return $service;
        }
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

        if (!$service->setLimit($userId, $maxTasks)) {
            return JsonResponse::error('USER_NOT_FOUND', 'User is not a member of this workspace', 404);
        }

        return JsonResponse::success('WIP_LIMIT_SET', 'Limit updated');
    }

    public function delete(array $params = []): JsonResponse
    {
        $service = $this->service();
        if ($service instanceof JsonResponse) {
            return $service;
        }
        $userId = (int)($params['user_id'] ?? 0);
        if ($userId <= 0) {
            return JsonResponse::error('INVALID_PARAM', 'user_id is required', 400);
        }

        if (!$service->deleteLimit($userId)) {
            return JsonResponse::error('USER_NOT_FOUND', 'User is not a member of this workspace', 404);
        }

        return JsonResponse::success('WIP_LIMIT_DELETED', 'Limit removed');
    }

    public function summary(array $params = []): JsonResponse
    {
        $service = $this->service();
        if ($service instanceof JsonResponse) {
            return $service;
        }
        return JsonResponse::success('WIP_SUMMARY', 'OK', [
            'items' => $service->getSummary(),
        ]);
    }

    public function scopes(array $params = []): JsonResponse
    {
        $service = $this->service();
        if ($service instanceof JsonResponse) {
            return $service;
        }
        $scopeType = $this->normalizeScopeType($params);
        if ($scopeType === null) {
            return JsonResponse::error('INVALID_PARAM', 'scope_type must be team or project', 400);
        }

        return JsonResponse::success('WIP_SCOPES', 'OK', [
            'items' => $service->getScopeSummary($scopeType),
        ]);
    }

    public function setScope(array $params = []): JsonResponse
    {
        $service = $this->service();
        if ($service instanceof JsonResponse) {
            return $service;
        }
        $body = $this->readBody();
        if (empty($params['scope_type'])) {
            $params = array_merge($params, $body);
        }

        $scopeType = $this->normalizeScopeType($params);
        $scopePublicId = trim((string)($params['scope_public_id'] ?? $params['scope_id'] ?? ''));
        $maxTasks = (int)($params['max_tasks'] ?? 0);

        if ($scopeType === null) {
            return JsonResponse::error('INVALID_PARAM', 'scope_type must be team or project', 400);
        }
        if ($scopePublicId === '') {
            return JsonResponse::error('INVALID_PARAM', 'scope_public_id is required', 400);
        }
        if ($maxTasks < 1) {
            return JsonResponse::error('INVALID_PARAM', 'max_tasks must be >= 1', 400);
        }

        $scopeId = $service->resolveScopeId($scopeType, $scopePublicId);
        if ($scopeId === null) {
            return JsonResponse::error('SCOPE_NOT_FOUND', 'Scope not found', 404);
        }

        if (!$service->setScopeLimit($scopeType, $scopeId, $maxTasks)) {
            return JsonResponse::error('SCOPE_NOT_FOUND', 'Scope not found', 404);
        }

        return JsonResponse::success('WIP_SCOPE_LIMIT_SET', 'Limit updated');
    }

    public function deleteScope(array $params = []): JsonResponse
    {
        $service = $this->service();
        if ($service instanceof JsonResponse) {
            return $service;
        }
        $scopeType = $this->normalizeScopeType($params);
        $scopePublicId = trim((string)($params['scope_public_id'] ?? ''));

        if ($scopeType === null) {
            return JsonResponse::error('INVALID_PARAM', 'scope_type must be team or project', 400);
        }
        if ($scopePublicId === '') {
            return JsonResponse::error('INVALID_PARAM', 'scope_public_id is required', 400);
        }

        $scopeId = $service->resolveScopeId($scopeType, $scopePublicId);
        if ($scopeId === null) {
            return JsonResponse::error('SCOPE_NOT_FOUND', 'Scope not found', 404);
        }

        $service->deleteScopeLimit($scopeType, $scopeId);

        return JsonResponse::success('WIP_SCOPE_LIMIT_DELETED', 'Limit removed');
    }

    /**
     * @param array<string, mixed> $params
     */
    private function normalizeScopeType(array $params): ?string
    {
        $scopeType = strtolower(trim((string)($params['scope_type'] ?? '')));
        return in_array($scopeType, ['team', 'project'], true) ? $scopeType : null;
    }

    private function service(): WipLimitService|JsonResponse
    {
        $auth = $this->container->has('auth_user') ? $this->container->get('auth_user') : null;
        $actor = is_array($auth) && is_array($auth['user'] ?? null) ? $auth['user'] : [];
        $organizationId = (int)($actor['organization_id'] ?? 0);
        if ($organizationId <= 0) {
            return JsonResponse::error('ORGANIZATION_REQUIRED', 'Active workspace is required', 403);
        }

        $moduleConfig = $this->container->get('module.config');
        return new WipLimitService(
            $this->container->get('db.pdo'),
            $moduleConfig->getAll('crm.wip-limit'),
            $organizationId,
        );
    }

    private function actorCanManage(): bool
    {
        $auth = $this->container->has('auth_user') ? $this->container->get('auth_user') : null;
        if (!is_array($auth) || !is_array($auth['user'] ?? null) || !$this->container->has('service.authz')) {
            return false;
        }

        return $this->container->get('service.authz')->hasAnyPermissions(
            $auth['user'],
            ['module.wip-limit.manage']
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
