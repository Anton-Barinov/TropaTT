<?php
declare(strict_types=1);

namespace Api\Controller\Priority;

use Api\Controller\Common\BaseController;
use Api\System\Library\Service\PriorityService;
use Api\System\Library\Validation\Validator;

final class PriorityController extends BaseController
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
            $result = $cache->remember('priority', $cacheKey, 60, function () use ($input) {
                /** @var PriorityService $service */
                $service = $this->container->get('service.priority');
                return $service->list($input);
            });
        } else {
            /** @var PriorityService $service */
            $service = $this->container->get('service.priority');
            $result = $service->list($this->request()->allInput());
        }

        return $this->success('PRIORITY_LIST', $this->t('priority/messages.list'), ['items' => $result['items']], meta: $result['meta']);
    }

    public function get(array $params): \Api\System\Library\Http\JsonResponse
    {
        /** @var PriorityService $service */
        $service = $this->container->get('service.priority');
        $item = $service->get((string)$params['public_id']);
        if (!$item) {
            return $this->error('PRIORITY_NOT_FOUND', $this->t('priority/messages.not_found'), 404, [
                'priority' => [$this->t('priority/messages.not_found')],
            ]);
        }

        return $this->success('PRIORITY_DETAIL', $this->t('priority/messages.detail'), ['priority' => $item]);
    }

    public function create(): \Api\System\Library\Http\JsonResponse
    {
        $input = $this->request()->allInput();
        $v = new Validator();
        $v->require($input, 'code', $this->t('common/messages.field_required'))
            ->require($input, 'title', $this->t('common/messages.field_required'))
            ->maxLen($input, 'code', 64, $this->t('priority/messages.max_64'))
            ->maxLen($input, 'title', 255, $this->t('priority/messages.max_255'));

        if ($v->fails()) {
            return $this->error('VALIDATION_ERROR', $this->t('common/messages.validation_error'), 422, $v->errors());
        }

        /** @var PriorityService $service */
        $service = $this->container->get('service.priority');
        $item = $service->create($input);
        if (is_string($item) && $item === 'PRIORITY_CODE_EXISTS') {
            return $this->error('PRIORITY_CODE_EXISTS', $this->t('priority/messages.code_exists'), 409, [
                'code' => [$this->t('priority/messages.code_exists')],
            ]);
        }

        $this->invalidateCache('priority');

        return $this->success('PRIORITY_CREATED', $this->t('priority/messages.created'), ['priority' => $item], 201);
    }

    public function update(array $params): \Api\System\Library\Http\JsonResponse
    {
        $input = $this->request()->allInput();
        $v = new Validator();
        $v->maxLen($input, 'code', 64, $this->t('priority/messages.max_64'))
            ->maxLen($input, 'title', 255, $this->t('priority/messages.max_255'));

        if ($v->fails()) {
            return $this->error('VALIDATION_ERROR', $this->t('common/messages.validation_error'), 422, $v->errors());
        }

        /** @var PriorityService $service */
        $service = $this->container->get('service.priority');
        $item = $service->update((string)$params['public_id'], $input);
        if ($item === null) {
            return $this->error('PRIORITY_NOT_FOUND', $this->t('priority/messages.not_found'), 404, [
                'priority' => [$this->t('priority/messages.not_found')],
            ]);
        }
        if (is_string($item) && $item === 'PRIORITY_CODE_EXISTS') {
            return $this->error('PRIORITY_CODE_EXISTS', $this->t('priority/messages.code_exists'), 409, [
                'code' => [$this->t('priority/messages.code_exists')],
            ]);
        }

        $this->invalidateCache('priority');

        return $this->success('PRIORITY_UPDATED', $this->t('priority/messages.updated'), ['priority' => $item]);
    }

    public function delete(array $params): \Api\System\Library\Http\JsonResponse
    {
        /** @var PriorityService $service */
        $service = $this->container->get('service.priority');
        $ok = $service->delete((string)$params['public_id']);
        if (!$ok) {
            return $this->error('PRIORITY_NOT_FOUND', $this->t('priority/messages.not_found'), 404, [
                'priority' => [$this->t('priority/messages.not_found')],
            ]);
        }

        $this->invalidateCache('priority');

        return $this->success('PRIORITY_DELETED', $this->t('priority/messages.deleted'));
    }
}
