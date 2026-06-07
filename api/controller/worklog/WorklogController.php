<?php
declare(strict_types=1);

namespace Api\Controller\Worklog;

use Api\Controller\Common\BaseController;
use Api\System\Library\Service\WorklogService;
use Api\System\Library\Validation\Validator;

final class WorklogController extends BaseController
{
    public function list(): \Api\System\Library\Http\JsonResponse
    {
        $authUser = $this->user();
        if (!$authUser) {
            return $this->error('UNAUTHORIZED', $this->t('common/messages.unauthorized'), 401);
        }

        /** @var WorklogService $service */
        $service = $this->container->get('service.worklog');
        $result = $service->list($this->request()->allInput(), $authUser['user']);

        return $this->success('WORKLOG_LIST', $this->t('worklog/messages.list'), ['items' => $result['items']], meta: $result['meta']);
    }

    public function create(): \Api\System\Library\Http\JsonResponse
    {
        $authUser = $this->user();
        if (!$authUser) {
            return $this->error('UNAUTHORIZED', $this->t('common/messages.unauthorized'), 401);
        }

        $input = $this->request()->allInput();
        $v = new Validator();
        $v->require($input, 'minutes_spent', $this->t('common/messages.field_required'))
            ->date($input, 'logged_at', $this->t('common/messages.invalid_date'))
            ->maxLen($input, 'note', 8000, $this->t('worklog/messages.max_8000'));

        if ($v->fails()) {
            return $this->error('VALIDATION_ERROR', $this->t('common/messages.validation_error'), 422, $v->errors());
        }

        $minutes = (int)($input['minutes_spent'] ?? 0);
        if ($minutes <= 0) {
            return $this->error('VALIDATION_ERROR', $this->t('common/messages.validation_error'), 422, [
                'minutes_spent' => [$this->t('worklog/messages.minutes_positive')],
            ]);
        }

        /** @var WorklogService $service */
        $service = $this->container->get('service.worklog');
        $item = $service->create($input, $authUser['user']);
        if ($item === 'TASK_NOT_FOUND') {
            return $this->error('TASK_NOT_FOUND', $this->t('common/messages.task_not_found'), 404, [
                'task_public_id' => [$this->t('common/messages.task_not_found')],
            ]);
        }
        if ($item === 'FORBIDDEN') {
            return $this->error('FORBIDDEN', $this->t('common/messages.forbidden'), 403, [
                'permission' => [$this->t('worklog/messages.permission_task')],
            ]);
        }

        return $this->success('WORKLOG_CREATED', $this->t('worklog/messages.created'), ['worklog' => $item], 201);
    }

    public function get(array $params): \Api\System\Library\Http\JsonResponse
    {
        $authUser = $this->user();
        if (!$authUser) {
            return $this->error('UNAUTHORIZED', $this->t('common/messages.unauthorized'), 401);
        }

        /** @var WorklogService $service */
        $service = $this->container->get('service.worklog');
        $item = $service->get((string)$params['public_id'], $authUser['user']);
        if (!$item) {
            return $this->error('WORKLOG_NOT_FOUND', $this->t('worklog/messages.not_found'), 404, [
                'worklog' => [$this->t('worklog/messages.not_found')],
            ]);
        }

        return $this->success('WORKLOG_DETAIL', $this->t('worklog/messages.detail'), ['worklog' => $item]);
    }

    public function update(array $params): \Api\System\Library\Http\JsonResponse
    {
        $authUser = $this->user();
        if (!$authUser) {
            return $this->error('UNAUTHORIZED', $this->t('common/messages.unauthorized'), 401);
        }

        $input = $this->request()->allInput();
        $v = new Validator();
        $v->date($input, 'logged_at', $this->t('common/messages.invalid_date'))
            ->maxLen($input, 'note', 8000, $this->t('worklog/messages.max_8000'));
        if ($v->fails()) {
            return $this->error('VALIDATION_ERROR', $this->t('common/messages.validation_error'), 422, $v->errors());
        }
        if (array_key_exists('minutes_spent', $input) && (int)$input['minutes_spent'] <= 0) {
            return $this->error('VALIDATION_ERROR', $this->t('common/messages.validation_error'), 422, [
                'minutes_spent' => [$this->t('worklog/messages.minutes_positive')],
            ]);
        }

        /** @var WorklogService $service */
        $service = $this->container->get('service.worklog');
        $item = $service->update((string)$params['public_id'], $input, $authUser['user']);
        if ($item === null) {
            return $this->error('WORKLOG_NOT_FOUND', $this->t('worklog/messages.not_found'), 404, [
                'worklog' => [$this->t('worklog/messages.not_found')],
            ]);
        }
        if ($item === 'TASK_NOT_FOUND') {
            return $this->error('TASK_NOT_FOUND', $this->t('common/messages.task_not_found'), 404, [
                'task_public_id' => [$this->t('common/messages.task_not_found')],
            ]);
        }
        if ($item === 'FORBIDDEN') {
            return $this->error('FORBIDDEN', $this->t('common/messages.forbidden'), 403, [
                'permission' => [$this->t('worklog/messages.permission_worklog')],
            ]);
        }

        return $this->success('WORKLOG_UPDATED', $this->t('worklog/messages.updated'), ['worklog' => $item]);
    }

