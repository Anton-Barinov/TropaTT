<?php
declare(strict_types=1);

namespace Api\Controller\Subtask;

use Api\Controller\Common\BaseController;
use Api\System\Library\Service\SubtaskService;
use Api\System\Library\Validation\Validator;

final class SubtaskController extends BaseController
{
    public function listByTask(array $params): \Api\System\Library\Http\JsonResponse
    {
        $auth = $this->user();
        if (!$auth) {
            return $this->error('UNAUTHORIZED', $this->t('common/messages.unauthorized'), 401);
        }

        /** @var SubtaskService $service */
        $service = $this->container->get('service.subtask');
        $items = $service->listByTask((string)$params['public_id'], $auth['user']);
        if ($items === null) {
            return $this->error('TASK_NOT_FOUND', $this->t('subtask/messages.task_not_found'), 404);
        }

        return $this->success('SUBTASK_LIST', $this->t('subtask/messages.list'), ['items' => $items]);
    }

    public function createByTask(array $params): \Api\System\Library\Http\JsonResponse
    {
        $auth = $this->user();
        if (!$auth) {
            return $this->error('UNAUTHORIZED', $this->t('common/messages.unauthorized'), 401);
        }

        $input = $this->request()->allInput();
        $v = new Validator();
        $v->require($input, 'title', $this->t('common/messages.field_required'))
            ->maxLen($input, 'title', 255, $this->t('subtask/messages.max_255'))
            ->maxLen($input, 'description', 8000, $this->t('subtask/messages.max_8000'))
            ->enum($input, 'status', ['new', 'in_progress', 'blocked', 'done'], $this->t('subtask/messages.invalid_status'))
            ->enum($input, 'priority', ['low', 'normal', 'high', 'urgent'], $this->t('subtask/messages.invalid_priority'))
            ->date($input, 'due_at', $this->t('subtask/messages.invalid_due_at'));

        if ($v->fails()) {
            return $this->error('VALIDATION_ERROR', $this->t('common/messages.validation_error'), 422, $v->errors());
        }

        /** @var SubtaskService $service */
        $service = $this->container->get('service.subtask');
        $item = $service->create((string)$params['public_id'], $input, $auth['user']);
        if (!$item) {
            return $this->error('TASK_NOT_FOUND', $this->t('subtask/messages.task_not_found'), 404);
        }

        return $this->success('SUBTASK_CREATED', $this->t('subtask/messages.created'), ['subtask' => $item], 201);
    }

    public function get(array $params): \Api\System\Library\Http\JsonResponse
    {
        $auth = $this->user();
        if (!$auth) {
            return $this->error('UNAUTHORIZED', $this->t('common/messages.unauthorized'), 401);
        }

        /** @var SubtaskService $service */
        $service = $this->container->get('service.subtask');
        $item = $service->get((string)$params['public_id'], $auth['user']);
        if (!$item) {
            return $this->error('SUBTASK_NOT_FOUND', $this->t('subtask/messages.not_found'), 404);
        }

        return $this->success('SUBTASK_DETAIL', $this->t('subtask/messages.detail'), ['subtask' => $item]);
    }

    public function update(array $params): \Api\System\Library\Http\JsonResponse
    {
        $auth = $this->user();
        if (!$auth) {
            return $this->error('UNAUTHORIZED', $this->t('common/messages.unauthorized'), 401);
        }

        $input = $this->request()->allInput();
        $v = new Validator();
        $v->maxLen($input, 'title', 255, $this->t('subtask/messages.max_255'))
            ->maxLen($input, 'description', 8000, $this->t('subtask/messages.max_8000'))
            ->enum($input, 'status', ['new', 'in_progress', 'blocked', 'done'], $this->t('subtask/messages.invalid_status'))
            ->enum($input, 'priority', ['low', 'normal', 'high', 'urgent'], $this->t('subtask/messages.invalid_priority'))
            ->date($input, 'due_at', $this->t('subtask/messages.invalid_due_at'));

        if ($v->fails()) {
            return $this->error('VALIDATION_ERROR', $this->t('common/messages.validation_error'), 422, $v->errors());
        }

        /** @var SubtaskService $service */
        $service = $this->container->get('service.subtask');
        $item = $service->update((string)$params['public_id'], $input, $auth['user']);
        if (!$item) {
            return $this->error('SUBTASK_NOT_FOUND', $this->t('subtask/messages.not_found'), 404);
        }

        return $this->success('SUBTASK_UPDATED', $this->t('subtask/messages.updated'), ['subtask' => $item]);
    }

    public function delete(array $params): \Api\System\Library\Http\JsonResponse
    {
        $auth = $this->user();
        if (!$auth) {
            return $this->error('UNAUTHORIZED', $this->t('common/messages.unauthorized'), 401);
        }

        /** @var SubtaskService $service */
        $service = $this->container->get('service.subtask');
        $ok = $service->delete((string)$params['public_id'], $auth['user']);
        if (!$ok) {
            return $this->error('SUBTASK_NOT_FOUND', $this->t('subtask/messages.not_found'), 404);
        }

        return $this->success('SUBTASK_DELETED', $this->t('subtask/messages.deleted'));
    }
}
