<?php
declare(strict_types=1);

namespace Api\Controller\Task;

use Api\Controller\Common\BaseController;
use Api\System\Library\Service\TaskRelationService;
use Api\System\Library\Validation\Validator;

final class TaskRelationController extends BaseController
{
    public function list(array $params): \Api\System\Library\Http\JsonResponse
    {
        $auth = $this->user();
        if (!$auth) {
            return $this->error('UNAUTHORIZED', $this->t('common/messages.unauthorized'), 401);
        }

        $taskPublicId = (string)($params['public_id'] ?? '');
        if ($taskPublicId === '') {
            return $this->error('TASK_NOT_FOUND', $this->t('common/messages.task_not_found'), 404);
        }

        /** @var TaskRelationService $service */
        $service = $this->container->get('service.task_relation');
        $result = $service->list($taskPublicId, $auth['user']);

        if ($result === null) {
            return $this->error('TASK_NOT_FOUND', $this->t('common/messages.task_not_found'), 404);
        }

        return $this->success('TASK_RELATIONS', $this->t('task_relations/messages.list'), $result);
    }

    public function create(array $params): \Api\System\Library\Http\JsonResponse
    {
        $auth = $this->user();
        if (!$auth) {
            return $this->error('UNAUTHORIZED', $this->t('common/messages.unauthorized'), 401);
        }

        $taskPublicId = (string)($params['public_id'] ?? '');
        if ($taskPublicId === '') {
            return $this->error('TASK_NOT_FOUND', $this->t('common/messages.task_not_found'), 404);
        }

        $input = $this->request()->allInput();
        $v = new Validator();
        $v->require($input, 'relation_type', $this->t('common/messages.field_required'));

        if ($v->fails()) {
            return $this->error('VALIDATION_ERROR', $this->t('common/messages.validation_error'), 422, $v->errors());
        }

        /** @var TaskRelationService $service */
        $service = $this->container->get('service.task_relation');
        $result = $service->create($taskPublicId, $input, $auth['user']);

        if ($result === null) {
            return $this->error('TASK_NOT_FOUND', $this->t('common/messages.task_not_found'), 404);
        }

        return match ($result) {
            'TASK_RELATION_TARGET_REQUIRED' => $this->error(
                'TASK_RELATION_TARGET_REQUIRED',
                $this->t('task_relations/messages.target_required', 'Target task is required'),
                422
            ),
            'TASK_RELATION_TARGET_NOT_FOUND' => $this->error(
                'TASK_RELATION_TARGET_NOT_FOUND',
                $this->t('task_relations/messages.target_not_found', 'Target task not found'),
                404
            ),
            'TASK_RELATION_TYPE_INVALID' => $this->error(
                'TASK_RELATION_TYPE_INVALID',
                $this->t('task_relations/messages.invalid_type', 'Invalid relation type'),
                422,
                ['relation_type' => [$this->t('task_relations/messages.supported_types', 'blocked_by, relates_to, duplicate, implements, caused_by, parent_of')]]
            ),
            'TASK_RELATION_SELF_LINK_FORBIDDEN' => $this->error(
                'TASK_RELATION_SELF_LINK_FORBIDDEN',
                $this->t('task_relations/messages.self_link_forbidden', 'Cannot relate a task to itself'),
                422
            ),
            'TASK_RELATION_ALREADY_EXISTS' => $this->error(
                'TASK_RELATION_ALREADY_EXISTS',
                $this->t('task_relations/messages.already_exists', 'This relation already exists'),
                409
            ),
            'TASK_RELATION_NOTE_TOO_LONG' => $this->error(
                'TASK_RELATION_NOTE_TOO_LONG',
                $this->t('task_relations/messages.note_too_long', 'Note exceeds maximum length'),
                422,
                ['note' => [$this->t('task_relations/messages.note_too_long', 'Note exceeds maximum length')]]
            ),
            default => $this->success(
                'TASK_RELATION_CREATED',
                $this->t('task_relations/messages.created', 'Task relation created'),
                ['relation' => $result],
                201
            ),
        };
    }

    public function delete(array $params): \Api\System\Library\Http\JsonResponse
    {
        $auth = $this->user();
        if (!$auth) {
            return $this->error('UNAUTHORIZED', $this->t('common/messages.unauthorized'), 401);
        }

        $relationPublicId = (string)($params['public_id'] ?? '');
        if ($relationPublicId === '') {
            return $this->error('TASK_RELATION_NOT_FOUND', $this->t('task_relations/messages.not_found', 'Relation not found'), 404);
        }

        /** @var TaskRelationService $service */
        $service = $this->container->get('service.task_relation');
        $result = $service->delete($relationPublicId, $auth['user']);

        if ($result === false) {
            return $this->error('TASK_RELATION_NOT_FOUND', $this->t('task_relations/messages.not_found', 'Relation not found'), 404);
        }

        if ($result === 'TASK_RELATION_FORBIDDEN') {
            return $this->error('TASK_RELATION_FORBIDDEN', $this->t('common/messages.forbidden'), 403);
        }

        return $this->success('TASK_RELATION_DELETED', $this->t('task_relations/messages.deleted', 'Task relation deleted'));
    }

    public function searchTasks(): \Api\System\Library\Http\JsonResponse
    {
        $auth = $this->user();
        if (!$auth) {
            return $this->error('UNAUTHORIZED', $this->t('common/messages.unauthorized'), 401);
        }

        $input = $this->request()->allInput();

        /** @var TaskRelationService $service */
        $service = $this->container->get('service.task_relation');
        $items = $service->searchTasks($input, $auth['user']);

        return $this->success('TASK_SEARCH_RESULTS', $this->t('common/messages.ok', 'OK'), ['items' => $items]);
    }
}