    public function delete(array $params): \Api\System\Library\Http\JsonResponse
    {
        $authUser = $this->user();
        if (!$authUser) {
            return $this->error('UNAUTHORIZED', $this->t('common/messages.unauthorized'), 401);
        }

        /** @var WorklogService $service */
        $service = $this->container->get('service.worklog');
        $ok = $service->delete((string)$params['public_id'], $authUser['user']);
        if ($ok === 'FORBIDDEN') {
            return $this->error('FORBIDDEN', $this->t('common/messages.forbidden'), 403, [
                'permission' => [$this->t('worklog/messages.permission_worklog')],
            ]);
        }
        if (!$ok) {
            return $this->error('WORKLOG_NOT_FOUND', $this->t('worklog/messages.not_found'), 404, [
                'worklog' => [$this->t('worklog/messages.not_found')],
            ]);
        }

        return $this->success('WORKLOG_DELETED', $this->t('worklog/messages.deleted'));
    }

    public function summary(): \Api\System\Library\Http\JsonResponse
    {
        $authUser = $this->user();
        if (!$authUser) {
            return $this->error('UNAUTHORIZED', $this->t('common/messages.unauthorized'), 401);
        }

        /** @var WorklogService $service */
        $service = $this->container->get('service.worklog');
        $result = $service->summary($this->request()->allInput(), $authUser['user']);

        return $this->success('WORKLOG_SUMMARY', $this->t('worklog/messages.summary'), $result);
    }

    public function earnings(): \Api\System\Library\Http\JsonResponse
    {
        $authUser = $this->user();
        if (!$authUser) {
            return $this->error('UNAUTHORIZED', $this->t('common/messages.unauthorized'), 401);
        }

        /** @var WorklogService $service */
        $service = $this->container->get('service.worklog');
        $result = $service->earnings($this->request()->allInput(), $authUser['user']);

        return $this->success('WORKLOG_EARNINGS', $this->t('worklog/messages.earnings'), $result);
    }

    public function taskSummary(array $params): \Api\System\Library\Http\JsonResponse
    {
        $authUser = $this->user();
        if (!$authUser) {
            return $this->error('UNAUTHORIZED', $this->t('common/messages.unauthorized'), 401);
        }

        /** @var WorklogService $service */
        $service = $this->container->get('service.worklog');
        $result = $service->taskSummaryByUser((string)$params['public_id'], $authUser['user']);
        if ($result === null) {
            return $this->error('TASK_NOT_FOUND', $this->t('common/messages.task_not_found'), 404);
        }

        return $this->success('WORKLOG_TASK_SUMMARY', $this->t('worklog/messages.task_summary'), $result);
    }

    public function matrix(): \Api\System\Library\Http\JsonResponse
    {
        $authUser = $this->user();
        if (!$authUser) {
            return $this->error('UNAUTHORIZED', $this->t('common/messages.unauthorized'), 401);
        }

        /** @var WorklogService $service */
        $service = $this->container->get('service.worklog');
        $result = $service->matrix($this->request()->allInput(), $authUser['user']);

        return $this->success('WORKLOG_MATRIX', $this->t('worklog/messages.matrix'), $result);
    }

    public function detail(): \Api\System\Library\Http\JsonResponse
    {
        $authUser = $this->user();
        if (!$authUser) {
            return $this->error('UNAUTHORIZED', $this->t('common/messages.unauthorized'), 401);
        }

        $input = $this->request()->allInput();
        $day = (string)($input['day'] ?? '');
        $userPublicId = (string)($input['user_public_id'] ?? '');

        if ($day === '' || $userPublicId === '') {
            return $this->error('VALIDATION_ERROR', $this->t('common/messages.validation_error'), 422);
        }

        /** @var WorklogService $service */
        $service = $this->container->get('service.worklog');
        $result = $service->detail($day, $userPublicId, $authUser['user']);

        return $this->success('WORKLOG_DETAIL', $this->t('worklog/messages.detail'), $result);
    }
}
