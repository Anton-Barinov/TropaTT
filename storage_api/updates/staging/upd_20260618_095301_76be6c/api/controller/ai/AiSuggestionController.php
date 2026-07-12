<?php
declare(strict_types=1);

namespace Api\Controller\Ai;

use Api\Controller\Common\BaseController;
use Api\System\Library\Service\AiSuggestionService;

final class AiSuggestionController extends BaseController
{
    public function createTaskSummary(array $params): \Api\System\Library\Http\JsonResponse
    {
        $auth = $this->user();
        if (!$auth) {
            return $this->error('UNAUTHORIZED', $this->t('common/messages.unauthorized'), 401);
        }

        $taskPublicId = trim((string)($params['task_public_id'] ?? ''));
        if ($taskPublicId === '') {
            return $this->error('VALIDATION_ERROR', $this->t('common/messages.validation_error'), 422, [
                'task_public_id' => [$this->t('common/messages.field_required')],
            ]);
        }

        return $this->withIdempotency(function () use ($taskPublicId, $auth): \Api\System\Library\Http\JsonResponse {
            /** @var AiSuggestionService $service */
            $service = $this->container->get('service.ai_suggestion');
            $result = $service->createTaskSummary($taskPublicId, $this->request()->allInput(), $auth['user']);
            if (!(bool)($result['ok'] ?? false)) {
                $code = $this->normalizeAiCode((string)($result['code'] ?? 'AI_SUGGESTION_CREATE_FAILED'));
                $status = match ($code) {
                    'TASK_NOT_FOUND' => 404,
                    'AI_DISABLED', 'AI_FEATURE_DISABLED', 'AI_PROVIDER_NOT_CONFIGURED' => 409,
                    'AI_INTENT_DISABLED' => 409,
                    'AI_RATE_LIMITED' => 429,
                    'AI_COST_LIMIT_EXCEEDED' => 409,
                    'AI_SCHEMA_VALIDATION_FAILED' => 422,
                    'AI_ACTION_TYPE_NOT_ALLOWED' => 422,
                    'AI_SCOPE_PUBLIC_ID_INVALID' => 422,
                    'FORBIDDEN' => 403,
                    'AI_PROVIDER_TIMEOUT' => 504,
                    'AI_PROVIDER_AUTH_FAILED', 'AI_PROVIDER_UNAVAILABLE' => 502,
                    default => 400,
                };
                return $this->error($code, $this->t('ai/messages.suggestion_create_failed'), $status, [
                    'ai' => [$code],
                ], meta: $this->aiErrorMeta($result, $code));
            }

            return $this->success('AI_SUGGESTION_CREATED', $this->t('ai/messages.suggestion_created'), [
                'suggestion' => $result['suggestion'],
                'job_public_id' => (string)($result['job_public_id'] ?? ''),
            ], 201);
        });
    }

    public function createTaskDecompose(array $params): \Api\System\Library\Http\JsonResponse
    {
        return $this->createTaskIntent($params, 'createTaskDecomposition');
    }

    public function createTaskChecklist(array $params): \Api\System\Library\Http\JsonResponse
    {
        return $this->createTaskIntent($params, 'createTaskChecklist');
    }

    public function createTaskQuality(array $params): \Api\System\Library\Http\JsonResponse
    {
        return $this->createTaskIntent($params, 'createTaskQuality');
    }

    public function createTaskNextAction(array $params): \Api\System\Library\Http\JsonResponse
    {
        return $this->createTaskIntent($params, 'createTaskNextAction');
    }

    public function createTaskCommentDraft(array $params): \Api\System\Library\Http\JsonResponse
    {
        return $this->createTaskIntent($params, 'createTaskCommentDraft');
    }

    public function createProjectSummary(array $params): \Api\System\Library\Http\JsonResponse
    {
        return $this->createScopedIntent($params, 'project_public_id', 'createProjectSummary');
    }

    public function createProjectRisks(array $params): \Api\System\Library\Http\JsonResponse
    {
        return $this->createScopedIntent($params, 'project_public_id', 'createProjectRisks');
    }

    public function createProjectClientReport(array $params): \Api\System\Library\Http\JsonResponse
    {
        return $this->createScopedIntent($params, 'project_public_id', 'createProjectClientReport');
    }

