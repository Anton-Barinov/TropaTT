<?php
declare(strict_types=1);

namespace Api\Controller\Task;

use Api\Controller\Common\BaseController;
use Api\Model\Status\StatusRepository;
use Api\System\Library\Service\CommentService;
use Api\System\Library\Service\TaskBulkService;
use Api\System\Library\Service\TaskBoardService;
use Api\System\Library\Service\TaskService;
use Api\System\Library\Validation\Validator;

final class TaskController extends BaseController
{
    public function board(): \Api\System\Library\Http\JsonResponse
    {
        $authUser = $this->user();
        if (!$authUser) {
            return $this->error('UNAUTHORIZED', $this->t('common/messages.unauthorized'), 401);
        }

        $cache = $this->cacheApi();
        if ($cache !== null) {
            $input = $this->request()->allInput();
            ksort($input);
            $cacheKey = 'board:' . $this->cacheUserId() . ':' . hash('sha256', json_encode($input));
            $result = $cache->remember('task', $cacheKey, 60, function () use ($input, $authUser) {
                /** @var TaskBoardService $service */
                $service = $this->container->get('service.task_board');
                return $service->board($input, $authUser['user']);
            });
        } else {
            /** @var TaskBoardService $service */
            $service = $this->container->get('service.task_board');
            $result = $service->board($this->request()->allInput(), $authUser['user']);
        }

        return $this->success('TASK_BOARD', $this->t('task/messages.board'), [
            'board' => $result['board'],
        ], meta: $result['meta']);
    }

