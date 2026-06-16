<?php
declare(strict_types=1);

namespace Api\Controller\View;

use Api\Controller\Common\BaseController;
use Api\System\Library\Service\SavedViewService;
use Api\System\Library\Validation\Validator;

final class ViewController extends BaseController
{
    public function list(): \Api\System\Library\Http\JsonResponse
    {
        $auth = $this->user();
        if (!$auth) {
            return $this->error('UNAUTHORIZED', $this->t('common/messages.unauthorized'), 401);
        }

        /** @var SavedViewService $service */
        $service = $this->container->get('service.saved_view');
        $result = $service->list($this->request()->allInput(), $auth['user']);

        return $this->success('SAVED_VIEW_LIST', $this->t('view/messages.list'), [
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
        $v->require($input, 'title', $this->t('view/messages.title_required'));

        if ($v->fails()) {
            return $this->error('VALIDATION_ERROR', $this->t('common/messages.validation_error'), 422, $v->errors());
        }

        /** @var SavedViewService $service */
        $service = $this->container->get('service.saved_view');
        $item = $service->create($input, $auth['user']);

        return $this->mapServiceResponse($item, 'SAVED_VIEW_CREATED', 'view/messages.created', 201);
    }

    public function get(array $params): \Api\System\Library\Http\JsonResponse
    {
        $auth = $this->user();
        if (!$auth) {
            return $this->error('UNAUTHORIZED', $this->t('common/messages.unauthorized'), 401);
        }

        /** @var SavedViewService $service */
        $service = $this->container->get('service.saved_view');
        $item = $service->get((string)$params['public_id'], $auth['user']);

        if ($item === null) {
            return $this->error('SAVED_VIEW_NOT_FOUND', $this->t('view/messages.not_found'), 404);
        }

        if (is_string($item)) {
            return $this->mapServiceError($item);
        }

        return $this->success('SAVED_VIEW_DETAIL', $this->t('view/messages.detail'), [
            'saved_view' => $item,
        ]);
    }

    public function update(array $params): \Api\System\Library\Http\JsonResponse
    {
        $auth = $this->user();
        if (!$auth) {
            return $this->error('UNAUTHORIZED', $this->t('common/messages.unauthorized'), 401);
        }

        /** @var SavedViewService $service */
        $service = $this->container->get('service.saved_view');
        $item = $service->update((string)$params['public_id'], $this->request()->allInput(), $auth['user']);

        return $this->mapServiceResponse($item, 'SAVED_VIEW_UPDATED', 'view/messages.updated');
    }

    public function archive(array $params): \Api\System\Library\Http\JsonResponse
    {
        $auth = $this->user();
        if (!$auth) {
            return $this->error('UNAUTHORIZED', $this->t('common/messages.unauthorized'), 401);
        }

        /** @var SavedViewService $service */
        $service = $this->container->get('service.saved_view');
        $ok = $service->archive((string)$params['public_id'], $auth['user']);

        if ($ok === false) {
            return $this->error('SAVED_VIEW_NOT_FOUND', $this->t('view/messages.not_found'), 404);
        }

        if (is_string($ok)) {
            return $this->mapServiceError($ok);
        }

        $this->invalidateCache('view');

        return $this->success('SAVED_VIEW_ARCHIVED', $this->t('view/messages.archived', 'Saved view archived'));
    }

    public function delete(array $params): \Api\System\Library\Http\JsonResponse
    {
        return $this->archive($params);
    }

    public function duplicate(array $params): \Api\System\Library\Http\JsonResponse
    {
        $auth = $this->user();
        if (!$auth) {
            return $this->error('UNAUTHORIZED', $this->t('common/messages.unauthorized'), 401);
        }

        /** @var SavedViewService $service */
        $service = $this->container->get('service.saved_view');
        $item = $service->duplicate((string)$params['public_id'], $this->request()->allInput(), $auth['user']);

        return $this->mapServiceResponse($item, 'SAVED_VIEW_DUPLICATED', 'view/messages.duplicated', 201);
    }

    public function pin(array $params): \Api\System\Library\Http\JsonResponse
    {
        $auth = $this->user();
        if (!$auth) {
            return $this->error('UNAUTHORIZED', $this->t('common/messages.unauthorized'), 401);
        }

        /** @var SavedViewService $service */
        $service = $this->container->get('service.saved_view');
        $pref = $service->pin((string)$params['public_id'], $this->request()->allInput(), $auth['user']);

        if ($pref === null) {
            return $this->error('SAVED_VIEW_NOT_FOUND', $this->t('view/messages.not_found'), 404);
        }

        if (is_string($pref)) {
            return $this->mapServiceError($pref);
        }

        return $this->success('SAVED_VIEW_PINNED', $this->t('view/messages.pinned', 'Saved view preferences updated'), [
            'preference' => $pref,
        ]);
    }

    public function touchLastUsed(array $params): \Api\System\Library\Http\JsonResponse
    {
        $auth = $this->user();
        if (!$auth) {
            return $this->error('UNAUTHORIZED', $this->t('common/messages.unauthorized'), 401);
        }

        /** @var SavedViewService $service */
        $service = $this->container->get('service.saved_view');
        $ok = $service->touchLastUsed((string)$params['public_id'], $auth['user']);

        if ($ok === false) {
            return $this->error('SAVED_VIEW_NOT_FOUND', $this->t('view/messages.not_found'), 404);
        }

        if (is_string($ok)) {
            return $this->mapServiceError($ok);
        }

        return $this->success('SAVED_VIEW_LAST_USED', $this->t('common/messages.ok', 'OK'));
    }

    public function taskFilters(array $params): \Api\System\Library\Http\JsonResponse
    {
        $auth = $this->user();
        if (!$auth) {
            return $this->error('UNAUTHORIZED', $this->t('common/messages.unauthorized'), 401);
        }

        /** @var SavedViewService $service */
        $service = $this->container->get('service.saved_view');
        $result = $service->getTaskFilters((string)$params['public_id'], $auth['user']);

        if ($result === null) {
            return $this->error('SAVED_VIEW_NOT_FOUND', $this->t('view/messages.not_found'), 404);
        }

        if (is_string($result)) {
            return $this->mapServiceError($result);
        }

        return $this->success('SAVED_VIEW_TASK_FILTERS', $this->t('view/messages.task_filters', 'Task filters'), $result);
    }

    /**
     * Map service response (array|string|null) to JsonResponse.
     */
    private function mapServiceResponse(array|string|null $result, string $successCode, string $messageKey, int $status = 200): \Api\System\Library\Http\JsonResponse
    {
        if ($result === null) {
            return $this->error('SAVED_VIEW_NOT_FOUND', $this->t('view/messages.not_found'), 404);
        }

        if (is_string($result)) {
            return $this->mapServiceError($result);
        }

        $this->invalidateCache('view');

        return $this->success($successCode, $this->t($messageKey), [
            'saved_view' => $result,
        ], $status);
    }

    /**
     * Map a service error code to HTTP error response.
     */
    private function mapServiceError(string $code): \Api\System\Library\Http\JsonResponse
    {
        return match ($code) {
            'SAVED_VIEW_NOT_FOUND' => $this->error($code, $this->t('view/messages.not_found'), 404),
            'SAVED_VIEW_FORBIDDEN' => $this->error($code, $this->t('common/messages.forbidden'), 403),
            'SAVED_VIEW_LOCKED' => $this->error($code, $this->t('view/messages.locked', 'This view is locked and cannot be edited'), 403),
            'SAVED_VIEW_TITLE_REQUIRED' => $this->error($code, $this->t('view/messages.title_required'), 422),
            'SAVED_VIEW_TITLE_TOO_LONG' => $this->error($code, $this->t('view/messages.title_too_long', 'Title is too long'), 422),
            'SAVED_VIEW_TITLE_ALREADY_EXISTS' => $this->error($code, $this->t('view/messages.title_already_exists', 'A view with this title already exists'), 409),
            'SAVED_VIEW_INVALID_ENTITY_TYPE',
            'SAVED_VIEW_INVALID_ACCESS_LEVEL',
            'SAVED_VIEW_INVALID_LAYOUT',
            'SAVED_VIEW_INVALID_GROUP_BY',
            'SAVED_VIEW_INVALID_ORDER_BY',
            'SAVED_VIEW_INVALID_ORDER_DIR',
            'SAVED_VIEW_INVALID_FILTERS',
            'SAVED_VIEW_INVALID_DISPLAY_FILTERS',
            'SAVED_VIEW_INVALID_DISPLAY_PROPERTIES',
            'SAVED_VIEW_INVALID_RICH_FILTERS',
            'SAVED_VIEW_INVALID_DESCRIPTION' => $this->error($code, $this->t('common/messages.validation_error'), 422),
            'SAVED_VIEW_SYSTEM_ONLY_ROOT' => $this->error($code, $this->t('view/messages.system_only_root', 'Only root can create system views'), 403),
            'ROW_VERSION_CONFLICT' => $this->error($code, $this->t('common/messages.row_version_conflict', 'Row version conflict'), 409),
            default => $this->error($code, $this->t('common/messages.validation_error'), 422),
        };
    }
}
