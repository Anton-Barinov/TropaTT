<?php
declare(strict_types=1);

namespace Api\Controller\Ai;

use Api\Controller\Common\BaseController;
use Api\System\Library\Service\AiJobService;

final class AiJobController extends BaseController
{
    public function list(): \Api\System\Library\Http\JsonResponse
    {
        $auth = $this->user();
        if (!$auth) {
            return $this->error('UNAUTHORIZED', $this->t('common/messages.unauthorized'), 401);
        }
        if (!$this->canViewAiJobs($auth['user'])) {
            return $this->error('FORBIDDEN', $this->t('common/messages.forbidden'), 403, [
                'permission' => ['ai.view_cron_results'],
            ]);
        }

        /** @var AiJobService $service */
        $service = $this->container->get('service.ai_job');
        $result = $service->list($this->request()->allInput(), $auth['user']);

        return $this->success('AI_JOBS_LIST', $this->t('ai/messages.action_result'), [
            'items' => $result['items'],
        ], meta: $result['meta']);
    }

    public function get(array $params): \Api\System\Library\Http\JsonResponse
    {
        $auth = $this->user();
        if (!$auth) {
            return $this->error('UNAUTHORIZED', $this->t('common/messages.unauthorized'), 401);
        }
        if (!$this->canViewAiJobs($auth['user'])) {
            return $this->error('FORBIDDEN', $this->t('common/messages.forbidden'), 403, [
                'permission' => ['ai.view_cron_results'],
            ]);
        }

        /** @var AiJobService $service */
        $service = $this->container->get('service.ai_job');
        $item = $service->get(trim((string)($params['public_id'] ?? '')), $auth['user']);
        if (!$item) {
            return $this->error('AI_JOB_NOT_FOUND', $this->t('ai/messages.action_result'), 404, [
                'job' => ['AI_JOB_NOT_FOUND'],
            ]);
        }

        return $this->success('AI_JOB_DETAIL', $this->t('ai/messages.action_result'), [
            'job' => $item,
        ]);
    }

    public function retry(array $params): \Api\System\Library\Http\JsonResponse
    {
        $auth = $this->user();
        if (!$auth) {
            return $this->error('UNAUTHORIZED', $this->t('common/messages.unauthorized'), 401);
        }
        if (!$this->canManageAiJobs($auth['user'])) {
            return $this->error('FORBIDDEN', $this->t('common/messages.forbidden'), 403, [
                'permission' => ['ai.manage_cron_jobs'],
            ]);
        }

        $publicId = trim((string)($params['public_id'] ?? ''));
        if ($publicId === '') {
            return $this->error('VALIDATION_ERROR', $this->t('common/messages.validation_error'), 422, [
                'public_id' => [$this->t('common/messages.field_required')],
            ]);
        }

        return $this->withIdempotency(function () use ($publicId, $auth): \Api\System\Library\Http\JsonResponse {
            /** @var AiJobService $service */
            $service = $this->container->get('service.ai_job');
            $result = $service->retry($publicId, $auth['user']);
            if (!(bool)($result['ok'] ?? false)) {
                $code = (string)($result['code'] ?? 'AI_JOB_RETRY_FAILED');
                $status = match ($code) {
                    'AI_JOB_NOT_FOUND' => 404,
                    'AI_JOB_RETRY_NOT_ALLOWED' => 409,
                    default => 400,
                };
                return $this->error($code, $this->t('ai/messages.action_result'), $status, [
                    'job' => [$code],
                ]);
            }

            return $this->success('AI_JOB_RETRY_SCHEDULED', $this->t('ai/messages.action_result'), [
                'job' => (array)($result['job'] ?? []),
            ], 201);
        });
    }