    public function list(): \Api\System\Library\Http\JsonResponse
    {
        $authUser = $this->user();
        if (!$authUser) {
            return $this->error('UNAUTHORIZED', $this->t('common/messages.unauthorized'), 401);
        }

        $input = $this->request()->allInput();
        $errors = [];
        if (!empty($input['updated_since']) && strtotime((string)$input['updated_since']) === false) {
            $errors['updated_since'][] = $this->t('common/messages.invalid_date');
        }
        if (!empty($input['cursor']) && strlen((string)$input['cursor']) > 1024) {
            $errors['cursor'][] = $this->t('task/messages.invalid_cursor');
        }
        if ($errors !== []) {
            return $this->error('VALIDATION_ERROR', $this->t('common/messages.validation_error'), 422, $errors);
        }

        $cache = $this->cacheApi();
        if ($cache !== null) {
            ksort($input);
            $cacheKey = 'list:' . $this->cacheUserId() . ':' . hash('sha256', json_encode($input));
            $result = $cache->remember('task', $cacheKey, 60, function () use ($input, $authUser) {
                /** @var TaskService $service */
                $service = $this->container->get('service.task');
                return $service->list($input, $authUser['user']);
            });
        } else {
            /** @var TaskService $service */
            $service = $this->container->get('service.task');
            $result = $service->list($input, $authUser['user']);
        }

        return $this->success('TASK_LIST', $this->t('task/messages.list'), [
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
        $v = new Validator();
        $v->require($input, 'title', $this->t('common/messages.field_required'))
            ->maxLen($input, 'title', 255, $this->t('task/messages.max_255'))
            ->maxLen($input, 'description', 65000, 'Description is too long')
            ->enum($input, 'priority', ['low', 'normal', 'high', 'urgent'], $this->t('task/messages.invalid_priority'))
            ->date($input, 'due_at', $this->t('common/messages.invalid_date'));

        // SEC-003: Sanitize HTML from user input to prevent stored XSS
        if (isset($input['title']) && is_string($input['title'])) {
            $input['title'] = strip_tags((string)$input['title']);
        }
        if (isset($input['description']) && is_string($input['description'])) {
            $input['description'] = strip_tags((string)$input['description'], '<b><i><u><p><br><ul><ol><li><a><strong><em><h1><h2><h3><h4><h5><h6><blockquote><code><pre><table><thead><tbody><tr><th><td><hr>');
        }

        $errors = $v->errors();
        if (array_key_exists('status', $input)) {
            $statusCode = trim((string)$input['status']);
            if ($statusCode !== '' && !$this->isAllowedTaskStatus($statusCode)) {
                $errors['status'][] = $this->t('task/messages.invalid_status');
            }
        }

        if ($errors !== []) {
            return $this->error('VALIDATION_ERROR', $this->t('common/messages.validation_error'), 422, $errors);
        }

        return $this->withIdempotency(function () use ($authUser, $input): \Api\System\Library\Http\JsonResponse {
            /** @var TaskService $service */
            $service = $this->container->get('service.task');
            $item = $service->create($input, $authUser['user']);
            if ($item === 'PROJECT_NOT_FOUND') {
                return $this->error('PROJECT_NOT_FOUND', $this->t('common/messages.project_not_found'), 404, [
                    'project' => [$this->t('common/messages.project_not_found')],
                ]);
            }
            if ($item === 'PARENT_TASK_NOT_FOUND') {
                return $this->error('PARENT_TASK_NOT_FOUND', $this->t('common/messages.task_not_found'), 404, [
                    'parent_task_public_id' => [$this->t('common/messages.task_not_found')],
                ]);
            }

            $this->fireWorkflowTrigger('task_created', $item, $authUser['user']);

            $this->invalidateCache('task');

            return $this->success('TASK_CREATED', $this->t('task/messages.created'), [
                'task' => $item,
            ], 201, [
                'row_version' => (int)($item['row_version'] ?? 1),
            ]);
        });
    }

    public function get(array $params): \Api\System\Library\Http\JsonResponse
    {
        $authUser = $this->user();
        if (!$authUser) {
            return $this->error('UNAUTHORIZED', $this->t('common/messages.unauthorized'), 401);
        }

        /** @var TaskService $service */
        $service = $this->container->get('service.task');
        $item = $service->get((string)$params['public_id'], $authUser['user']);

        if (!$item) {
            return $this->error('TASK_NOT_FOUND', $this->t('common/messages.task_not_found'), 404, [
                'task' => [$this->t('common/messages.task_not_found')],
            ]);
        }

        return $this->success('TASK_DETAIL', $this->t('task/messages.detail'), [
            'task' => $item,
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
        $v = new Validator();
        $v->maxLen($input, 'title', 255, $this->t('task/messages.max_255'))
            ->maxLen($input, 'description', 65000, 'Description is too long')
            ->enum($input, 'priority', ['low', 'normal', 'high', 'urgent'], $this->t('task/messages.invalid_priority'))
            ->date($input, 'due_at', $this->t('common/messages.invalid_date'));

        // SEC-003: Sanitize HTML from user input to prevent stored XSS
        if (isset($input['title']) && is_string($input['title'])) {
            $input['title'] = strip_tags((string)$input['title']);
        }
        if (isset($input['description']) && is_string($input['description'])) {
            $input['description'] = strip_tags((string)$input['description'], '<b><i><u><p><br><ul><ol><li><a><strong><em><h1><h2><h3><h4><h5><h6><blockquote><code><pre><table><thead><tbody><tr><th><td><hr>');
        }

        $errors = $v->errors();
        if (array_key_exists('status', $input)) {
            $statusCode = trim((string)$input['status']);
            if ($statusCode !== '' && !$this->isAllowedTaskStatus($statusCode)) {
                $errors['status'][] = $this->t('task/messages.invalid_status');
            }
        }

        if ($errors !== []) {
            return $this->error('VALIDATION_ERROR', $this->t('common/messages.validation_error'), 422, $errors);
        }

        /** @var TaskService $service */
        $service = $this->container->get('service.task');
        $before = $service->get((string)$params['public_id'], $authUser['user']);
        $item = $service->update((string)$params['public_id'], $input, (int)$authUser['user']['id'], $authUser['user']);

        if ($item === 'ROW_VERSION_CONFLICT') {
            return $this->error('ROW_VERSION_CONFLICT', $this->t('task/messages.row_version_conflict'), 409);
        }
        if ($item === 'PROJECT_NOT_FOUND') {
            return $this->error('PROJECT_NOT_FOUND', $this->t('common/messages.project_not_found'), 404, [
                'project' => [$this->t('common/messages.project_not_found')],
            ]);
        }
        if ($item === 'PARENT_TASK_NOT_FOUND') {
            return $this->error('PARENT_TASK_NOT_FOUND', $this->t('common/messages.task_not_found'), 404, [
                'parent_task_public_id' => [$this->t('common/messages.task_not_found')],
            ]);
        }
        if ($item === 'INVALID_PARENT_TASK') {
            return $this->error('INVALID_PARENT_TASK', $this->t('common/messages.validation_error'), 422, [
                'parent_task_public_id' => [$this->t('common/messages.validation_error')],
            ]);
        }
        if ($item === 'CYCLIC_DEPENDENCY_DETECTED') {
            return $this->error('CYCLIC_DEPENDENCY_DETECTED', 'Circular dependency detected in task hierarchy', 422, [
                'parent_task_public_id' => ['Circular dependency detected'],
            ]);
        }
        if ($item === 'TASK_KEY_FIELD_NOT_EDITABLE') {
            return $this->error('TASK_KEY_FIELD_NOT_EDITABLE', $this->t('task/messages.not_editable', 'Task key fields are not editable'), 422, [
                'task_key' => [$this->t('task/messages.not_editable', 'Task key fields are not editable')],
            ]);
        }
        if ($item === 'FORBIDDEN_TASK_IDENTITY_EDIT') {
            return $this->error('FORBIDDEN_TASK_IDENTITY_EDIT', $this->t('task/messages.identity_edit_forbidden'), 403, [
                'task' => [$this->t('task/messages.identity_edit_forbidden')],
            ]);
        }
        if (!$item || !is_array($item)) {
            return $this->error('TASK_NOT_FOUND', $this->t('common/messages.task_not_found'), 404);
        }

        if (isset($input['status']) && (string)$input['status'] !== (string)($before['status_code'] ?? '')) {
            $this->fireWorkflowTrigger('task_status_changed', $item, $authUser['user'], [
                'previous_status' => $before['status_code'] ?? null,
                'new_status' => (string)$input['status'],
            ]);
        }
        $this->fireWorkflowTrigger('task_updated', $item, $authUser['user']);

        $this->invalidateCache('task');

        return $this->success('TASK_UPDATED', $this->t('task/messages.updated'), [
            'task' => $item,
        ], meta: [
            'row_version' => (int)($item['row_version'] ?? 1),
        ]);
    }

    public function getByKey(array $params): \Api\System\Library\Http\JsonResponse
    {
        $authUser = $this->user();
        if (!$authUser) {
            return $this->error('UNAUTHORIZED', $this->t('common/messages.unauthorized'), 401);
        }

        $key = trim((string)($params['task_key'] ?? $this->request()->query('key', '')));
        if ($key === '') {
            return $this->error('TASK_KEY_INVALID', $this->t('task/messages.invalid_task_key', 'Invalid task key'), 422);
        }

        /** @var TaskService $service */
        $service = $this->container->get('service.task');
        $item = $service->getByTaskKey($key, $authUser['user']);

        if (!$item) {
            return $this->error('TASK_NOT_FOUND', $this->t('common/messages.task_not_found'), 404);
        }

        return $this->success('TASK_DETAIL', $this->t('task/messages.detail'), [
            'task' => $item,
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

        /** @var TaskService $service */
        $service = $this->container->get('service.task');
        $ok = $service->delete((string)$params['public_id'], $authUser['user']);
        if (!$ok) {
            return $this->error('TASK_NOT_FOUND', $this->t('common/messages.task_not_found'), 404, [
                'task' => [$this->t('common/messages.task_not_found')],
            ]);
        }

        $this->invalidateCache('task');

        return $this->success('TASK_DELETED', $this->t('task/messages.deleted'));
    }

    public function comments(array $params): \Api\System\Library\Http\JsonResponse
    {
        $authUser = $this->user();
        if (!$authUser) {
            return $this->error('UNAUTHORIZED', $this->t('common/messages.unauthorized'), 401);
        }

        /** @var TaskService $taskService */
        $taskService = $this->container->get('service.task');
        $task = $taskService->get((string)$params['public_id'], $authUser['user']);
        if (!$task) {
            return $this->error('TASK_NOT_FOUND', $this->t('common/messages.task_not_found'), 404, [
                'task' => [$this->t('common/messages.task_not_found')],
            ]);
        }

        /** @var CommentService $service */
        $service = $this->container->get('service.comment');
        $result = $service->listByTask((string)$params['public_id'], $this->request()->allInput());

        return $this->success('COMMENT_LIST', $this->t('task/messages.comment_list'), [
            'items' => $result['items'],
        ], meta: $result['meta']);
    }

    public function addComment(array $params): \Api\System\Library\Http\JsonResponse
    {
        $authUser = $this->user();
        if (!$authUser) {
            return $this->error('UNAUTHORIZED', $this->t('common/messages.unauthorized'), 401);
        }

        $input = $this->request()->allInput();
        $v = new Validator();
        $v->require($input, 'body', $this->t('common/messages.field_required'))
            ->maxLen($input, 'body', 8000, $this->t('task/messages.max_8000'));

        if ($v->fails()) {
            return $this->error('VALIDATION_ERROR', $this->t('common/messages.validation_error'), 422, $v->errors());
        }

        /** @var CommentService $service */
        /** @var TaskService $taskService */
        $taskService = $this->container->get('service.task');
        $task = $taskService->get((string)$params['public_id'], $authUser['user']);
        if (!$task) {
            return $this->error('TASK_NOT_FOUND', $this->t('common/messages.task_not_found'), 404, [
                'task' => [$this->t('common/messages.task_not_found')],
            ]);
        }

        /** @var CommentService $service */
        $service = $this->container->get('service.comment');
        $comment = $service->createByTask((string)$params['public_id'], $input, (int)$authUser['user']['id']);

        if (!$comment) {
            return $this->error('TASK_NOT_FOUND', $this->t('common/messages.task_not_found'), 404, [
                'task' => [$this->t('common/messages.task_not_found')],
            ]);
        }

        return $this->success('COMMENT_CREATED', $this->t('task/messages.comment_created'), $comment, 201);
    }

    public function bulkUpdate(): \Api\System\Library\Http\JsonResponse
    {
        $authUser = $this->user();
        if (!$authUser) {
            return $this->error('UNAUTHORIZED', $this->t('common/messages.unauthorized'), 401);
        }

        $input = $this->request()->allInput();
        $taskPublicIds = (array)($input['task_public_ids'] ?? []);
        $changes = (array)($input['changes'] ?? []);

        $errors = [];
        if ($taskPublicIds === []) {
            $errors['task_public_ids'][] = $this->t('task/messages.task_ids_required');
        }

        $hasChange = false;
        foreach (['status', 'priority', 'archived', 'assignee_user_public_id', 'add_tag_public_ids', 'remove_tag_public_ids'] as $key) {
            if (array_key_exists($key, $changes)) {
                $hasChange = true;
                break;
            }
        }
        if (!$hasChange) {
            $errors['changes'][] = $this->t('task/messages.changes_required');
        }

        $v = new Validator();
        $v->enum($changes, 'priority', ['low', 'normal', 'high', 'urgent'], $this->t('task/messages.invalid_priority'));
        if ($v->fails()) {
            $errors = array_merge($errors, $v->errors());
        }
        if (array_key_exists('status', $changes)) {
            $statusCode = trim((string)$changes['status']);
            if ($statusCode !== '' && !$this->isAllowedTaskStatus($statusCode)) {
                $errors['status'][] = $this->t('task/messages.invalid_status');
            }
        }

        if ($errors !== []) {
            return $this->error('VALIDATION_ERROR', $this->t('common/messages.validation_error'), 422, $errors);
        }

        /** @var TaskBulkService $service */
        $service = $this->container->get('service.task_bulk');
        $result = $service->apply($input, $authUser['user']);
        if ($result === 'TASK_IDS_REQUIRED') {
            return $this->error('VALIDATION_ERROR', $this->t('common/messages.validation_error'), 422, [
                'task_public_ids' => [$this->t('task/messages.task_ids_required')],
            ]);
        }
        if ($result === 'ASSIGNEE_NOT_FOUND') {
            return $this->error('ASSIGNEE_NOT_FOUND', $this->t('task/messages.assignee_not_found'), 404, [
                'assignee_user_public_id' => [$this->t('task/messages.assignee_not_found')],
            ]);
        }

        $this->invalidateCache('task');

        return $this->success('TASK_BULK_UPDATED', $this->t('task/messages.bulk_updated'), [
            'summary' => $result['summary'],
            'updated' => $result['updated'],
            'skipped' => $result['skipped'],
        ]);
    }

    public function move(array $params): \Api\System\Library\Http\JsonResponse
    {
        $authUser = $this->user();
        if (!$authUser) {
            return $this->error('UNAUTHORIZED', $this->t('common/messages.unauthorized'), 401);
        }

        $input = $this->request()->allInput();
        $errors = [];
        if (empty($input['to_status']) && empty($input['to_status_public_id'])) {
            $errors['to_status'][] = $this->t('task/messages.to_status_required');
        }
        if ($errors !== []) {
            return $this->error('VALIDATION_ERROR', $this->t('common/messages.validation_error'), 422, $errors);
        }

        /** @var TaskBoardService $service */
        $service = $this->container->get('service.task_board');
        $item = $service->move((string)$params['public_id'], $input, $authUser['user']);
        if ($item === null) {
            return $this->error('TASK_NOT_FOUND', $this->t('common/messages.task_not_found'), 404);
        }
        if ($item === 'ROW_VERSION_CONFLICT') {
            return $this->error('ROW_VERSION_CONFLICT', $this->t('task/messages.row_version_conflict'), 409);
        }
        if ($item === 'STATUS_REQUIRED') {
            return $this->error('VALIDATION_ERROR', $this->t('common/messages.validation_error'), 422, [
                'to_status' => [$this->t('task/messages.to_status_required')],
            ]);
        }
        if ($item === 'INVALID_STATUS') {
            return $this->error('INVALID_STATUS', $this->t('task/messages.invalid_status'), 422, [
                'to_status' => [$this->t('task/messages.invalid_status')],
            ]);
        }
        if ($item === 'WIP_LIMIT_EXCEEDED') {
            return $this->error('WIP_LIMIT_EXCEEDED', $this->t('task/messages.wip_limit_exceeded', 'WIP limit exceeded for this column'), 422);
        }

        $this->invalidateCache('task');

        return $this->success('TASK_MOVED', $this->t('task/messages.moved'), [
            'task' => $item,
        ], meta: [
            'row_version' => (int)($item['row_version'] ?? 1),
        ]);
    }

    private function fireWorkflowTrigger(string $trigger, array $task, array $actor, array $extra = []): void
    {
        try {
            // Load task tags
            $taskTagIds = [];
            try {
                $tagService = $this->container->get('service.tag');
                $tags = $tagService->listTaskTags((string)($task['public_id'] ?? ''), $actor);
                if (is_array($tags)) {
                    $taskTagIds = array_map(static fn(array $t): string => (string)($t['public_id'] ?? ''), $tags);
                }
            } catch (\Throwable) {
                $taskTagIds = [];
            }
            $wf = $this->container->get('service.workflow');
            $context = array_merge([
                'task_id' => (int)($task['id'] ?? 0),
                'task_public_id' => (string)($task['public_id'] ?? ''),
                'task_title' => (string)($task['title'] ?? ''),
                'task_status' => (string)($task['status_code'] ?? ''),
                'task_assignee_id' => (int)($task['assignee_user_id'] ?? 0),
                'project_id' => (int)($task['project_id'] ?? 0),
                'actor_id' => (int)($actor['id'] ?? 0),
                'actor_public_id' => (string)($actor['public_id'] ?? ''),
                'task_tags' => $taskTagIds,
            ], $extra);
            $wf->fireTrigger($trigger, $context);
        } catch (\Throwable) {}
    }

    private function isAllowedTaskStatus(string $statusCode): bool
    {
        if (in_array($statusCode, ['new', 'in_progress', 'blocked', 'done'], true)) {
            return true;
        }

        /** @var StatusRepository $statuses */
        $statuses = $this->container->get('repository.status');
        $status = $statuses->findByScopeAndCode('task', $statusCode);

        return $status !== null && (int)($status['is_active'] ?? 1) === 1;
    }
}