    public function createClientSummary(array $params): \Api\System\Library\Http\JsonResponse
    {
        return $this->createScopedIntent($params, 'client_public_id', 'createClientSummary');
    }

    public function createClientMeetingPrep(array $params): \Api\System\Library\Http\JsonResponse
    {
        return $this->createScopedIntent($params, 'client_public_id', 'createClientMeetingPrep');
    }

    public function createClientDataQuality(array $params): \Api\System\Library\Http\JsonResponse
    {
        return $this->createScopedIntent($params, 'client_public_id', 'createClientDataQuality');
    }

    public function createClientSafeReport(array $params): \Api\System\Library\Http\JsonResponse
    {
        return $this->createScopedIntent($params, 'client_public_id', 'createClientSafeReport');
    }

    public function createCalendarAgenda(array $params): \Api\System\Library\Http\JsonResponse
    {
        return $this->createScopedIntent($params, 'event_public_id', 'createCalendarEventAgenda');
    }

    public function createDashboardDigest(): \Api\System\Library\Http\JsonResponse
    {
        $auth = $this->user();
        if (!$auth) {
            return $this->error('UNAUTHORIZED', $this->t('common/messages.unauthorized'), 401);
        }

        return $this->withIdempotency(function () use ($auth): \Api\System\Library\Http\JsonResponse {
            /** @var AiSuggestionService $service */
            $service = $this->container->get('service.ai_suggestion');
            $result = $service->createDashboardDigest($this->request()->allInput(), $auth['user']);
            if (!(bool)($result['ok'] ?? false)) {
                $code = $this->normalizeAiCode((string)($result['code'] ?? 'AI_SUGGESTION_CREATE_FAILED'));
                $status = match ($code) {
                    'AI_DISABLED', 'AI_FEATURE_DISABLED', 'AI_PROVIDER_NOT_CONFIGURED', 'AI_INTENT_DISABLED', 'AI_PREFERENCES_DAILY_PLAN_DISABLED' => 409,
                    'AI_RATE_LIMITED' => 429,
                    'AI_COST_LIMIT_EXCEEDED' => 409,
                    'AI_ACTION_TYPE_NOT_ALLOWED', 'AI_SCHEMA_VALIDATION_FAILED', 'AI_SCOPE_PUBLIC_ID_INVALID' => 422,
                    'FORBIDDEN' => 403,
                    'AI_PROVIDER_TIMEOUT' => 504,
                    'AI_PROVIDER_AUTH_FAILED', 'AI_PROVIDER_UNAVAILABLE' => 502,
                    default => 400,
                };
                return $this->error($code, $this->t('ai/messages.suggestion_create_failed'), $status, [
                    'ai' => [$code],
                ], meta: $this->aiErrorMeta($result, $code));
            }

            return $this->success('AI_SUGGESTION_CREATED', $this->t('ai/messages.suggestion_created'), [
                'suggestion' => $result['suggestion'],
                'job_public_id' => (string)($result['job_public_id'] ?? ''),
            ], 201);
        });
    }

    public function createAnalyticsKpiExplanation(): \Api\System\Library\Http\JsonResponse
    {
        return $this->createActorScopedIntent('createAnalyticsKpiExplanation');
    }

    public function createAnalyticsRisksExplanation(): \Api\System\Library\Http\JsonResponse
    {
        return $this->createActorScopedIntent('createAnalyticsRisksExplanation');
    }

    public function createAnalyticsTeamWorkloadSummary(): \Api\System\Library\Http\JsonResponse
    {
        return $this->createActorScopedIntent('createAnalyticsTeamWorkloadSummary');
    }

    public function createAdminLogReview(): \Api\System\Library\Http\JsonResponse
    {
        return $this->createActorScopedIntent('createAdminLogReview');
    }

    public function createWebhookHealthReview(): \Api\System\Library\Http\JsonResponse
    {
        return $this->createActorScopedIntent('createWebhookHealthReview');
    }

    public function createWorkflowRuleAudit(): \Api\System\Library\Http\JsonResponse
    {
        return $this->createActorScopedIntent('createWorkflowRuleAudit');
    }

