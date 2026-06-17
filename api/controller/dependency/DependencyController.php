<?php
declare(strict_types=1);

namespace Api\Controller\Dependency;

use Api\Controller\Common\BaseController;
use Api\System\Library\Service\DependencyService;
use Api\System\Library\Validation\Validator;

final class DependencyController extends BaseController
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
            $cacheKey = 'list:' . $this->cacheUserId() . ':' . md5(json_encode($input));
            $items = $cache->remember('dependency', $cacheKey, 60, function () use ($input, $auth) {
                /** @var DependencyService $service */
                $service = $this->container->get('service.dependency');
                return $service->list($input, $auth['user']);
            });
        } else {
            /** @var DependencyService $service */
            $service = $this->container->get('service.dependency');
            $items = $service->list($this->request()->allInput(), $auth['user']);
        }

        return $this->success('DEPENDENCY_LIST', $this->t('dependency/messages.list'), ['items' => $items]);
    }

    public function create(): \Api\System\Library\Http\JsonResponse
    {
        $auth = $this->user();
        if (!$auth) {
            return $this->error('UNAUTHORIZED', $this->t('common/messages.unauthorized'), 401);
        }

        $input = $this->request()->allInput();
        $v = new Validator();
        $v->require($input, 'task_public_id', $this->t('common/messages.field_required'))
            ->require($input, 'depends_on_task_public_id', $this->t('common/messages.field_required'));

        if ($v->fails()) {
            return $this->error('VALIDATION_ERROR', $this->t('common/messages.validation_error'), 422, $v->errors());
        }

        /** @var DependencyService $service */
        $service = $this->container->get('service.dependency');
        $item = $service->create($input, $auth['user']);
        if ($item === 'TASK_NOT_FOUND') {
            return $this->error('TASK_NOT_FOUND', $this->t('dependency/messages.task_not_found'), 404);
        }
        if ($item === 'INVALID_DEPENDENCY_TYPE') {
            return $this->error('INVALID_DEPENDENCY_TYPE', $this->t('dependency/messages.invalid_type'), 422, [
                'dependency_type' => [$this->t('dependency/messages.supported_types')],
            ]);
        }
        if ($item === 'DEPENDENCY_SELF_FORBIDDEN') {
            return $this->error('DEPENDENCY_SELF_FORBIDDEN', $this->t('dependency/messages.self_forbidden'), 422);
        }
        if ($item === 'DEPENDENCY_DIFFERENT_PROJECTS') {
            return $this->error('DEPENDENCY_DIFFERENT_PROJECTS', $this->t('dependency/messages.different_projects'), 422);
        }

        $this->invalidateCache('dependency');

        return $this->success('DEPENDENCY_CREATED', $this->t('dependency/messages.created'), ['dependency' => $item], 201);
    }

    public function delete(array $params): \Api\System\Library\Http\JsonResponse
    {
        $auth = $this->user();
        if (!$auth) {
            return $this->error('UNAUTHORIZED', $this->t('common/messages.unauthorized'), 401);
        }

        /** @var DependencyService $service */
        $service = $this->container->get('service.dependency');
        $ok = $service->delete((string)$params['public_id'], $auth['user']);
        if ($ok === false) {
            return $this->error('DEPENDENCY_NOT_FOUND', $this->t('dependency/messages.not_found'), 404);
        }
        if ($ok === 'TASK_NOT_FOUND') {
            return $this->error('TASK_NOT_FOUND', $this->t('dependency/messages.task_not_found'), 404);
        }

        $this->invalidateCache('dependency');

        return $this->success('DEPENDENCY_DELETED', $this->t('dependency/messages.deleted'));
    }
}
