<?php
declare(strict_types=1);

namespace Api\Controller\Intake;

use Api\Controller\Common\BaseController;
use Api\System\Library\Service\IntakeItemService;

final class IntakeItemController extends BaseController
{
    public function list(): \Api\System\Library\Http\JsonResponse
    {
        $authUser = $this->user();
        if (!$authUser) {
            return $this->error('UNAUTHORIZED', $this->t('common/messages.unauthorized'), 401);
        }

        $input = $this->request()->allInput();
        /** @var IntakeItemService $service */
        $service = $this->container->get('service.intake_item');
        $result = $service->list($input);

        if (is_string($result)) {
            return $this->error($result, $this->t('common/messages.error', 'Error'), 422);
        }

        return $this->success('INTAKE_LIST', $this->t('intake/messages.list', 'Intake items list'), [
            'items' => $result['items'],
        ], meta: $result['meta']);
    }

    public function create(): \Api\System\Library\Http\JsonResponse
    {
        $authUser = $this->user();
        if (!$authUser) {
            return $this->error('UNAUTHORIZED', $this->t('common/messages.unauthorized'), 401);
        }

        $input = $this->request()->allInput();
        /** @var IntakeItemService $service */
        $service = $this->container->get('service.intake_item');
        $item = $service->create($input, $authUser['user']);

        if (is_string($item)) {
            $httpStatus = match ($item) {
                'INTAKE_PROJECT_NOT_FOUND', 'INTAKE_CLIENT_NOT_FOUND', 'INTAKE_CONTACT_NOT_FOUND' => 404,
                default => 422,
            };
            return $this->error($item, $this->t('common/messages.validation_error', 'Validation error'), $httpStatus);
        }

        return $this->success('INTAKE_CREATED', $this->t('intake/messages.created', 'Intake item created'), [
            'item' => $item,
        ], 201, [
            'row_version' => (int)($item['row_version'] ?? 1),
        ]);
    }

    public function get(array $params): \Api\System\Library\Http\JsonResponse
    {
        $authUser = $this->user();
        if (!$authUser) {
            return $this->error('UNAUTHORIZED', $this->t('common/messages.unauthorized'), 401);
        }

        /** @var IntakeItemService $service */
        $service = $this->container->get('service.intake_item');
        $item = $service->get((string)$params['public_id'], $authUser['user']);

        if (!$item) {
            return $this->error('INTAKE_NOT_FOUND', $this->t('intake/messages.not_found', 'Intake item not found'), 404);
        }

        return $this->success('INTAKE_DETAIL', $this->t('intake/messages.detail', 'Intake item detail'), [
            'item' => $item,
        ], meta: [
            'row_version' => (int)($item['row_version'] ?? 1),
        ]);
    }

    public function update(array $params): \Api\System\Library\Http\JsonResponse
    {
        $authUser = $this->user();
        if (!$authUser) {
            return $this->error('UNAUTHORIZED', $this->t('common/messages.unauthorized'), 401);
        }

        $input = $this->request()->allInput();
        /** @var IntakeItemService $service */
        $service = $this->container->get('service.intake_item');
        $item = $service->update((string)$params['public_id'], $input, $authUser['user']);

        if ($item === 'ROW_VERSION_CONFLICT') {
            return $this->error('ROW_VERSION_CONFLICT', $this->t('intake/messages.row_version_conflict', 'Item was modified by another user'), 409);
        }
        if (is_string($item)) {
            $httpStatus = match ($item) {
                'INTAKE_NOT_FOUND' => 404,
                'INTAKE_PROJECT_NOT_FOUND', 'INTAKE_CLIENT_NOT_FOUND', 'INTAKE_CONTACT_NOT_FOUND' => 404,
                'INTAKE_FIELD_NOT_EDITABLE' => 422,
                default => 422,
            };
            return $this->error($item, $this->t('common/messages.validation_error', 'Validation error'), $httpStatus);
        }
        if ($item === null) {
            return $this->error('INTAKE_NOT_FOUND', $this->t('intake/messages.not_found', 'Intake item not found'), 404);
        }

        return $this->success('INTAKE_UPDATED', $this->t('intake/messages.updated', 'Intake item updated'), [
            'item' => $item,
        ], meta: [
            'row_version' => (int)($item['row_version'] ?? 1),
        ]);
    }

    public function delete(array $params): \Api\System\Library\Http\JsonResponse
    {
        $authUser = $this->user();
        if (!$authUser) {
            return $this->error('UNAUTHORIZED', $this->t('common/messages.unauthorized'), 401);
        }

        /** @var IntakeItemService $service */
        $service = $this->container->get('service.intake_item');
        $ok = $service->delete((string)$params['public_id'], $authUser['user']);

        if ($ok === false) {
            return $this->error('INTAKE_NOT_FOUND', $this->t('intake/messages.not_found', 'Intake item not found'), 404);
        }

        return $this->success('INTAKE_DELETED', $this->t('intake/messages.deleted', 'Intake item deleted'));
    }