    public function createMyDayPlan(): \Api\System\Library\Http\JsonResponse
    {
        $auth = $this->user();
        if (!$auth) {
            return $this->error('UNAUTHORIZED', $this->t('common/messages.unauthorized'), 401);
        }

        return $this->withIdempotency(function () use ($auth): \Api\System\Library\Http\JsonResponse {
            /** @var AiSuggestionService $service */
            $service = $this->container->get('service.ai_suggestion');
            $result = $service->createMyDayPlan($this->request()->allInput(), $auth['user']);
            if (!(bool)($result['ok'] ?? false)) {
                $code = $this->normalizeAiCode((string)($result['code'] ?? 'AI_SUGGESTION_CREATE_FAILED'));
                $status = match ($code) {
                    'AI_DISABLED', 'AI_FEATURE_DISABLED', 'AI_PROVIDER_NOT_CONFIGURED', 'AI_INTENT_DISABLED', 'AI_PREFERENCES_DAILY_PLAN_DISABLED' => 409,
                    'AI_RATE_LIMITED' => 429,
                    'AI_COST_LIMIT_EXCEEDED' => 409,
                    'AI_ACTION_TYPE_NOT_ALLOWED', 'AI_SCHEMA_VALIDATION_FAILED', 'AI_SCOPE_PUBLIC_ID_INVALID' => 422,
                    'FORBIDDEN' => 403,
                    'AI_PROVIDER_TIMEOUT' => 504,
                    'AI_PROVIDER_AUTH_FAILED', 'AI_PROVIDER_UNAVAILABLE' => 502,
                    default => 400,
                };
                return $this->error($code, $this->t('ai/messages.suggestion_create_failed'), $status, [
                    'ai' => [$code],
                ], meta: $this->aiErrorMeta($result, $code));
            }

            return $this->success('AI_SUGGESTION_CREATED', $this->t('ai/messages.suggestion_created'), [
                'suggestion' => $result['suggestion'],
                'job_public_id' => (string)($result['job_public_id'] ?? ''),
            ], 201);
        });
    }

    public function createMyWeekPlan(): \Api\System\Library\Http\JsonResponse
    {
        $auth = $this->user();
        if (!$auth) {
            return $this->error('UNAUTHORIZED', $this->t('common/messages.unauthorized'), 401);
        }

        return $this->withIdempotency(function () use ($auth): \Api\System\Library\Http\JsonResponse {
            /** @var AiSuggestionService $service */
            $service = $this->container->get('service.ai_suggestion');
            $result = $service->createMyWeekPlan($this->request()->allInput(), $auth['user']);
            if (!(bool)($result['ok'] ?? false)) {
                $code = $this->normalizeAiCode((string)($result['code'] ?? 'AI_SUGGESTION_CREATE_FAILED'));
                $status = match ($code) {
                    'AI_DISABLED', 'AI_FEATURE_DISABLED', 'AI_PROVIDER_NOT_CONFIGURED', 'AI_INTENT_DISABLED' => 409,
                    'AI_RATE_LIMITED' => 429,
                    'AI_COST_LIMIT_EXCEEDED' => 409,
                    'AI_ACTION_TYPE_NOT_ALLOWED', 'AI_SCHEMA_VALIDATION_FAILED', 'AI_SCOPE_PUBLIC_ID_INVALID' => 422,
                    'FORBIDDEN' => 403,
                    'AI_PROVIDER_TIMEOUT' => 504,
                    'AI_PROVIDER_AUTH_FAILED', 'AI_PROVIDER_UNAVAILABLE' => 502,
                    default => 400,
                };
                return $this->error($code, $this->t('ai/messages.suggestion_create_failed'), $status, [
                    'ai' => [$code],
                ], meta: $this->aiErrorMeta($result, $code));
            }

            return $this->success('AI_SUGGESTION_CREATED', $this->t('ai/messages.suggestion_created'), [
                'suggestion' => $result['suggestion'],
                'job_public_id' => (string)($result['job_public_id'] ?? ''),
            ], 201);
        });
    }

