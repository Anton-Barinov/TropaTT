<?php
declare(strict_types=1);

namespace Api\Controller\Checklist;

use Api\Controller\Common\BaseController;
use Api\System\Library\Service\ChecklistService;
use Api\System\Library\Validation\Validator;

final class ChecklistController extends BaseController
{
    public function listByTask(array $params): \Api\System\Library\Http\JsonResponse
    {
        $auth = $this->user();
        if (!$auth) {
            return $this->error('UNAUTHORIZED', $this->t('common/messages.unauthorized'), 401);
        }

        /** @var ChecklistService $service */
        $service = $this->container->get('service.checklist');
        $items = $service->listByTask((string)$params['public_id'], $auth['user']);
        if ($items === null) {
            return $this->error('TASK_NOT_FOUND', $this->t('checklist/messages.task_not_found'), 404);
        }

        return $this->success('CHECKLIST_LIST', $this->t('checklist/messages.list'), ['items' => $items]);
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
            ->maxLen($input, 'title', 255, $this->t('checklist/messages.max_255'));

        if ($v->fails()) {
            return $this->error('VALIDATION_ERROR', $this->t('common/messages.validation_error'), 422, $v->errors());
        }

        /** @var ChecklistService $service */
        $service = $this->container->get('service.checklist');
        $item = $service->create((string)$params['public_id'], $input, $auth['user']);
        if (!$item) {
            return $this->error('TASK_NOT_FOUND', $this->t('checklist/messages.task_not_found'), 404);
        }

        return $this->success('CHECKLIST_CREATED', $this->t('checklist/messages.created'), ['checklist' => $item], 201);
    }

    public function get(array $params): \Api\System\Library\Http\JsonResponse
    {
        $auth = $this->user();
        if (!$auth) {
            return $this->error('UNAUTHORIZED', $this->t('common/messages.unauthorized'), 401);
        }

        /** @var ChecklistService $service */
        $service = $this->container->get('service.checklist');
        $item = $service->get((string)$params['public_id'], $auth['user']);
        if (!$item) {
            return $this->error('CHECKLIST_NOT_FOUND', $this->t('checklist/messages.not_found'), 404);
        }

        return $this->success('CHECKLIST_DETAIL', $this->t('checklist/messages.detail'), ['checklist' => $item]);
    }

    public function update(array $params): \Api\System\Library\Http\JsonResponse
    {
        $auth = $this->user();
        if (!$auth) {
            return $this->error('UNAUTHORIZED', $this->t('common/messages.unauthorized'), 401);
        }

        $input = $this->request()->allInput();
        $v = new Validator();
        $v->maxLen($input, 'title', 255, $this->t('checklist/messages.max_255'));

        if ($v->fails()) {
            return $this->error('VALIDATION_ERROR', $this->t('common/messages.validation_error'), 422, $v->errors());
        }

        /** @var ChecklistService $service */
        $service = $this->container->get('service.checklist');
        $item = $service->update((string)$params['public_id'], $input, $auth['user']);
        if (!$item) {
            return $this->error('CHECKLIST_NOT_FOUND', $this->t('checklist/messages.not_found'), 404);
        }

        return $this->success('CHECKLIST_UPDATED', $this->t('checklist/messages.updated'), ['checklist' => $item]);
    }

    public function delete(array $params): \Api\System\Library\Http\JsonResponse
    {
        $auth = $this->user();
        if (!$auth) {
            return $this->error('UNAUTHORIZED', $this->t('common/messages.unauthorized'), 401);
        }

        /** @var ChecklistService $service */
        $service = $this->container->get('service.checklist');
        $ok = $service->delete((string)$params['public_id'], $auth['user']);
        if (!$ok) {
            return $this->error('CHECKLIST_NOT_FOUND', $this->t('checklist/messages.not_found'), 404);
        }

        return $this->success('CHECKLIST_DELETED', $this->t('checklist/messages.deleted'));
    }