    public function accept(array $params): \Api\System\Library\Http\JsonResponse
    {
        $authUser = $this->user();
        if (!$authUser) {
            return $this->error('UNAUTHORIZED', $this->t('common/messages.unauthorized'), 401);
        }

        $input = $this->request()->allInput();
        /** @var IntakeItemService $service */
        $service = $this->container->get('service.intake_item');
        $result = $service->accept((string)$params['public_id'], $input, $authUser['user']);

        if ($result === 'ROW_VERSION_CONFLICT') {
            return $this->error('ROW_VERSION_CONFLICT', $this->t('intake/messages.row_version_conflict', 'Item was modified by another user'), 409);
        }
        if (is_string($result)) {
            $httpStatus = match ($result) {
                'INTAKE_NOT_FOUND' => 404,
                'INTAKE_ALREADY_ACCEPTED' => 422,
                'INTAKE_INVALID_STATUS_TRANSITION' => 422,
                'INTAKE_PROJECT_NOT_FOUND' => 404,
                'INTAKE_ACCEPT_TASK_CREATE_FAILED' => 500,
                default => 422,
            };
            return $this->error($result, $this->t('common/messages.validation_error', 'Validation error'), $httpStatus);
        }
        if ($result === null) {
            return $this->error('INTAKE_NOT_FOUND', $this->t('intake/messages.not_found', 'Intake item not found'), 404);
        }

        return $this->success('INTAKE_ACCEPTED', $this->t('intake/messages.accepted', 'Intake item accepted'), [
            'item' => $result['item'],
            'task' => $result['task'],
        ]);
    }

    public function reject(array $params): \Api\System\Library\Http\JsonResponse
    {
        $authUser = $this->user();
        if (!$authUser) {
            return $this->error('UNAUTHORIZED', $this->t('common/messages.unauthorized'), 401);
        }

        $input = $this->request()->allInput();
        /** @var IntakeItemService $service */
        $service = $this->container->get('service.intake_item');
        $result = $service->reject((string)$params['public_id'], $input, $authUser['user']);

        if ($result === 'ROW_VERSION_CONFLICT') {
            return $this->error('ROW_VERSION_CONFLICT', $this->t('intake/messages.row_version_conflict', 'Item was modified by another user'), 409);
        }
        if (is_string($result)) {
            $httpStatus = match ($result) {
                'INTAKE_NOT_FOUND' => 404,
                'INTAKE_INVALID_STATUS_TRANSITION' => 422,
                'INTAKE_REASON_REQUIRED' => 422,
                default => 422,
            };
            return $this->error($result, $this->t('common/messages.validation_error', 'Validation error'), $httpStatus);
        }
        if ($result === null) {
            return $this->error('INTAKE_NOT_FOUND', $this->t('intake/messages.not_found', 'Intake item not found'), 404);
        }

        return $this->success('INTAKE_REJECTED', $this->t('intake/messages.rejected', 'Intake item rejected'), [
            'item' => $result,
        ]);
    }

    public function snooze(array $params): \Api\System\Library\Http\JsonResponse
    {
        $authUser = $this->user();
        if (!$authUser) {
            return $this->error('UNAUTHORIZED', $this->t('common/messages.unauthorized'), 401);
        }

        $input = $this->request()->allInput();
        /** @var IntakeItemService $service */
        $service = $this->container->get('service.intake_item');
        $result = $service->snooze((string)$params['public_id'], $input, $authUser['user']);

        if ($result === 'ROW_VERSION_CONFLICT') {
            return $this->error('ROW_VERSION_CONFLICT', $this->t('intake/messages.row_version_conflict', 'Item was modified by another user'), 409);
        }
        if (is_string($result)) {
            $httpStatus = match ($result) {
                'INTAKE_NOT_FOUND' => 404,
                'INTAKE_INVALID_STATUS_TRANSITION' => 422,
                'INTAKE_SNOOZED_UNTIL_REQUIRED' => 422,
                default => 422,
            };
            return $this->error($result, $this->t('common/messages.validation_error', 'Validation error'), $httpStatus);
        }
        if ($result === null) {
            return $this->error('INTAKE_NOT_FOUND', $this->t('intake/messages.not_found', 'Intake item not found'), 404);
        }

        return $this->success('INTAKE_SNOOZED', $this->t('intake/messages.snoozed', 'Intake item snoozed'), [
            'item' => $result,
        ]);
    }

    public function duplicate(array $params): \Api\System\Library\Http\JsonResponse
    {
        $authUser = $this->user();
        if (!$authUser) {
            return $this->error('UNAUTHORIZED', $this->t('common/messages.unauthorized'), 401);
        }

        $input = $this->request()->allInput();
        /** @var IntakeItemService $service */
        $service = $this->container->get('service.intake_item');
        $result = $service->markDuplicate((string)$params['public_id'], $input, $authUser['user']);

        if ($result === 'ROW_VERSION_CONFLICT') {
            return $this->error('ROW_VERSION_CONFLICT', $this->t('intake/messages.row_version_conflict', 'Item was modified by another user'), 409);
        }
        if (is_string($result)) {
            $httpStatus = match ($result) {
                'INTAKE_NOT_FOUND' => 404,
                'INTAKE_INVALID_STATUS_TRANSITION' => 422,
                'INTAKE_DUPLICATE_TARGET_REQUIRED' => 422,
                'INTAKE_DUPLICATE_TARGET_NOT_FOUND' => 404,
                default => 422,
            };
            return $this->error($result, $this->t('common/messages.validation_error', 'Validation error'), $httpStatus);
        }
        if ($result === null) {
            return $this->error('INTAKE_NOT_FOUND', $this->t('intake/messages.not_found', 'Intake item not found'), 404);
        }

        return $this->success('INTAKE_MARKED_DUPLICATE', $this->t('intake/messages.marked_duplicate', 'Intake item marked as duplicate'), [
            'item' => $result,
        ]);
    }