    public function createTaskListPriority(): \Api\System\Library\Http\JsonResponse
    {
        $auth = $this->user();
        if (!$auth) {
            return $this->error('UNAUTHORIZED', $this->t('common/messages.unauthorized'), 401);
        }

        return $this->withIdempotency(function () use ($auth): \Api\System\Library\Http\JsonResponse {
            /** @var AiSuggestionService $service */
            $service = $this->container->get('service.ai_suggestion');
            $result = $service->createTaskListPriority($this->request()->allInput(), $auth['user']);
            if (!(bool)($result['ok'] ?? false)) {
                $code = $this->normalizeAiCode((string)($result['code'] ?? 'AI_SUGGESTION_CREATE_FAILED'));
                $status = match ($code) {
                    'AI_DISABLED', 'AI_FEATURE_DISABLED', 'AI_PROVIDER_NOT_CONFIGURED', 'AI_INTENT_DISABLED' => 409,
                    'AI_RATE_LIMITED' => 429,
                    'AI_COST_LIMIT_EXCEEDED' => 409,
                    'AI_ACTION_TYPE_NOT_ALLOWED', 'AI_SCHEMA_VALIDATION_FAILED', 'AI_SCOPE_PUBLIC_ID_INVALID', 'AI_TASK_LIST_EMPTY' => 422,
                    'FORBIDDEN' => 403,
                    'AI_PROVIDER_TIMEOUT' => 504,
                    'AI_PROVIDER_AUTH_FAILED', 'AI_PROVIDER_UNAVAILABLE' => 502,
                    default => 400,
                };
                return $this->error($code, $this->t('ai/messages.suggestion_create_failed'), $status, [
                    'ai' => [$code],
                ], meta: $this->aiErrorMeta($result, $code));
            }

            return $this->success('AI_SUGGESTION_CREATED', $this->t('ai/messages.suggestion_created'), [
                'suggestion' => $result['suggestion'],
                'job_public_id' => (string)($result['job_public_id'] ?? ''),
            ], 201);
        });
    }

    public function list(): \Api\System\Library\Http\JsonResponse
    {
        $auth = $this->user();
        if (!$auth) {
            return $this->error('UNAUTHORIZED', $this->t('common/messages.unauthorized'), 401);
        }

        /** @var AiSuggestionService $service */
        $service = $this->container->get('service.ai_suggestion');
        $result = $service->list($this->request()->allInput(), $auth['user']);

        return $this->success('AI_SUGGESTION_LIST', $this->t('ai/messages.suggestion_list'), [
            'items' => $result['items'],
        ], meta: $result['meta']);
    }

    public function get(array $params): \Api\System\Library\Http\JsonResponse
    {
        $auth = $this->user();
        if (!$auth) {
            return $this->error('UNAUTHORIZED', $this->t('common/messages.unauthorized'), 401);
        }

        /** @var AiSuggestionService $service */
        $service = $this->container->get('service.ai_suggestion');
        $item = $service->get(trim((string)($params['public_id'] ?? '')), $auth['user']);
        if (!$item) {
            return $this->error('AI_SUGGESTION_NOT_FOUND', $this->t('ai/messages.suggestion_not_found'), 404, [
                'suggestion' => [$this->t('ai/messages.suggestion_not_found')],
            ]);
        }

        return $this->success('AI_SUGGESTION_DETAIL', $this->t('ai/messages.suggestion_detail'), [
            'suggestion' => $item,
        ]);
    }

    public function dismiss(array $params): \Api\System\Library\Http\JsonResponse
    {
        $auth = $this->user();
        if (!$auth) {
            return $this->error('UNAUTHORIZED', $this->t('common/messages.unauthorized'), 401);
        }

        return $this->withIdempotency(function () use ($params, $auth): \Api\System\Library\Http\JsonResponse {
            /** @var AiSuggestionService $service */
            $service = $this->container->get('service.ai_suggestion');
            $result = $service->dismiss(trim((string)($params['public_id'] ?? '')), $auth['user']);
            if (!(bool)($result['ok'] ?? false)) {
                $code = (string)($result['code'] ?? 'AI_SUGGESTION_NOT_FOUND');
                $status = $code === 'AI_SUGGESTION_NOT_FOUND' ? 404 : 400;
                return $this->error($code, $this->t('ai/messages.suggestion_dismiss_failed'), $status, [
                    'suggestion' => [$code],
                ]);
            }

            return $this->success('AI_SUGGESTION_DISMISSED', $this->t('ai/messages.suggestion_dismissed'), [
                'suggestion' => $result['suggestion'],
            ]);
        });
    }

