<?php
declare(strict_types=1);

namespace Api\Controller\Cycle;

use Api\Controller\Common\BaseController;
use Api\System\Library\Http\JsonResponse;
use Api\System\Library\Service\WorkCycleService;

final class WorkCycleController extends BaseController
{
    public function list(): JsonResponse
    {
        $authUser = $this->user();
        if (!$authUser) {
            return $this->error('UNAUTHORIZED', $this->t('common/messages.unauthorized'), 401);
        }

        /** @var WorkCycleService $service */
        $service = $this->container->get('service.work_cycle');

        $filters = $this->request()->allInput();
        unset($filters['route']);

        $result = $service->list($filters, $authUser['user']);

        return $this->success('CYCLE_LIST', $this->t('task/messages.cycle_list', 'Cycles'), [
            'items' => $result['items'],
        ], meta: $result['meta']);
    }

    public function create(): JsonResponse
    {
        $authUser = $this->user();
        if (!$authUser) {
            return $this->error('UNAUTHORIZED', $this->t('common/messages.unauthorized'), 401);
        }

        /** @var WorkCycleService $service */
        $service = $this->container->get('service.work_cycle');

        $input = $this->request()->allInput();
        unset($input['route']);

        $result = $service->create($input, $authUser['user']);

        if (is_string($result)) {
            return $this->mapError($result);
        }

        return $this->success('CYCLE_CREATED', $this->t('task/messages.cycle_created', 'Cycle created'), $result);
    }

    public function get(array $params): JsonResponse
    {
        $authUser = $this->user();
        if (!$authUser) {
            return $this->error('UNAUTHORIZED', $this->t('common/messages.unauthorized'), 401);
        }

        /** @var WorkCycleService $service */
        $service = $this->container->get('service.work_cycle');

        $result = $service->get((string)$params['public_id'], $authUser['user']);

        if (is_string($result)) {
            return $this->mapError($result);
        }

        return $this->success('CYCLE_GET', $this->t('task/messages.cycle_get', 'Cycle'), $result);
    }

    public function update(array $params): JsonResponse
    {
        $authUser = $this->user();
        if (!$authUser) {
            return $this->error('UNAUTHORIZED', $this->t('common/messages.unauthorized'), 401);
        }

        /** @var WorkCycleService $service */
        $service = $this->container->get('service.work_cycle');

        $input = $this->request()->allInput();
        unset($input['route']);

        $result = $service->update((string)$params['public_id'], $input, $authUser['user']);

        if (is_string($result)) {
            return $this->mapError($result);
        }

        return $this->success('CYCLE_UPDATED', $this->t('task/messages.cycle_updated', 'Cycle updated'), $result);
    }

    public function delete(array $params): JsonResponse
    {
        $authUser = $this->user();
        if (!$authUser) {
            return $this->error('UNAUTHORIZED', $this->t('common/messages.unauthorized'), 401);
        }

        /** @var WorkCycleService $service */
        $service = $this->container->get('service.work_cycle');

        $result = $service->delete((string)$params['public_id'], $authUser['user']);

        if (is_string($result)) {
            return $this->mapError($result);
        }

        return $this->success('CYCLE_DELETED', $this->t('task/messages.cycle_deleted', 'Cycle deleted'));
    }

    public function start(array $params): JsonResponse
    {
        $authUser = $this->user();
        if (!$authUser) {
            return $this->error('UNAUTHORIZED', $this->t('common/messages.unauthorized'), 401);
        }

        /** @var WorkCycleService $service */
        $service = $this->container->get('service.work_cycle');

        $input = $this->request()->allInput();
        unset($input['route']);

        $result = $service->start((string)$params['public_id'], $input, $authUser['user']);

        if (is_string($result)) {
            return $this->mapError($result);
        }

        return $this->success('CYCLE_STARTED', $this->t('task/messages.cycle_started', 'Cycle started'), $result);
    }

    public function complete(array $params): JsonResponse
    {
        $authUser = $this->user();
        if (!$authUser) {
            return $this->error('UNAUTHORIZED', $this->t('common/messages.unauthorized'), 401);
        }

        /** @var WorkCycleService $service */
        $service = $this->container->get('service.work_cycle');

        $input = $this->request()->allInput();
        unset($input['route']);

        $result = $service->complete((string)$params['public_id'], $input, $authUser['user']);

        if (is_string($result)) {
            return $this->mapError($result);
        }

        return $this->success('CYCLE_COMPLETED', $this->t('task/messages.cycle_completed', 'Cycle completed'), $result);
    }

    public function reopen(array $params): JsonResponse
    {
        $authUser = $this->user();
        if (!$authUser) {
            return $this->error('UNAUTHORIZED', $this->t('common/messages.unauthorized'), 401);
        }

        /** @var WorkCycleService $service */
        $service = $this->container->get('service.work_cycle');

        $input = $this->request()->allInput();
        unset($input['route']);

        $result = $service->reopen((string)$params['public_id'], $input, $authUser['user']);

        if (is_string($result)) {
            return $this->mapError($result);
        }

        return $this->success('CYCLE_REOPENED', $this->t('task/messages.cycle_reopened', 'Cycle reopened'), $result);
    }