    public function reopen(array $params): \Api\System\Library\Http\JsonResponse
    {
        $authUser = $this->user();
        if (!$authUser) {
            return $this->error('UNAUTHORIZED', $this->t('common/messages.unauthorized'), 401);
        }

        $input = $this->request()->allInput();
        /** @var IntakeItemService $service */
        $service = $this->container->get('service.intake_item');
        $result = $service->reopen((string)$params['public_id'], $authUser['user']);

        if (is_string($result)) {
            $httpStatus = match ($result) {
                'INTAKE_NOT_FOUND' => 404,
                'INTAKE_INVALID_STATUS_TRANSITION' => 422,
                default => 422,
            };
            return $this->error($result, $this->t('common/messages.validation_error', 'Validation error'), $httpStatus);
        }
        if ($result === null) {
            return $this->error('INTAKE_NOT_FOUND', $this->t('intake/messages.not_found', 'Intake item not found'), 404);
        }

        return $this->success('INTAKE_REOPENED', $this->t('intake/messages.reopened', 'Intake item reopened'), [
            'item' => $result,
        ]);
    }

    public function bulk(array $params = []): \Api\System\Library\Http\JsonResponse
    {
        $authUser = $this->user();
        if (!$authUser) {
            return $this->error('UNAUTHORIZED', $this->t('common/messages.unauthorized'), 401);
        }

        $input = $this->request()->allInput();
        $action = (string)($input['action'] ?? '');

        // The route gate requires intake.manage; accept and delete are guarded
        // by narrower permissions, so re-check them per action to keep bulk
        // triage consistent with the single-item RBAC model.
        $actionPermission = match ($action) {
            'accept' => 'intake.accept',
            'delete' => 'intake.delete',
            default => 'intake.manage',
        };
        /** @var \Api\System\Library\Service\AuthzService $authz */
        $authz = $this->container->get('service.authz');
        if (!$authz->hasPermissions($authUser['user'], [$actionPermission])) {
            return $this->error('FORBIDDEN', $this->t('common/messages.forbidden'), 403);
        }

        /** @var IntakeItemService $service */
        $service = $this->container->get('service.intake_item');
        $result = $service->bulk($input, $authUser['user']);

        if (is_string($result)) {
            $httpStatus = match ($result) {
                'INTAKE_BULK_IDS_REQUIRED', 'INTAKE_BULK_INVALID_ACTION', 'INTAKE_BULK_TOO_MANY_ITEMS' => 422,
                default => 422,
            };
            return $this->error($result, $this->t('common/messages.validation_error', 'Validation error'), $httpStatus);
        }

        $summary = $result['summary'] ?? [];
        $action = (string)($result['action'] ?? '');
        $message = match ($action) {
            'accept' => $this->t('intake/messages.bulk_accepted', 'Requests processed'),
            'reject' => $this->t('intake/messages.bulk_rejected', 'Requests rejected'),
            'assign' => $this->t('intake/messages.bulk_assigned', 'Requests assigned'),
            'snooze' => $this->t('intake/messages.bulk_snoozed', 'Requests snoozed'),
            'reopen' => $this->t('intake/messages.bulk_reopened', 'Requests reopened'),
            'delete' => $this->t('intake/messages.bulk_deleted', 'Requests deleted'),
            default => $this->t('intake/messages.bulk_done', 'Bulk operation complete'),
        };

        return $this->success('INTAKE_BULK_DONE', $message, [
            'action' => $action,
            'summary' => $summary,
        ]);
    }

    public function activities(array $params): \Api\System\Library\Http\JsonResponse
    {
        $authUser = $this->user();
        if (!$authUser) {
            return $this->error('UNAUTHORIZED', $this->t('common/messages.unauthorized'), 401);
        }

        $filters = $this->request()->allInput();
        /** @var IntakeItemService $service */
        $service = $this->container->get('service.intake_item');
        $result = $service->activities((string)$params['public_id'], $filters, $authUser['user']);

        if (is_string($result)) {
            return $this->error($result, $this->t('common/messages.error', 'Error'), 422);
        }
        if ($result === null) {
            return $this->error('INTAKE_NOT_FOUND', $this->t('intake/messages.not_found', 'Intake item not found'), 404);
        }

        return $this->success('INTAKE_ACTIVITIES', $this->t('intake/messages.activities', 'Intake item activities'), [
            'items' => $result['items'],
        ]);
    }
}