    public function previewApply(array $params): \Api\System\Library\Http\JsonResponse
    {
        $auth = $this->user();
        if (!$auth) {
            return $this->error('UNAUTHORIZED', $this->t('common/messages.unauthorized'), 401);
        }

        /** @var AiSuggestionService $service */
        $service = $this->container->get('service.ai_suggestion');
        $result = $service->previewApply(trim((string)($params['public_id'] ?? '')), $auth['user']);
        if (!(bool)($result['ok'] ?? false)) {
            $code = (string)($result['code'] ?? 'AI_SUGGESTION_NOT_FOUND');
            $status = $code === 'AI_SUGGESTION_NOT_FOUND' ? 404 : 400;
            return $this->error($code, $this->t('ai/messages.suggestion_preview_failed'), $status, [
                'suggestion' => [$code],
            ]);
        }

        return $this->success('AI_SUGGESTION_PREVIEW', $this->t('ai/messages.suggestion_preview'), [
            'suggestion' => $result['suggestion'],
            'preview' => $result['preview'],
        ]);
    }

    public function confirm(array $params): \Api\System\Library\Http\JsonResponse
    {
        $auth = $this->user();
        if (!$auth) {
            return $this->error('UNAUTHORIZED', $this->t('common/messages.unauthorized'), 401);
        }

        return $this->withIdempotency(function () use ($params, $auth): \Api\System\Library\Http\JsonResponse {
            /** @var AiSuggestionService $service */
            $service = $this->container->get('service.ai_suggestion');
            $result = $service->confirm(trim((string)($params['public_id'] ?? '')), $this->request()->allInput(), $auth['user']);
            if (!(bool)($result['ok'] ?? false)) {
                $code = $this->normalizeAiCode((string)($result['code'] ?? 'AI_SUGGESTION_CONFIRM_FAILED'));
                $status = match ($code) {
                    'AI_SUGGESTION_NOT_FOUND' => 404,
                    'TASK_NOT_FOUND' => 404,
                    'AI_SUGGESTION_CONFIRM_INVALID_DECISION' => 422,
                    'AI_ROW_VERSION_CONFLICT' => 409,
                    'AI_PROVIDER_TIMEOUT' => 504,
                    'AI_PROVIDER_AUTH_FAILED', 'AI_PROVIDER_UNAVAILABLE' => 502,
                    default => 400,
                };
                return $this->error($code, $this->t('ai/messages.suggestion_confirm_failed'), $status, [
                    'suggestion' => [$code],
                ], meta: $this->aiErrorMeta($result, $code));
            }

            return $this->success('AI_SUGGESTION_CONFIRMED', $this->t('ai/messages.suggestion_confirmed'), [
                'suggestion' => $result['suggestion'],
            ]);
        });
    }

    private function createTaskIntent(array $params, string $serviceMethod): \Api\System\Library\Http\JsonResponse
    {
        $auth = $this->user();
        if (!$auth) {
            return $this->error('UNAUTHORIZED', $this->t('common/messages.unauthorized'), 401);
        }

        $taskPublicId = trim((string)($params['task_public_id'] ?? ''));
        if ($taskPublicId === '') {
            return $this->error('VALIDATION_ERROR', $this->t('common/messages.validation_error'), 422, [
                'task_public_id' => [$this->t('common/messages.field_required')],
            ]);
        }

        return $this->withIdempotency(function () use ($taskPublicId, $auth, $serviceMethod): \Api\System\Library\Http\JsonResponse {
            /** @var AiSuggestionService $service */
            $service = $this->container->get('service.ai_suggestion');
            $result = $service->{$serviceMethod}($taskPublicId, $this->request()->allInput(), $auth['user']);
            if (!(bool)($result['ok'] ?? false)) {
                $code = $this->normalizeAiCode((string)($result['code'] ?? 'AI_SUGGESTION_CREATE_FAILED'));
                $status = match ($code) {
                    'TASK_NOT_FOUND' => 404,
                    'AI_DISABLED', 'AI_FEATURE_DISABLED', 'AI_PROVIDER_NOT_CONFIGURED' => 409,
                    'AI_INTENT_DISABLED' => 409,
                    'AI_RATE_LIMITED' => 429,
                    'AI_COST_LIMIT_EXCEEDED' => 409,
                    'AI_SCHEMA_VALIDATION_FAILED' => 422,
                    'AI_ACTION_TYPE_NOT_ALLOWED' => 422,
                    'AI_SCOPE_PUBLIC_ID_INVALID' => 422,
                    'FORBIDDEN' => 403,
                    'AI_PROVIDER_TIMEOUT' => 504,
                    'AI_PROVIDER_AUTH_FAILED', 'AI_PROVIDER_UNAVAILABLE' => 502,
                    default => 400,
                };
                return $this->error($code, $this->t('ai/messages.suggestion_create_failed'), $status, [
                    'ai' => [$code],
                ], meta: $this->aiErrorMeta($result, $code));
            }

            return $this->success('AI_SUGGESTION_CREATED', $this->t('ai/messages.suggestion_created'), [
                'suggestion' => $result['suggestion'],
                'job_public_id' => (string)($result['job_public_id'] ?? ''),
            ], 201);
        });
    }