    public function archive(array $params): JsonResponse
    {
        $authUser = $this->user();
        if (!$authUser) {
            return $this->error('UNAUTHORIZED', $this->t('common/messages.unauthorized'), 401);
        }

        /** @var WorkCycleService $service */
        $service = $this->container->get('service.work_cycle');

        $input = $this->request()->allInput();
        unset($input['route']);

        $result = $service->archive((string)$params['public_id'], $input, $authUser['user']);

        if (is_string($result)) {
            return $this->mapError($result);
        }

        return $this->success('CYCLE_ARCHIVED', $this->t('task/messages.cycle_archived', 'Cycle archived'));
    }

    public function tasks(array $params): JsonResponse
    {
        $authUser = $this->user();
        if (!$authUser) {
            return $this->error('UNAUTHORIZED', $this->t('common/messages.unauthorized'), 401);
        }

        /** @var WorkCycleService $service */
        $service = $this->container->get('service.work_cycle');

        $filters = $this->request()->allInput();
        unset($filters['route']);

        $result = $service->tasks((string)$params['public_id'], $filters, $authUser['user']);

        if (is_string($result)) {
            return $this->mapError($result);
        }

        return $this->success('CYCLE_TASKS', $this->t('task/messages.cycle_tasks', 'Cycle tasks'), $result);
    }

    public function addTasks(array $params): JsonResponse
    {
        $authUser = $this->user();
        if (!$authUser) {
            return $this->error('UNAUTHORIZED', $this->t('common/messages.unauthorized'), 401);
        }

        /** @var WorkCycleService $service */
        $service = $this->container->get('service.work_cycle');

        $input = $this->request()->allInput();
        unset($input['route']);

        $result = $service->addTasks((string)$params['public_id'], $input, $authUser['user']);

        if (is_string($result)) {
            return $this->mapError($result);
        }

        return $this->success('CYCLE_TASKS_ADDED', $this->t('task/messages.cycle_tasks_added', 'Tasks added to cycle'), $result);
    }

    public function removeTask(array $params): JsonResponse
    {
        $authUser = $this->user();
        if (!$authUser) {
            return $this->error('UNAUTHORIZED', $this->t('common/messages.unauthorized'), 401);
        }

        /** @var WorkCycleService $service */
        $service = $this->container->get('service.work_cycle');

        $result = $service->removeTask((string)$params['public_id'], (string)$params['task_public_id'], $authUser['user']);

        if (is_string($result)) {
            return $this->mapError($result);
        }

        return $this->success('CYCLE_TASK_REMOVED', $this->t('task/messages.cycle_task_removed', 'Task removed from cycle'));
    }

    public function summary(array $params): JsonResponse
    {
        $authUser = $this->user();
        if (!$authUser) {
            return $this->error('UNAUTHORIZED', $this->t('common/messages.unauthorized'), 401);
        }

        /** @var WorkCycleService $service */
        $service = $this->container->get('service.work_cycle');

        $result = $service->summary((string)$params['public_id'], $authUser['user']);

        if (is_string($result)) {
            return $this->mapError($result);
        }

        return $this->success('CYCLE_SUMMARY', $this->t('task/messages.cycle_summary', 'Cycle summary'), $result);
    }

    public function transferUnfinished(array $params): JsonResponse
    {
        $authUser = $this->user();
        if (!$authUser) {
            return $this->error('UNAUTHORIZED', $this->t('common/messages.unauthorized'), 401);
        }

        /** @var WorkCycleService $service */
        $service = $this->container->get('service.work_cycle');

        $input = $this->request()->allInput();
        unset($input['route']);

        $result = $service->transferUnfinished((string)$params['public_id'], $input, $authUser['user']);

        if (is_string($result)) {
            return $this->mapError($result);
        }

        return $this->success('CYCLE_UNFINISHED_TRANSFERRED', $this->t('task/messages.cycle_unfinished_transferred', 'Unfinished tasks transferred'), $result);
    }

    private function mapError(string $code): JsonResponse
    {
        return match ($code) {
            'CYCLE_NOT_FOUND',
            'CYCLE_PROJECT_NOT_FOUND',
            'CYCLE_OWNER_NOT_FOUND',
            'CYCLE_TASK_NOT_FOUND',
            'CYCLE_TARGET_CYCLE_NOT_FOUND' => $this->error($code, $this->t('common/messages.not_found', 'Not found'), 404),

            'CYCLE_FORBIDDEN' => $this->error($code, $this->t('common/messages.forbidden', 'Forbidden'), 403),

            'CYCLE_TASK_ALREADY_IN_ACTIVE_CYCLE',
            'ROW_VERSION_CONFLICT' => $this->error($code, $this->t('common/messages.conflict', 'Conflict'), 409, ['conflict' => [$code]]),

            'CYCLE_TARGET_CYCLE_PROJECT_MISMATCH' => $this->error($code, $this->t('common/messages.validation_error', 'Target cycle must belong to the same project'), 422, ['target_cycle_public_id' => [$code]]),

            default => $this->error($code, $this->t('common/messages.validation_error', 'Validation error'), 422, ['validation' => [$code]]),
        };
    }
}