    public function listItems(array $params): \Api\System\Library\Http\JsonResponse
    {
        $auth = $this->user();
        if (!$auth) {
            return $this->error('UNAUTHORIZED', $this->t('common/messages.unauthorized'), 401);
        }

        /** @var ChecklistService $service */
        $service = $this->container->get('service.checklist');
        $items = $service->listItems((string)$params['public_id'], $auth['user']);
        if ($items === null) {
            return $this->error('CHECKLIST_NOT_FOUND', $this->t('checklist/messages.not_found'), 404);
        }

        return $this->success('CHECKLIST_ITEM_LIST', $this->t('checklist/messages.item_list'), ['items' => $items]);
    }

    public function createItem(array $params): \Api\System\Library\Http\JsonResponse
    {
        $auth = $this->user();
        if (!$auth) {
            return $this->error('UNAUTHORIZED', $this->t('common/messages.unauthorized'), 401);
        }

        $input = $this->request()->allInput();
        $v = new Validator();
        $v->require($input, 'title', $this->t('common/messages.field_required'))
            ->maxLen($input, 'title', 255, $this->t('checklist/messages.max_255'));

        if ($v->fails()) {
            return $this->error('VALIDATION_ERROR', $this->t('common/messages.validation_error'), 422, $v->errors());
        }

        /** @var ChecklistService $service */
        $service = $this->container->get('service.checklist');
        $item = $service->createItem((string)$params['public_id'], $input, $auth['user']);
        if (!$item) {
            return $this->error('CHECKLIST_NOT_FOUND', $this->t('checklist/messages.not_found'), 404);
        }

        return $this->success('CHECKLIST_ITEM_CREATED', $this->t('checklist/messages.item_created'), ['item' => $item], 201);
    }

    public function getItem(array $params): \Api\System\Library\Http\JsonResponse
    {
        $auth = $this->user();
        if (!$auth) {
            return $this->error('UNAUTHORIZED', $this->t('common/messages.unauthorized'), 401);
        }

        /** @var ChecklistService $service */
        $service = $this->container->get('service.checklist');
        $item = $service->getItem((string)$params['public_id'], $auth['user']);
        if (!$item) {
            return $this->error('CHECKLIST_ITEM_NOT_FOUND', $this->t('checklist/messages.item_not_found'), 404);
        }

        return $this->success('CHECKLIST_ITEM_DETAIL', $this->t('checklist/messages.item_detail'), ['item' => $item]);
    }

    public function updateItem(array $params): \Api\System\Library\Http\JsonResponse
    {
        $auth = $this->user();
        if (!$auth) {
            return $this->error('UNAUTHORIZED', $this->t('common/messages.unauthorized'), 401);
        }

        $input = $this->request()->allInput();
        $v = new Validator();
        $v->maxLen($input, 'title', 255, $this->t('checklist/messages.max_255'));

        if ($v->fails()) {
            return $this->error('VALIDATION_ERROR', $this->t('common/messages.validation_error'), 422, $v->errors());
        }

        /** @var ChecklistService $service */
        $service = $this->container->get('service.checklist');
        $item = $service->updateItem((string)$params['public_id'], $input, $auth['user']);
        if (!$item) {
            return $this->error('CHECKLIST_ITEM_NOT_FOUND', $this->t('checklist/messages.item_not_found'), 404);
        }

        return $this->success('CHECKLIST_ITEM_UPDATED', $this->t('checklist/messages.item_updated'), ['item' => $item]);
    }

    public function deleteItem(array $params): \Api\System\Library\Http\JsonResponse
    {
        $auth = $this->user();
        if (!$auth) {
            return $this->error('UNAUTHORIZED', $this->t('common/messages.unauthorized'), 401);
        }

        /** @var ChecklistService $service */
        $service = $this->container->get('service.checklist');
        $ok = $service->deleteItem((string)$params['public_id'], $auth['user']);
        if (!$ok) {
            return $this->error('CHECKLIST_ITEM_NOT_FOUND', $this->t('checklist/messages.item_not_found'), 404);
        }

        return $this->success('CHECKLIST_ITEM_DELETED', $this->t('checklist/messages.item_deleted'));
    }
}