    private function createScopedIntent(array $params, string $paramName, string $serviceMethod): \Api\System\Library\Http\JsonResponse
    {
        $auth = $this->user();
        if (!$auth) {
            return $this->error('UNAUTHORIZED', $this->t('common/messages.unauthorized'), 401);
        }

        $publicId = trim((string)($params[$paramName] ?? ''));
        if ($publicId === '') {
            return $this->error('VALIDATION_ERROR', $this->t('common/messages.validation_error'), 422, [
                $paramName => [$this->t('common/messages.field_required')],
            ]);
        }

        return $this->withIdempotency(function () use ($publicId, $auth, $serviceMethod): \Api\System\Library\Http\JsonResponse {
            /** @var AiSuggestionService $service */
            $service = $this->container->get('service.ai_suggestion');
            $result = $service->{$serviceMethod}($publicId, $this->request()->allInput(), $auth['user']);
            if (!(bool)($result['ok'] ?? false)) {
                $code = $this->normalizeAiCode((string)($result['code'] ?? 'AI_SUGGESTION_CREATE_FAILED'));
                $status = match ($code) {
                    'PROJECT_NOT_FOUND', 'CLIENT_NOT_FOUND', 'EVENT_NOT_FOUND' => 404,
                    'AI_DISABLED', 'AI_FEATURE_DISABLED', 'AI_PROVIDER_NOT_CONFIGURED', 'AI_INTENT_DISABLED' => 409,
                    'AI_RATE_LIMITED' => 429,
                    'AI_COST_LIMIT_EXCEEDED' => 409,
                    'AI_ACTION_TYPE_NOT_ALLOWED', 'AI_SCHEMA_VALIDATION_FAILED', 'AI_SCOPE_PUBLIC_ID_INVALID' => 422,
                    'FORBIDDEN' => 403,
                    'AI_PROVIDER_TIMEOUT' => 504,
                    'AI_PROVIDER_AUTH_FAILED', 'AI_PROVIDER_UNAVAILABLE' => 502,
                    default => 400,
                };
                return $this->error($code, $this->t('ai/messages.suggestion_create_failed'), $status, [
                    'ai' => [$code],
                ], meta: $this->aiErrorMeta($result, $code));
            }

            return $this->success('AI_SUGGESTION_CREATED', $this->t('ai/messages.suggestion_created'), [
                'suggestion' => $result['suggestion'],
                'job_public_id' => (string)($result['job_public_id'] ?? ''),
            ], 201);
        });
    }

