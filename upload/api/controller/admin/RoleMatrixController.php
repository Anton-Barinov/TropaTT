<?php
declare(strict_types=1);

namespace Api\Controller\Admin;

use Api\Controller\Common\BaseController;
use Api\System\Library\Service\AdminRoleMatrixService;

final class RoleMatrixController extends BaseController
{
    public function get(): \Api\System\Library\Http\JsonResponse
    {
        /** @var AdminRoleMatrixService $service */
        $service = $this->container->get('service.admin_role_matrix');

        return $this->success('ADMIN_ROLE_MATRIX', $this->t('admin/messages.role_matrix'), $service->getMatrix());
    }

    public function update(): \Api\System\Library\Http\JsonResponse
    {
        $auth = $this->user();
        if (!$auth) {
            return $this->error('UNAUTHORIZED', $this->t('common/messages.unauthorized'), 401);
        }

        $roles = $this->request()->input('roles', []);
        if (!is_array($roles)) {
            return $this->error('VALIDATION_ERROR', $this->t('common/messages.validation_error'), 422, [
                'roles' => [$this->t('admin/messages.roles_array_expected')],
            ]);
        }

        /** @var AdminRoleMatrixService $service */
        $service = $this->container->get('service.admin_role_matrix');
        $result = $service->setMatrix($roles, $auth['user']);
        if (!(bool)($result['ok'] ?? false)) {
            $code = (string)($result['code'] ?? 'ROLE_MATRIX_UPDATE_FAILED');
            $status = in_array($code, ['FORBIDDEN'], true) ? 403 : 422;

            return $this->error($code, $this->t('admin/messages.role_matrix_update_failed'), $status, [
                'field' => [(string)($result['field'] ?? 'roles')],
            ]);
        }

        $this->invalidateCache('permission');
        $this->invalidateCache('role');
        $this->invalidateCache('user');

        return $this->success('ADMIN_ROLE_MATRIX_UPDATED', $this->t('admin/messages.role_matrix_updated'), [
            'updated' => $result['updated'] ?? [],
            'matrix' => $result['matrix'] ?? [],
        ]);
    }
}
