<?php
declare(strict_types=1);

namespace Api\Controller\Worklog;

use Api\Controller\Common\BaseController;
use Api\System\Library\Security\FinancialFieldPolicy;
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

        $cache = $this->cacheApi();
        if ($cache !== null) {
            $input = $this->request()->allInput();
            ksort($input);
            $cacheKey = 'list:' . $this->cacheUserId() . ':' . hash('sha256', json_encode($input));
            $result = $cache->remember('worklog', $cacheKey, 60, function () use ($input, $authUser) {
                /** @var WorklogService $service */
                $service = $this->container->get('service.worklog');
                return $service->list($input, $authUser['user']);
            });
        } else {
            /** @var WorklogService $service */
            $service = $this->container->get('service.worklog');
            $result = $service->list($this->request()->allInput(), $authUser['user']);
        }

        $policy = new FinancialFieldPolicy();
        $result['items'] = $policy->filterRows($result['items'], $authUser['user'], 'worklog.list');

        return $this->success('WORKLOG_LIST', $this->t('worklog/messages.list'), ['items' => $result['items']], meta: $result['meta']);
    }

    public function create(): \Api\System\Library\Http\JsonResponse
    {
        $authUser = $this->user();
        if (!$authUser) {
            return $this->error('UNAUTHORIZED', $this->t('common/messages.unauthorized'), 401);
        }

        $rl = $this->checkIpRateLimit('wl_create', 30, 60, 300);
        if ($rl['blocked'] === true) {
            return $this->error('RATE_LIMITED', $this->t('common/messages.rate_limited'), 429, [], [
                'retry_after' => $rl['retry_after'],
            ]);
        }

        $input = $this->request()->allInput();
        $v = new Validator();
        $v->require($input, 'task_public_id', $this->t('common/messages.field_required'))
            ->require($input, 'minutes_spent', $this->t('common/messages.field_required'))
            ->require($input, 'activity_code', $this->t('common/messages.field_required'))
            ->date($input, 'logged_at', $this->t('common/messages.invalid_date'))
            ->date($input, 'started_at', $this->t('worklog/messages.invalid_interval_time'))
            ->date($input, 'ended_at', $this->t('worklog/messages.invalid_interval_time'))
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

        $intervalError = $this->validateIntervalPair($input);
        if ($intervalError !== null) {
            return $this->error('VALIDATION_ERROR', $this->t('common/messages.validation_error'), 422, $intervalError);
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
        if ($item === 'RATE_PERIOD_LOCKED') {
            return $this->error('RATE_PERIOD_LOCKED', $this->t('worklog/messages.period_locked'), 403);
        }

        $this->invalidateCache('worklog');
        $this->fireWorklogTrigger($item, $authUser['user']);

        $item = (new FinancialFieldPolicy())->filterRow($item, $authUser['user'], 'worklog.create');
        return $this->success('WORKLOG_CREATED', $this->t('worklog/messages.created'), ['worklog' => $item], 201);
    }

    /**
     * Fire the worklog_logged automation trigger after a successful create or
     * update, mirroring TaskController::fireWorkflowTrigger. The context carries
     * everything worklog rules need: executor, task, minutes, exact interval and
     * the UTC day used for the day/continuous windows.
     */
    private function fireWorklogTrigger(array $worklog, array $actor, int $previousMinutes = 0): void
    {
        try {
            $wf = $this->container->get('service.workflow');
            // The day window in WorklogRepository filters on logged_at, so the
            // same field must define the day (matches analytics grouping too).
            $day = gmdate('Y-m-d', (int)strtotime((string)($worklog['logged_at'] ?? 'now')));
            $context = [
                'worklog_id' => (int)($worklog['id'] ?? 0),
                'worklog_public_id' => (string)($worklog['public_id'] ?? ''),
                'task_id' => (int)($worklog['task_id'] ?? 0),
                'task_public_id' => (string)($worklog['task_public_id'] ?? ''),
                'task_title' => (string)($worklog['task_title'] ?? ''),
                'user_id' => (int)($worklog['user_id'] ?? 0),
                'user_public_id' => (string)($worklog['user_public_id'] ?? ''),
                'user_full_name' => (string)($worklog['user_full_name'] ?? $worklog['user_login'] ?? ''),
                'minutes_spent' => (int)($worklog['minutes_spent'] ?? 0),
                'previous_minutes_spent' => $previousMinutes,
                'started_at' => (string)($worklog['started_at'] ?? ''),
                'ended_at' => (string)($worklog['ended_at'] ?? ''),
                'day' => $day,
                'actor_id' => (int)($actor['id'] ?? 0),
                'actor_public_id' => (string)($actor['public_id'] ?? ''),
            ];
            $wf->fireTrigger('worklog_logged', $context);
        } catch (\Throwable $e) {
            error_log('[WorklogController::fireWorklogTrigger] ' . $e->getMessage());
        }
    }

    /**
     * Exact timer intervals must arrive as a pair (started_at + ended_at) and
     * the end must be strictly after the start. Returns the error map to send
     * back or null when the pair is valid / absent.
     *
     * @return array<string, array<int, string>>|null
     */
    private function validateIntervalPair(array $input): ?array
    {
        $hasStart = isset($input['started_at']) && $input['started_at'] !== '' && $input['started_at'] !== null;
        $hasEnd = isset($input['ended_at']) && $input['ended_at'] !== '' && $input['ended_at'] !== null;
        if ($hasStart !== $hasEnd) {
            return [
                'started_at' => [$this->t('worklog/messages.interval_pair_required')],
                'ended_at' => [$this->t('worklog/messages.interval_pair_required')],
            ];
        }
        if ($hasStart && strtotime((string)$input['ended_at']) <= strtotime((string)$input['started_at'])) {
            return [
                'ended_at' => [$this->t('worklog/messages.interval_end_after_start')],
            ];
        }

        return null;
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

        $item = (new FinancialFieldPolicy())->filterRow($item, $authUser['user'], 'worklog.detail');

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
            ->date($input, 'started_at', $this->t('worklog/messages.invalid_interval_time'))
            ->date($input, 'ended_at', $this->t('worklog/messages.invalid_interval_time'))
            ->maxLen($input, 'note', 8000, $this->t('worklog/messages.max_8000'));
        if ($v->fails()) {
            return $this->error('VALIDATION_ERROR', $this->t('common/messages.validation_error'), 422, $v->errors());
        }
        $intervalError = $this->validateIntervalPair($input);
        if ($intervalError !== null) {
            return $this->error('VALIDATION_ERROR', $this->t('common/messages.validation_error'), 422, $intervalError);
        }
        if (array_key_exists('minutes_spent', $input) && (int)$input['minutes_spent'] <= 0) {
            return $this->error('VALIDATION_ERROR', $this->t('common/messages.validation_error'), 422, [
                'minutes_spent' => [$this->t('worklog/messages.minutes_positive')],
            ]);
        }

        /** @var WorklogService $service */
        $service = $this->container->get('service.worklog');
        $previousMinutes = 0;
        try {
            $existingRow = $this->container->get('repository.worklog')->findByPublicId((string)$params['public_id']);
            $previousMinutes = (int)($existingRow['minutes_spent'] ?? 0);
        } catch (\Throwable $e) {
            error_log('[WorklogController::update] worklog lookup failed: ' . $e->getMessage());
        }
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
        if ($item === 'RATE_PERIOD_LOCKED') {
            return $this->error('RATE_PERIOD_LOCKED', $this->t('worklog/messages.period_locked'), 403);
        }

        $this->invalidateCache('worklog');
        $this->fireWorklogTrigger($item, $authUser['user'], $previousMinutes);

        $item = (new FinancialFieldPolicy())->filterRow($item, $authUser['user'], 'worklog.update');
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

        $this->invalidateCache('worklog');

        return $this->success('WORKLOG_DELETED', $this->t('worklog/messages.deleted'));
    }

    public function summary(): \Api\System\Library\Http\JsonResponse
    {
        $authUser = $this->user();
        if (!$authUser) {
            return $this->error('UNAUTHORIZED', $this->t('common/messages.unauthorized'), 401);
        }

        $cache = $this->cacheApi();
        if ($cache !== null) {
            $input = $this->request()->allInput();
            ksort($input);
            $cacheKey = 'summary:' . $this->cacheUserId() . ':' . hash('sha256', json_encode($input));
            $result = $cache->remember('worklog', $cacheKey, 60, function () use ($input, $authUser) {
                /** @var WorklogService $service */
                $service = $this->container->get('service.worklog');
                return $service->summary($input, $authUser['user']);
            });
        } else {
            /** @var WorklogService $service */
            $service = $this->container->get('service.worklog');
            $result = $service->summary($this->request()->allInput(), $authUser['user']);
        }

        return $this->success('WORKLOG_SUMMARY', $this->t('worklog/messages.summary'), $result);
    }

    public function earnings(): \Api\System\Library\Http\JsonResponse
    {
        $authUser = $this->user();
        if (!$authUser) {
            return $this->error('UNAUTHORIZED', $this->t('common/messages.unauthorized'), 401);
        }

        $cache = $this->cacheApi();
        if ($cache !== null) {
            $input = $this->request()->allInput();
            ksort($input);
            $cacheKey = 'earnings:' . $this->cacheUserId() . ':' . hash('sha256', json_encode($input));
            $result = $cache->remember('worklog', $cacheKey, 60, function () use ($input, $authUser) {
                /** @var WorklogService $service */
                $service = $this->container->get('service.worklog');
                return $service->earnings($input, $authUser['user']);
            });
        } else {
            /** @var WorklogService $service */
            $service = $this->container->get('service.worklog');
            $result = $service->earnings($this->request()->allInput(), $authUser['user']);
        }

        // Filter financial fields through the unified policy (TZ 6.4)
        $policy = new FinancialFieldPolicy();
        $result['items'] = $policy->filterRows($result['items'], $authUser['user'], 'worklog.earnings');

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

        // Filter financial fields through the unified policy (TZ 6.4)
        $policy = new FinancialFieldPolicy();
        $result['user_breakdown'] = $policy->filterRows($result['user_breakdown'], $authUser['user'], 'worklog.task_summary');

        return $this->success('WORKLOG_TASK_SUMMARY', $this->t('worklog/messages.task_summary'), $result);
    }

    public function matrix(): \Api\System\Library\Http\JsonResponse
    {
        $authUser = $this->user();
        if (!$authUser) {
            return $this->error('UNAUTHORIZED', $this->t('common/messages.unauthorized'), 401);
        }

        $cache = $this->cacheApi();
        if ($cache !== null) {
            $input = $this->request()->allInput();
            ksort($input);
            $cacheKey = 'matrix:' . $this->cacheUserId() . ':' . hash('sha256', json_encode($input));
            $result = $cache->remember('worklog', $cacheKey, 60, function () use ($input, $authUser) {
                /** @var WorklogService $service */
                $service = $this->container->get('service.worklog');
                return $service->matrix($input, $authUser['user']);
            });
        } else {
            /** @var WorklogService $service */
            $service = $this->container->get('service.worklog');
            $result = $service->matrix($this->request()->allInput(), $authUser['user']);
        }

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
        $projectPublicId = (string)($input['project_public_id'] ?? '');

        if ($day === '' || $userPublicId === '') {
            return $this->error('VALIDATION_ERROR', $this->t('common/messages.validation_error'), 422);
        }

        $cache = $this->cacheApi();
        if ($cache !== null) {
            ksort($input);
            $cacheKey = 'detail:' . $this->cacheUserId() . ':' . hash('sha256', json_encode($input));
            $result = $cache->remember('worklog', $cacheKey, 60, function () use ($input, $authUser) {
                $day = (string)($input['day'] ?? '');
                $userPublicId = (string)($input['user_public_id'] ?? '');
                $projectPublicId = (string)($input['project_public_id'] ?? '');
                /** @var WorklogService $service */
                $service = $this->container->get('service.worklog');
                return $service->detail($day, $userPublicId, $projectPublicId ?: null, $authUser['user']);
            });
        } else {
            /** @var WorklogService $service */
            $service = $this->container->get('service.worklog');
            $result = $service->detail($day, $userPublicId, $projectPublicId ?: null, $authUser['user']);
        }

        return $this->success('WORKLOG_DETAIL', $this->t('worklog/messages.detail'), $result);
    }
}