    private function createActorScopedIntent(string $serviceMethod): \Api\System\Library\Http\JsonResponse
    {
        $auth = $this->user();
        if (!$auth) {
            return $this->error('UNAUTHORIZED', $this->t('common/messages.unauthorized'), 401);
        }

        return $this->withIdempotency(function () use ($auth, $serviceMethod): \Api\System\Library\Http\JsonResponse {
            /** @var AiSuggestionService $service */
            $service = $this->container->get('service.ai_suggestion');
            $result = $service->{$serviceMethod}($this->request()->allInput(), $auth['user']);
            if (!(bool)($result['ok'] ?? false)) {
                $code = $this->normalizeAiCode((string)($result['code'] ?? 'AI_SUGGESTION_CREATE_FAILED'));
                $status = match ($code) {
                    'AI_DISABLED', 'AI_FEATURE_DISABLED', 'AI_PROVIDER_NOT_CONFIGURED', 'AI_INTENT_DISABLED' => 409,
                    'AI_RATE_LIMITED' => 429,
                    'AI_COST_LIMIT_EXCEEDED' => 409,
                    'AI_ACTION_TYPE_NOT_ALLOWED', 'AI_SCHEMA_VALIDATION_FAILED', 'AI_SCOPE_PUBLIC_ID_INVALID' => 422,
                    'FORBIDDEN' => 403,
                    'AI_PROVIDER_TIMEOUT' => 504,
                    'AI_PROVIDER_AUTH_FAILED', 'AI_PROVIDER_UNAVAILABLE' => 502,
                    default => 400,
                };
                return $this->error($code, $this->t('ai/messages.suggestion_create_failed'), $status, [
                    'ai' => [$code],
                ], meta: $this->aiErrorMeta($result, $code));
            }

            return $this->success('AI_SUGGESTION_CREATED', $this->t('ai/messages.suggestion_created'), [
                'suggestion' => $result['suggestion'],
                'job_public_id' => (string)($result['job_public_id'] ?? ''),
            ], 201);
        });
    }

    /** @param array<string,mixed> $result */
    private function aiErrorMeta(array $result, string $code): array
    {
        $meta = [];
        if ($code === 'AI_RATE_LIMITED') {
            $retryAfter = (int)($result['retry_after'] ?? 0);
            if ($retryAfter > 0) {
                $meta['retry_after'] = $retryAfter;
            }
        }
        if ($code === 'AI_ROW_VERSION_CONFLICT') {
            $rowVersion = (int)($result['row_version'] ?? 0);
            if ($rowVersion > 0) {
                $meta['row_version'] = $rowVersion;
            }
        }
        if (in_array($code, ['AI_PROVIDER_TIMEOUT', 'AI_PROVIDER_AUTH_FAILED', 'AI_PROVIDER_UNAVAILABLE'], true)) {
            $meta['provider_error'] = $this->providerErrorMeta($result, $code);
        }

        return $meta;
    }

    /** @param array<string,mixed> $result */
    private function providerErrorMeta(array $result, string $code): array
    {
        $providerError = $result['provider_error'] ?? null;
        if (is_array($providerError)) {
            $category = trim((string)($providerError['category'] ?? ''));
            $meta = [
                'category' => $category !== '' ? $category : $this->providerErrorCategoryByCode($code),
                'retryable' => (bool)($providerError['retryable'] ?? ($code !== 'AI_PROVIDER_AUTH_FAILED')),
            ];
            $httpStatus = max(0, (int)($providerError['http_status'] ?? 0));
            if ($httpStatus > 0) {
                $meta['http_status'] = $httpStatus;
            }

            return $meta;
        }

        return [
            'category' => $this->providerErrorCategoryByCode($code),
            'retryable' => $code !== 'AI_PROVIDER_AUTH_FAILED',
        ];
    }

    private function providerErrorCategoryByCode(string $code): string
    {
        return match ($code) {
            'AI_PROVIDER_TIMEOUT' => 'timeout',
            'AI_PROVIDER_AUTH_FAILED' => 'auth',
            default => 'unavailable',
        };
    }

    private function normalizeAiCode(string $code): string
    {
        $code = strtoupper(trim($code));
        if ($code === 'AI_PROVIDER_SECRET_NOT_CONFIGURED') {
            return 'AI_PROVIDER_NOT_CONFIGURED';
        }
        if (str_starts_with($code, 'AI_PROVIDER_') && !in_array($code, ['AI_PROVIDER_TIMEOUT', 'AI_PROVIDER_AUTH_FAILED', 'AI_PROVIDER_NOT_CONFIGURED', 'AI_PROVIDER_UNAVAILABLE'], true)) {
            return 'AI_PROVIDER_UNAVAILABLE';
        }

        return $code !== '' ? $code : 'AI_SUGGESTION_CREATE_FAILED';
    }
}
