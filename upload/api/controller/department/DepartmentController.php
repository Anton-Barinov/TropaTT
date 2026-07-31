<?php
declare(strict_types=1);

namespace Api\Controller\Department;

use Api\Controller\Common\BaseController;
use Api\System\Library\Service\DepartmentService;
use Api\System\Library\Validation\Validator;

final class DepartmentController extends BaseController
{
    public function list(): \Api\System\Library\Http\JsonResponse
    {
        $auth = $this->user();
        if (!$auth) {
            return $this->error('UNAUTHORIZED', $this->t('common/messages.unauthorized'), 401);
        }

        /** @var DepartmentService $service */
        $service = $this->container->get('service.department');
        $result = $service->list($this->request()->allInput(), $auth['user']);

        return $this->success('DEPARTMENT_LIST', $this->t('department/messages.list'), [
            'items' => $result['items'],
        ], meta: $result['meta']);
    }

    public function create(): \Api\System\Library\Http\JsonResponse
    {
        $auth = $this->user();
        if (!$auth) {
            return $this->error('UNAUTHORIZED', $this->t('common/messages.unauthorized'), 401);
        }

        $input = $this->request()->allInput();
        $v = new Validator();
        $v->require($input, 'title', $this->t('common/messages.field_required'))
            ->maxLen($input, 'title', 255, $this->t('department/messages.max_255'));

        if ($v->fails()) {
            return $this->error('VALIDATION_ERROR', $this->t('common/messages.validation_error'), 422, $v->errors());
        }

        /** @var DepartmentService $service */
        $service = $this->container->get('service.department');
        $item = $service->create($input, $auth['user']);

        return $this->success('DEPARTMENT_CREATED', $this->t('department/messages.created'), ['department' => $item], 201);
    }

    public function get(array $params): \Api\System\Library\Http\JsonResponse
    {
        $auth = $this->user();
        if (!$auth) {
            return $this->error('UNAUTHORIZED', $this->t('common/messages.unauthorized'), 401);
        }

        /** @var DepartmentService $service */
        $service = $this->container->get('service.department');
        $item = $service->get((string)$params['public_id'], $auth['user']);

        if (!$item) {
            return $this->error('DEPARTMENT_NOT_FOUND', $this->t('department/messages.not_found'), 404, [
                'department' => [$this->t('department/messages.not_found')],
            ]);
        }

        return $this->success('DEPARTMENT_DETAIL', $this->t('department/messages.detail'), ['department' => $item]);
    }

    public function update(array $params): \Api\System\Library\Http\JsonResponse
    {
        $auth = $this->user();
        if (!$auth) {
            return $this->error('UNAUTHORIZED', $this->t('common/messages.unauthorized'), 401);
        }

        /** @var DepartmentService $service */
        $service = $this->container->get('service.department');
        $item = $service->update((string)$params['public_id'], $this->request()->allInput(), $auth['user']);

        if (!$item) {
            return $this->error('DEPARTMENT_NOT_FOUND', $this->t('department/messages.not_found'), 404, [
                'department' => [$this->t('department/messages.not_found')],
            ]);
        }

        return $this->success('DEPARTMENT_UPDATED', $this->t('department/messages.updated'), ['department' => $item]);
    }

    public function delete(array $params): \Api\System\Library\Http\JsonResponse
    {
        $auth = $this->user();
        if (!$auth) {
            return $this->error('UNAUTHORIZED', $this->t('common/messages.unauthorized'), 401);
        }

        /** @var DepartmentService $service */
        $service = $this->container->get('service.department');
        $ok = $service->delete((string)$params['public_id'], $auth['user']);

        if (!$ok) {
            return $this->error('DEPARTMENT_NOT_FOUND', $this->t('department/messages.not_found'), 404, [
                'department' => [$this->t('department/messages.not_found')],
            ]);
        }

        return $this->success('DEPARTMENT_DELETED', $this->t('department/messages.deleted'));
    }
}
