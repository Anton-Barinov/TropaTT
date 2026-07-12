<?php
declare(strict_types=1);

namespace Api\Controller\Role;

use Api\Controller\Common\BaseController;
use Api\System\Library\Service\RoleService;
use Api\System\Library\Validation\Validator;

final class RoleController extends BaseController
{
    public function list(): \Api\System\Library\Http\JsonResponse
    {
        $auth = $this->user();
        if (!$auth) {
            return $this->error('UNAUTHORIZED', $this->t('common/messages.unauthorized'), 401);
        }

        $cache = $this->cacheApi();
        if ($cache !== null) {
            $input = $this->request()->allInput();
            ksort($input);
            $cacheKey = 'list:' . $this->cacheUserId() . ':' . hash('sha256', json_encode($input));
            $result = $cache->remember('role', $cacheKey, 60, function () use ($input) {
                /** @var RoleService $service */
                $service = $this->container->get('service.role');
                return $service->list($input);
            });
        } else {
            /** @var RoleService $service */
            $service = $this->container->get('service.role');
            $result = $service->list($this->request()->allInput());
        }

        return $this->success('ROLE_LIST', $this->t('role/messages.list'), $result);
    }

    public function create(): \Api\System\Library\Http\JsonResponse
    {
        $auth = $this->user();
        if (!$auth) {
            return $this->error('UNAUTHORIZED', $this->t('common/messages.unauthorized'), 401);
        }

        $input = $this->request()->allInput();
        $v = new Validator();
        $v->require($input, 'code', $this->t('common/messages.field_required'))
            ->require($input, 'title', $this->t('common/messages.field_required'));

        if ($v->fails()) {
            return $this->error('VALIDATION_ERROR', $this->t('common/messages.validation_error'), 422, $v->errors());
        }

        /** @var RoleService $service */
        $service = $this->container->get('service.role');
        $result = $service->create($input, $auth['user']);

        if (!$result['ok']) {
            return $this->error((string)$result['code'], $this->t('role/messages.create_failed'), 403, ['role' => [(string)$result['code']]]);
        }

        $this->invalidateCache('role');

        return $this->success('ROLE_CREATED', $this->t('role/messages.created'), ['role' => $result['role']], 201);
    }

    public function update(array $params): \Api\System\Library\Http\JsonResponse
    {
        $auth = $this->user();
        if (!$auth) {
            return $this->error('UNAUTHORIZED', $this->t('common/messages.unauthorized'), 401);
        }

        $input = $this->validatedInput(['title', 'code']);

        /** @var RoleService $service */
        $service = $this->container->get('service.role');
        $result = $service->update((string)$params['public_id'], $input, $auth['user']);

        if (!$result['ok']) {
            $status = in_array((string)$result['code'], ['ROLE_NOT_FOUND'], true) ? 404 : 403;
            return $this->error((string)$result['code'], $this->t('role/messages.update_failed'), $status, ['role' => [(string)$result['code']]]);
        }

        $this->invalidateCache('role');

        return $this->success('ROLE_UPDATED', $this->t('role/messages.updated'), ['role' => $result['role']]);
    }

    public function delete(array $params): \Api\System\Library\Http\JsonResponse
    {
        $auth = $this->user();
        if (!$auth) {
            return $this->error('UNAUTHORIZED', $this->t('common/messages.unauthorized'), 401);
        }

        /** @var RoleService $service */
        $service = $this->container->get('service.role');
        $result = $service->delete((string)$params['public_id'], $auth['user']);

        if (!$result['ok']) {
            $status = in_array((string)$result['code'], ['ROLE_NOT_FOUND'], true) ? 404 : 403;
            return $this->error((string)$result['code'], $this->t('role/messages.delete_failed'), $status, ['role' => [(string)$result['code']]]);
        }

        $this->invalidateCache('role');

        return $this->success('ROLE_DELETED', $this->t('role/messages.deleted'));
    }
}
