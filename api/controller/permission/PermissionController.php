<?php
declare(strict_types=1);

namespace Api\Controller\Permission;

use Api\Controller\Common\BaseController;
use Api\System\Library\Service\PermissionService;

final class PermissionController extends BaseController
{
    public function list(): \Api\System\Library\Http\JsonResponse
    {
        $cache = $this->cacheApi();
        if ($cache !== null) {
            $result = $cache->remember('permission', 'list', 60, function () {
                /** @var PermissionService $service */
                $service = $this->container->get('service.permission');
                return $service->list();
            });
        } else {
            /** @var PermissionService $service */
            $service = $this->container->get('service.permission');
            $result = $service->list();
        }

        return $this->success('PERMISSION_LIST', $this->t('permission/messages.list'), $result);
    }

    public function listByRole(array $params): \Api\System\Library\Http\JsonResponse
    {
        $cache = $this->cacheApi();
        if ($cache !== null) {
            $cacheKey = 'role:' . $params['public_id'];
            $result = $cache->remember('permission', $cacheKey, 60, function () use ($params) {
                /** @var PermissionService $service */
                $service = $this->container->get('service.permission');
                return $service->listByRole((string)$params['public_id']);
            });
        } else {
            /** @var PermissionService $service */
            $service = $this->container->get('service.permission');
            $result = $service->listByRole((string)$params['public_id']);
        }

        if (!(bool)($result['ok'] ?? false)) {
            return $this->error((string)$result['code'], $this->t('permission/messages.role_not_found'), 404, [
                'role' => [(string)$result['code']],
            ]);
        }

        return $this->success('ROLE_PERMISSION_LIST', $this->t('permission/messages.role_permission_list'), [
            'role' => $result['role'],
            'permissions' => $result['permissions'],
        ]);
    }

    public function setByRole(array $params): \Api\System\Library\Http\JsonResponse
    {
        $auth = $this->user();
        if (!$auth) {
            return $this->error('UNAUTHORIZED', $this->t('common/messages.unauthorized'), 401);
        }

        $permissionCodes = $this->request()->input('permission_codes', []);
        if (!is_array($permissionCodes)) {
            return $this->error('VALIDATION_ERROR', $this->t('common/messages.validation_error'), 422, [
                'permission_codes' => [$this->t('permission/messages.permission_codes_array')],
            ]);
        }

        /** @var PermissionService $service */
        $service = $this->container->get('service.permission');
        $result = $service->setByRole((string)$params['public_id'], $permissionCodes, $auth['user']);

        if (!(bool)($result['ok'] ?? false)) {
            $status = (string)($result['code'] ?? '') === 'ROLE_NOT_FOUND' ? 404 : 403;
            return $this->error((string)$result['code'], $this->t('permission/messages.role_permission_update_failed'), $status, [
                'permissions' => [(string)$result['code']],
            ]);
        }

        $this->invalidateCache('permission');

        return $this->success('ROLE_PERMISSION_UPDATED', $this->t('permission/messages.role_permission_updated'), [
            'role' => $result['role'],
            'permissions' => $result['permissions'],
        ]);
    }
}