    public function dryRun(array $params): \Api\System\Library\Http\JsonResponse
    {
        $auth = $this->user();
        if (!$auth) {
            return $this->error('UNAUTHORIZED', $this->t('common/messages.unauthorized'), 401);
        }
        if (!$this->canManageAiJobs($auth['user'])) {
            return $this->error('FORBIDDEN', $this->t('common/messages.forbidden'), 403, [
                'permission' => ['ai.manage_cron_jobs'],
            ]);
        }

        $jobCode = trim((string)($params['job_code'] ?? ''));
        if ($jobCode === '') {
            return $this->error('VALIDATION_ERROR', $this->t('common/messages.validation_error'), 422, [
                'job_code' => [$this->t('common/messages.field_required')],
            ]);
        }

        return $this->withIdempotency(function () use ($jobCode, $auth): \Api\System\Library\Http\JsonResponse {
            /** @var AiJobService $service */
            $service = $this->container->get('service.ai_job');
            $result = $service->dryRun($jobCode, $this->request()->allInput(), $auth['user']);
            if (!(bool)($result['ok'] ?? false)) {
                $code = (string)($result['code'] ?? 'AI_JOB_DRY_RUN_FAILED');
                $status = match ($code) {
                    'AI_JOB_CODE_NOT_ALLOWED' => 422,
                    default => 400,
                };
                return $this->error($code, $this->t('ai/messages.action_result'), $status, [
                    'job' => [$code],
                ]);
            }

            return $this->success('AI_JOB_DRY_RUN', $this->t('ai/messages.action_result'), [
                'dry_run' => (array)($result['dry_run'] ?? []),
            ]);
        });
    }

    public function runOnce(array $params): \Api\System\Library\Http\JsonResponse
    {
        $auth = $this->user();
        if (!$auth) {
            return $this->error('UNAUTHORIZED', $this->t('common/messages.unauthorized'), 401);
        }
        if (!$this->canManageAiJobs($auth['user'])) {
            return $this->error('FORBIDDEN', $this->t('common/messages.forbidden'), 403, [
                'permission' => ['ai.manage_cron_jobs'],
            ]);
        }

        $jobCode = trim((string)($params['job_code'] ?? ''));
        if ($jobCode === '') {
            return $this->error('VALIDATION_ERROR', $this->t('common/messages.validation_error'), 422, [
                'job_code' => [$this->t('common/messages.field_required')],
            ]);
        }

        return $this->withIdempotency(function () use ($jobCode, $auth): \Api\System\Library\Http\JsonResponse {
            /** @var AiJobService $service */
            $service = $this->container->get('service.ai_job');
            $result = $service->runOnce($jobCode, $this->request()->allInput(), $auth['user']);
            if (!(bool)($result['ok'] ?? false)) {
                $code = (string)($result['code'] ?? 'AI_JOB_RUN_ONCE_FAILED');
                $status = match ($code) {
                    'AI_JOB_CODE_NOT_ALLOWED' => 422,
                    'AI_DISABLED', 'AI_FEATURE_DISABLED', 'AI_PROVIDER_NOT_CONFIGURED', 'AI_JOB_ALREADY_QUEUED' => 409,
                    default => 400,
                };
                return $this->error($code, $this->t('ai/messages.action_result'), $status, [
                    'job' => [$code],
                ]);
            }

            return $this->success('AI_JOB_RUN_ONCE_SCHEDULED', $this->t('ai/messages.action_result'), [
                'job' => (array)($result['job'] ?? []),
            ], 201);
        });
    }

    /** @param array<string,mixed> $actor */
    private function canViewAiJobs(array $actor): bool
    {
        if ((bool)($actor['is_root'] ?? false)) {
            return true;
        }

        $roles = is_array($actor['roles'] ?? null) ? (array)$actor['roles'] : [];
        if (in_array('admin', $roles, true)) {
            return true;
        }

        $codes = is_array($actor['permission_codes'] ?? null) ? (array)$actor['permission_codes'] : [];
        return in_array('ai.admin', $codes, true) || in_array('ai.view_cron_results', $codes, true);
    }

    /** @param array<string,mixed> $actor */
    private function canManageAiJobs(array $actor): bool
    {
        if ((bool)($actor['is_root'] ?? false)) {
            return true;
        }

        $roles = is_array($actor['roles'] ?? null) ? (array)$actor['roles'] : [];
        if (in_array('admin', $roles, true)) {
            return true;
        }

        $codes = is_array($actor['permission_codes'] ?? null) ? (array)$actor['permission_codes'] : [];
        return in_array('ai.admin', $codes, true) || in_array('ai.manage_cron_jobs', $codes, true);
    }
}
