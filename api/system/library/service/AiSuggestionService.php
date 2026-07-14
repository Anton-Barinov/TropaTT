<?php
declare(strict_types=1);

namespace Api\System\Library\Service;

use Api\Model\Ai\AiProviderRepository;
use Api\Model\Ai\AiRuntimeRepository;
use Api\Model\Ai\AiIntentSettingRepository;
use Api\System\Library\Config;
use Api\System\Library\Language\LanguageManager;
use Api\System\Library\Language\TranslatableTrait;
use Api\System\Library\Logger\JsonLogger;
use Throwable;

final class AiSuggestionService
{
    use TranslatableTrait;

    public function __construct(
        private readonly AiProviderRepository $providers,
        private readonly AiRuntimeRepository $runtime,
        private readonly AiIntentSettingRepository $intentSettings,
        private readonly AiRetentionPolicyService $retention,
        private readonly AiPromptSchemaService $promptSchemas,
        private readonly AiPromptBuilderService $promptBuilder,
        private readonly AiContextBuilder $contextBuilder,
        private readonly AiRateLimitService $rateLimit,
        private readonly AiCostLimitService $costLimit,
        private readonly TaskService $tasks,
        private readonly ProjectService $projects,
        private readonly ClientService $clients,
        private readonly CalendarService $calendar,
        private readonly SettingService $settings,
        private readonly AiProviderService $aiProviderService,
        private readonly FeatureFlagService $featureFlags,
        private readonly JsonLogger $logger,
        private readonly Config $config,
        ?LanguageManager $lang = null
    ) {
        $this->lang = $lang ?? new LanguageManager(__DIR__ . '/../../language');
    }

    public function createTaskSummary(string $taskPublicId, array $input, array $actor): array
    {
        return $this->createTaskIntentSuggestion(
            $taskPublicId,
            $input,
            $actor,
            'task_summary',
            fn(array $context): array => $this->buildTaskSummarySuggestion($context)
        );
    }

    public function createTaskDecomposition(string $taskPublicId, array $input, array $actor): array
    {
        return $this->createTaskIntentSuggestion(
            $taskPublicId,
            $input,
            $actor,
            'task_decomposition',
            fn(array $context): array => $this->buildTaskDecompositionSuggestion($context)
        );
    }

    public function createTaskChecklist(string $taskPublicId, array $input, array $actor): array
    {
        return $this->createTaskIntentSuggestion(
            $taskPublicId,
            $input,
            $actor,
            'task_checklist',
            fn(array $context): array => $this->buildTaskChecklistSuggestion($context)
        );
    }

    public function createTaskQuality(string $taskPublicId, array $input, array $actor): array
    {
        return $this->createTaskIntentSuggestion(
            $taskPublicId,
            $input,
            $actor,
            'task_quality',
            fn(array $context): array => $this->buildTaskQualitySuggestion($context)
        );
    }

    public function createTaskNextAction(string $taskPublicId, array $input, array $actor): array
    {
        return $this->createTaskIntentSuggestion(
            $taskPublicId,
            $input,
            $actor,
            'task_next_action',
            fn(array $context): array => $this->buildTaskNextActionSuggestion($context)
        );
    }

    public function createTaskCommentDraft(string $taskPublicId, array $input, array $actor): array
    {
        return $this->createTaskIntentSuggestion(
            $taskPublicId,
            $input,
            $actor,
            'task_comment_draft',
            fn(array $context): array => $this->buildTaskCommentDraftSuggestion($context)
        );
    }

    public function createProjectSummary(string $projectPublicId, array $input, array $actor): array
    {
        $accessCheck = $this->ensureIntentAccessBeforeContextBuild('project_summary', $actor);
        if ($accessCheck !== null) {
            return $accessCheck;
        }

        $context = $this->contextBuilder->buildProjectSummaryContext($projectPublicId, $input, $actor);
        if ($context === null) {
            return ['ok' => false, 'code' => 'PROJECT_NOT_FOUND'];
        }

        return $this->createContextIntentSuggestion('project_summary', 'project', $projectPublicId, $context, $input, $actor, fn(array $ctx): array => $this->buildProjectSummarySuggestion($ctx));
    }

    public function createProjectRisks(string $projectPublicId, array $input, array $actor): array
    {
        $accessCheck = $this->ensureIntentAccessBeforeContextBuild('project_risk_summary', $actor);
        if ($accessCheck !== null) {
            return $accessCheck;
        }

        $context = $this->contextBuilder->buildProjectSummaryContext($projectPublicId, $input, $actor);
        if ($context === null) {
            return ['ok' => false, 'code' => 'PROJECT_NOT_FOUND'];
        }

        return $this->createContextIntentSuggestion('project_risk_summary', 'project', $projectPublicId, $context, $input, $actor, fn(array $ctx): array => $this->buildProjectRiskSuggestion($ctx));
    }

    public function createProjectClientReport(string $projectPublicId, array $input, array $actor): array
    {
        $accessCheck = $this->ensureIntentAccessBeforeContextBuild('project_client_report', $actor);
        if ($accessCheck !== null) {
            return $accessCheck;
        }

        $context = $this->contextBuilder->buildProjectSummaryContext($projectPublicId, $input, $actor);
        if ($context === null) {
            return ['ok' => false, 'code' => 'PROJECT_NOT_FOUND'];
        }

        return $this->createContextIntentSuggestion('project_client_report', 'project', $projectPublicId, $context, $input, $actor, fn(array $ctx): array => $this->buildProjectClientReportSuggestion($ctx));
    }

    public function createClientSummary(string $clientPublicId, array $input, array $actor): array
    {
        $accessCheck = $this->ensureIntentAccessBeforeContextBuild('client_summary', $actor);
        if ($accessCheck !== null) {
            return $accessCheck;
        }

        $context = $this->contextBuilder->buildClientSummaryContext($clientPublicId, $input, $actor);
        if ($context === null) {
            return ['ok' => false, 'code' => 'CLIENT_NOT_FOUND'];
        }

        return $this->createContextIntentSuggestion('client_summary', 'client', $clientPublicId, $context, $input, $actor, fn(array $ctx): array => $this->buildClientSummarySuggestion($ctx));
    }

    public function createClientMeetingPrep(string $clientPublicId, array $input, array $actor): array
    {
        $accessCheck = $this->ensureIntentAccessBeforeContextBuild('client_meeting_prep', $actor);
        if ($accessCheck !== null) {
            return $accessCheck;
        }

        $context = $this->contextBuilder->buildClientMeetingPrepContext($clientPublicId, $input, $actor);
        if ($context === null) {
            return ['ok' => false, 'code' => 'CLIENT_NOT_FOUND'];
        }

        return $this->createContextIntentSuggestion('client_meeting_prep', 'client', $clientPublicId, $context, $input, $actor, fn(array $ctx): array => $this->buildClientMeetingPrepSuggestion($ctx));
    }

    public function createClientDataQuality(string $clientPublicId, array $input, array $actor): array
    {
        $accessCheck = $this->ensureIntentAccessBeforeContextBuild('client_data_quality', $actor);
        if ($accessCheck !== null) {
            return $accessCheck;
        }

        $context = $this->contextBuilder->buildClientDataQualityContext($clientPublicId, $input, $actor);
        if ($context === null) {
            return ['ok' => false, 'code' => 'CLIENT_NOT_FOUND'];
        }

        return $this->createContextIntentSuggestion('client_data_quality', 'client', $clientPublicId, $context, $input, $actor, fn(array $ctx): array => $this->buildClientDataQualitySuggestion($ctx));
    }

    public function createClientSafeReport(string $clientPublicId, array $input, array $actor): array
    {
        $accessCheck = $this->ensureIntentAccessBeforeContextBuild('client_safe_report', $actor);
        if ($accessCheck !== null) {
            return $accessCheck;
        }

        $context = $this->contextBuilder->buildClientSafeReportContext($clientPublicId, $input, $actor);
        if ($context === null) {
            return ['ok' => false, 'code' => 'CLIENT_NOT_FOUND'];
        }

        return $this->createContextIntentSuggestion('client_safe_report', 'client', $clientPublicId, $context, $input, $actor, fn(array $ctx): array => $this->buildClientSafeReportSuggestion($ctx));
    }

    public function createCalendarEventAgenda(string $eventPublicId, array $input, array $actor): array
    {
        $accessCheck = $this->ensureIntentAccessBeforeContextBuild('calendar_event_agenda', $actor);
        if ($accessCheck !== null) {
            return $accessCheck;
        }

        $context = $this->contextBuilder->buildCalendarEventAgendaContext($eventPublicId, $input, $actor);
        if ($context === null) {
            return ['ok' => false, 'code' => 'EVENT_NOT_FOUND'];
        }

        return $this->createContextIntentSuggestion('calendar_event_agenda', 'calendar_event', $eventPublicId, $context, $input, $actor, fn(array $ctx): array => $this->buildCalendarAgendaSuggestion($ctx));
    }

    public function createDashboardDigest(array $input, array $actor): array
    {
        $accessCheck = $this->ensureIntentAccessBeforeContextBuild('dashboard_daily_digest', $actor);
        if ($accessCheck !== null) {
            return $accessCheck;
        }

        $actorPublicId = trim((string)($actor['public_id'] ?? ''));
        $entityPublicId = $actorPublicId !== '' ? $actorPublicId : 'dashboard_scope';
        $context = $this->contextBuilder->buildDashboardDigestContext($input, $actor);

        return $this->createContextIntentSuggestion('dashboard_daily_digest', 'dashboard', $entityPublicId, $context, $input, $actor, fn(array $ctx): array => $this->buildDashboardDigestSuggestion($ctx));
    }

    public function createAnalyticsKpiExplanation(array $input, array $actor): array
    {
        $accessCheck = $this->ensureIntentAccessBeforeContextBuild('analytics_kpi_explanation', $actor);
        if ($accessCheck !== null) {
            return $accessCheck;
        }

        $actorPublicId = trim((string)($actor['public_id'] ?? ''));
        $entityPublicId = $actorPublicId !== '' ? $actorPublicId : 'analytics_scope';
        $context = $this->contextBuilder->buildAnalyticsOverviewContext($input, $actor);

        return $this->createContextIntentSuggestion('analytics_kpi_explanation', 'analytics', $entityPublicId, $context, $input, $actor, fn(array $ctx): array => $this->buildAnalyticsKpiExplanationSuggestion($ctx));
    }

    public function createAnalyticsRisksExplanation(array $input, array $actor): array
    {
        $accessCheck = $this->ensureIntentAccessBeforeContextBuild('analytics_risks_explanation', $actor);
        if ($accessCheck !== null) {
            return $accessCheck;
        }

        $actorPublicId = trim((string)($actor['public_id'] ?? ''));
        $entityPublicId = $actorPublicId !== '' ? $actorPublicId : 'analytics_scope';
        $context = $this->contextBuilder->buildAnalyticsOverviewContext($input, $actor);

        return $this->createContextIntentSuggestion('analytics_risks_explanation', 'analytics', $entityPublicId, $context, $input, $actor, fn(array $ctx): array => $this->buildAnalyticsRisksExplanationSuggestion($ctx));
    }

    public function createAnalyticsTeamWorkloadSummary(array $input, array $actor): array
    {
        $accessCheck = $this->ensureIntentAccessBeforeContextBuild('analytics_team_workload_summary', $actor);
        if ($accessCheck !== null) {
            return $accessCheck;
        }

        $actorPublicId = trim((string)($actor['public_id'] ?? ''));
        $entityPublicId = $actorPublicId !== '' ? $actorPublicId : 'analytics_scope';
        $context = $this->contextBuilder->buildAnalyticsOverviewContext($input, $actor);

        return $this->createContextIntentSuggestion('analytics_team_workload_summary', 'analytics', $entityPublicId, $context, $input, $actor, fn(array $ctx): array => $this->buildAnalyticsTeamWorkloadSuggestion($ctx));
    }

    public function createAdminLogReview(array $input, array $actor): array
    {
        $accessCheck = $this->ensureIntentAccessBeforeContextBuild('admin_log_review', $actor);
        if ($accessCheck !== null) {
            return $accessCheck;
        }

        $actorPublicId = trim((string)($actor['public_id'] ?? ''));
        $entityPublicId = $actorPublicId !== '' ? $actorPublicId : 'admin_scope';
        $context = $this->contextBuilder->buildAdminLogReviewContext($input);

        return $this->createContextIntentSuggestion('admin_log_review', 'admin', $entityPublicId, $context, $input, $actor, fn(array $ctx): array => $this->buildAdminLogReviewSuggestion($ctx));
    }

    public function createWebhookHealthReview(array $input, array $actor): array
    {
        $accessCheck = $this->ensureIntentAccessBeforeContextBuild('webhook_health_review', $actor);
        if ($accessCheck !== null) {
            return $accessCheck;
        }

        $actorPublicId = trim((string)($actor['public_id'] ?? ''));
        $entityPublicId = $actorPublicId !== '' ? $actorPublicId : 'admin_scope';
        $context = $this->contextBuilder->buildWebhookHealthContext($input);

        return $this->createContextIntentSuggestion('webhook_health_review', 'admin', $entityPublicId, $context, $input, $actor, fn(array $ctx): array => $this->buildWebhookHealthReviewSuggestion($ctx));
    }

    public function createWorkflowRuleAudit(array $input, array $actor): array
    {
        $accessCheck = $this->ensureIntentAccessBeforeContextBuild('workflow_rule_audit', $actor);
        if ($accessCheck !== null) {
            return $accessCheck;
        }

        $actorPublicId = trim((string)($actor['public_id'] ?? ''));
        $entityPublicId = $actorPublicId !== '' ? $actorPublicId : 'admin_scope';
        $context = $this->contextBuilder->buildWorkflowRuleAuditContext($input, $actor);

        return $this->createContextIntentSuggestion('workflow_rule_audit', 'admin', $entityPublicId, $context, $input, $actor, fn(array $ctx): array => $this->buildWorkflowRuleAuditSuggestion($ctx));
    }

    public function createMyDayPlan(array $input, array $actor): array
    {
        if (!$this->isFeatureEnabledForActor('ai.enabled', $actor, false)) {
            return ['ok' => false, 'code' => 'AI_DISABLED'];
        }
        if (!$this->isFeatureEnabledForActor('ai.cron.daily_work_plan', $actor, false)) {
            return ['ok' => false, 'code' => 'AI_FEATURE_DISABLED'];
        }
        if (!in_array('my_day_plan', $this->actionAllowlist(), true)) {
            return ['ok' => false, 'code' => 'AI_ACTION_TYPE_NOT_ALLOWED'];
        }
        $rate = $this->rateLimit->assertWithinLimits('my_day_plan', $actor);
        if (!(bool)($rate['ok'] ?? false)) {
            return $this->limitFailure($rate, 'AI_RATE_LIMITED');
        }
        $cost = $this->costLimit->assertWithinLimits('my_day_plan', $actor);
        if (!(bool)($cost['ok'] ?? false)) {
            return $this->limitFailure($cost, 'AI_COST_LIMIT_EXCEEDED');
        }

        $intent = $this->intentSettings->findByIntentCode('my_day_plan');
        if ($intent && !(bool)($intent['is_enabled'] ?? true)) {
            return ['ok' => false, 'code' => 'AI_INTENT_DISABLED'];
        }
        if ($intent && trim((string)($intent['feature_flag'] ?? '')) !== '') {
            if (!$this->isFeatureEnabledForActor(trim((string)$intent['feature_flag']), $actor, false)) {
                return ['ok' => false, 'code' => 'AI_FEATURE_DISABLED'];
            }
        }
        if ($intent && trim((string)($intent['required_permission'] ?? '')) !== '') {
            if (!$this->hasActorPermission($actor, trim((string)$intent['required_permission']))) {
                return ['ok' => false, 'code' => 'FORBIDDEN'];
            }
        }
        $actorPublicId = (string)($actor['public_id'] ?? '');
        if (!$this->isValidPublicId($actorPublicId)) {
            return ['ok' => false, 'code' => 'AI_SCOPE_PUBLIC_ID_INVALID'];
        }
        if (!$this->isDailyPlanEnabledForScope($actorPublicId)) {
            return ['ok' => false, 'code' => 'AI_PREFERENCES_DAILY_PLAN_DISABLED'];
        }

        $provider = null;
        $intentProviderId = (int)($intent['provider_id'] ?? 0);
        if ($intentProviderId > 0) {
            $provider = $this->providers->findById($intentProviderId);
            if ($provider && !(bool)($provider['is_active'] ?? false)) {
                $provider = null;
            }
        }
        if (!$provider) {
            $provider = $this->providers->findDefaultActive() ?? $this->providers->findAnyActive();
        }
        $provider = $this->resolveUsableProvider($provider);
        if (!$provider) {
            return ['ok' => false, 'code' => 'AI_PROVIDER_NOT_CONFIGURED'];
        }

        $myDayContext = $this->minimizeContextForIntent(
            'my_day_plan',
            $this->contextBuilder->buildMyDayPlanContext($input, $actor)
        );
        $agenda = is_array($myDayContext['agenda'] ?? null) ? (array)$myDayContext['agenda'] : [];
        $candidateTasks = is_array($myDayContext['candidate_tasks'] ?? null) ? (array)$myDayContext['candidate_tasks'] : [];
        $sourceMeta = $this->resolveMyDaySourceMeta($input, $actor);
        $isRegenerate = $this->isRegenerateRequested($input);
        $fallbackPlanPayload = $this->buildMyDayPlanSuggestion($agenda, $candidateTasks, $sourceMeta);
        $planPayload = $fallbackPlanPayload;
        $schemaValidation = $this->promptSchemas->validatePayloadBySchema('my_day_plan', $planPayload);
        if (!(bool)($schemaValidation['ok'] ?? false)) {
            return ['ok' => false, 'code' => (string)($schemaValidation['code'] ?? 'AI_SCHEMA_VALIDATION_FAILED')];
        }
        $planPayload = $this->sanitizePayloadByIntentSchema('my_day_plan', $planPayload);

        $locale = trim((string)($actor['locale'] ?? '')) !== '' ? trim((string)$actor['locale']) : 'ru-ru';
        $prompt = $this->promptSchemas->resolveActivePrompt('my_day_plan', $locale);
        $strictPromptMasking = $this->useStrictPromptMaskingForProvider($provider);
        $promptEnvelope = $this->promptBuilder->buildPromptEnvelope(
            'my_day_plan',
            $prompt,
            $myDayContext,
            $input,
            0,
            $strictPromptMasking
        );
        $resolvedModel = trim((string)($intent['model'] ?? '')) !== '' ? trim((string)$intent['model']) : (string)($provider['default_model'] ?? '');
        $forceRefresh = $this->isForceRefreshRequested($input) || $isRegenerate;
        $dateBucket = $this->resolveCacheDateBucket('my_day_plan');
        $cacheKey = $this->buildCacheKey(
            (int)($actor['id'] ?? 0),
            $actor,
            'my_day_plan',
            'user',
            $actorPublicId,
            (string)($provider['public_id'] ?? ''),
            $resolvedModel,
            $dateBucket,
            $input,
            (int)($prompt['version'] ?? 0)
        );
        $dependencyFingerprint = $this->buildDependencyFingerprint('my_day_plan', $myDayContext, $actor, $dateBucket, (int)($prompt['version'] ?? 0));
        $cachedForFallback = $this->runtime->findLatestSuggestionByCacheKey(
            'my_day_plan',
            'user',
            $actorPublicId,
            (int)($actor['id'] ?? 0),
            $cacheKey
        );
        if (!$forceRefresh) {
            $cachedResponse = $this->resolveCachedSuggestionResponse(
                $cachedForFallback,
                $dependencyFingerprint,
                $dateBucket,
                (string)($provider['public_id'] ?? ''),
                $resolvedModel
            );
            if ($cachedResponse !== null) {
                $this->runtime->markSuggestionUsed((string)($cachedForFallback['public_id'] ?? ''), gmdate('Y-m-d H:i:s'));
                return [
                    'ok' => true,
                    'suggestion' => $cachedResponse,
                    'job_public_id' => '',
                ];
            }
        }
        $structuredIntent = $this->isStructuredIntent('my_day_plan');
        $llmPayload = [
            'intent_code' => 'my_day_plan',
            'system_prompt' => (string)($promptEnvelope['system_prompt'] ?? '') . ($structuredIntent ? "\n\n" . $this->structuredResponseInstruction('my_day_plan') : ''),
            'user_prompt' => (string)($promptEnvelope['user_prompt'] ?? ''),
            'context' => (array)($promptEnvelope['context'] ?? []),
            'model' => $resolvedModel,
        ];
        if ($structuredIntent) {
            $llmPayload['response_format'] = ['type' => 'json_object'];
        }
        $llm = $this->aiProviderService->completeText((string)($provider['public_id'] ?? ''), $llmPayload);
        $llmOk = (bool)($llm['ok'] ?? false) && trim((string)($llm['text'] ?? '')) !== '';
        $llmResolution = $this->resolveLlmExecution($provider, $llm);
        if (!(bool)($llmResolution['ok'] ?? false)) {
            $cachedDueError = $this->resolveStaleCacheDueToAiError(
                $cachedForFallback,
                $dependencyFingerprint,
                $dateBucket,
                (string)($provider['public_id'] ?? ''),
                $resolvedModel,
                (string)($llmResolution['code'] ?? 'AI_PROVIDER_UNAVAILABLE')
            );
            if ($cachedDueError !== null) {
                $this->runtime->markSuggestionUsed((string)($cachedForFallback['public_id'] ?? ''), gmdate('Y-m-d H:i:s'));
                return [
                    'ok' => true,
                    'suggestion' => $cachedDueError,
                    'job_public_id' => '',
                ];
            }
            return ['ok' => false, 'code' => (string)($llmResolution['code'] ?? 'AI_PROVIDER_UNAVAILABLE')];
        }
        $llmMode = $llmOk ? 'llm' : 'safe_mock';
        if ($structuredIntent) {
            if (!$llmOk) {
                return ['ok' => false, 'code' => 'AI_STRUCTURED_RESPONSE_INVALID'];
            }
            $structured = $this->parseStructuredIntentWithRepair(
                'my_day_plan',
                (string)($provider['public_id'] ?? ''),
                (string)$resolvedModel,
                (string)($promptEnvelope['system_prompt'] ?? ''),
                (string)($llm['text'] ?? '')
            );
            if (!(bool)($structured['ok'] ?? false)) {
                $this->logStructuredIntentInvalid('my_day_plan', $provider, $resolvedModel, $structured);
                return ['ok' => false, 'code' => 'AI_STRUCTURED_RESPONSE_INVALID'];
            }
            $planPayload = $this->mergeMyDayPlanWithFallback((array)($structured['payload'] ?? $planPayload), $fallbackPlanPayload);
            $meta = is_array($planPayload['meta'] ?? null) ? (array)$planPayload['meta'] : [];
            $meta['mode'] = 'llm';
            $meta['parse_ok'] = true;
            $meta['validation_ok'] = true;
            $meta['repair_attempted'] = (bool)($structured['repair_attempted'] ?? false);
            $meta['fallback_used'] = false;
            $meta['raw_text_used'] = false;
            $planPayload['meta'] = $meta;
            $llmMode = 'llm';
            $this->logger->audit([
                'action' => 'ai_structured_intent_result',
                'intent_code' => 'my_day_plan',
                'provider_code' => (string)($provider['provider_code'] ?? ''),
                'model' => $resolvedModel,
                'expected_schema' => 'my_day_plan',
                'parse_ok' => true,
                'validation_ok' => true,
                'repair_attempted' => (bool)($structured['repair_attempted'] ?? false),
                'fallback_used' => false,
                'raw_text_used' => false,
                'action_count' => count((array)($planPayload['suggested_actions'] ?? [])),
                'risk_count' => count((array)($planPayload['risks'] ?? [])),
                'question_count' => count((array)($planPayload['questions'] ?? [])),
                'suggested_task_count' => count((array)($planPayload['suggested_tasks'] ?? [])),
                'error_code' => null,
            ]);
        } elseif ($llmOk) {
            $planPayload = $this->mergeLlmTextIntoPayload('my_day_plan', $planPayload, (string)$llm['text']);
        }
        $planPayload = $this->mergeMyDayPlanWithFallback($planPayload, $fallbackPlanPayload);
        $now = gmdate('Y-m-d H:i:s');
        $inputHash = null;
        if ((string)($sourceMeta['execution_mode'] ?? '') === 'cron') {
            $hashPayload = [
                'intent_code' => 'my_day_plan',
                'scope_type' => 'user',
                'scope_public_id' => $actorPublicId,
                'source_meta' => $sourceMeta,
                'input' => $this->sanitizeInput($input),
                'agenda' => $agenda,
                'candidate_tasks' => $candidateTasks,
            ];
            $inputHash = $this->buildBackgroundInputHash($hashPayload);
            $existing = $this->runtime->findSuggestionByInputHash('my_day_plan', 'user', $actorPublicId, $inputHash);
            if ($existing) {
                return [
                    'ok' => true,
                    'suggestion' => $this->normalizeSuggestion((array)$existing, true),
                    'job_public_id' => '',
                ];
            }
        }

        $suggestionPublicId = $this->runtime->createSuggestion([
            'intent_code' => 'my_day_plan',
            'entity_type' => 'user',
            'entity_public_id' => $actorPublicId,
            'summary' => (string)($planPayload['summary'] ?? ''),
            'suggestion_json' => json_encode($planPayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'input_hash' => $inputHash,
            'status' => 'draft',
            'cache_key' => $cacheKey,
            'dependency_fingerprint' => $dependencyFingerprint,
            'cache_status' => 'fresh',
            'stale_reason' => null,
            'date_bucket' => $dateBucket,
            'provider_public_id' => (string)($provider['public_id'] ?? ''),
            'provider_code' => (string)($provider['provider_code'] ?? ''),
            'model' => $resolvedModel,
            'last_used_at' => $now,
            'usage_count' => 1,
            'result_meta_json' => json_encode([
                'cache' => [
                    'dependency_fingerprint' => $dependencyFingerprint,
                    'date_bucket' => $dateBucket,
                ],
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'created_by_user_id' => (int)($actor['id'] ?? 0) ?: null,
            'confirmed_by_user_id' => null,
            'created_at' => $now,
            'updated_at' => $now,
            'expires_at' => null,
        ]);

        $jobPayload = [
            'action_type' => 'my_day_plan',
            'intent_code' => 'my_day_plan',
            'provider_public_id' => (string)($provider['public_id'] ?? ''),
            'model' => $resolvedModel,
            'scope_type' => 'user',
            'scope_public_id' => $actorPublicId,
            'suggestion_public_id' => $suggestionPublicId,
            'intent_setting_public_id' => (string)($intent['public_id'] ?? ''),
            'prompt_public_id' => (string)($prompt['public_id'] ?? ''),
            'prompt_version' => (int)($prompt['version'] ?? 0),
            'prompt_runtime' => $this->sanitizePromptRuntimeForStorage($promptEnvelope),
            'source_meta' => $sourceMeta,
            'input' => $this->sanitizeInput($input),
            'regenerate' => $isRegenerate,
        ];
        $resultPayload = [
            'mode' => $llmMode,
            'suggestion_public_id' => $suggestionPublicId,
        ];

        $jobPublicId = $this->runtime->createJob([
            'job_type' => 'interactive',
            'action_type' => 'my_day_plan',
            'intent_code' => 'my_day_plan',
            'status' => 'completed',
            'requested_by_user_id' => (int)($actor['id'] ?? 0) ?: null,
            'scope_type' => 'user',
            'scope_public_id' => $actorPublicId,
            'idempotency_key_hash' => null,
            'payload_json' => json_encode($jobPayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'result_json' => json_encode($resultPayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'error_code' => $llmOk ? null : (string)($llm['code'] ?? 'AI_PROVIDER_UNAVAILABLE'),
            'error_message' => null,
            'created_at' => $now,
            'started_at' => $now,
            'finished_at' => $now,
            'updated_at' => $now,
        ]);

        $this->writeUsageLog([
            'user_id' => (int)($actor['id'] ?? 0) ?: null,
            'provider_public_id' => (string)($provider['public_id'] ?? ''),
            'action_type' => 'my_day_plan',
            'intent_code' => 'my_day_plan',
            'status' => 'completed',
            'error_code' => $llmOk ? null : (string)($llm['code'] ?? 'AI_PROVIDER_UNAVAILABLE'),
            'request_tokens' => (int)($llm['request_tokens'] ?? 0),
            'response_tokens' => (int)($llm['response_tokens'] ?? 0),
            'total_tokens' => (int)($llm['total_tokens'] ?? 0),
            'latency_ms' => (int)($llm['latency_ms'] ?? 0),
            'is_sensitive_context' => 0,
            'request_meta' => json_encode([
                'mode' => $llmMode,
                'scope_type' => 'user',
                'scope_public_id' => $actorPublicId,
                'suggestion_public_id' => $suggestionPublicId,
                'source_meta' => $sourceMeta,
                'intent_setting_public_id' => (string)($intent['public_id'] ?? ''),
                'prompt_public_id' => (string)($prompt['public_id'] ?? ''),
                'prompt_runtime' => [
                    'context_budget_tokens' => (int)($promptEnvelope['meta']['context_budget_tokens'] ?? 0),
                    'context_estimated_tokens' => (int)($promptEnvelope['meta']['context_estimated_tokens'] ?? 0),
                    'context_truncated' => (bool)($promptEnvelope['meta']['context_truncated'] ?? false),
                ],
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'created_at' => $now,
        ]);

        $this->logger->audit([
            'action' => 'ai_suggestion_created',
            'actor_public_id' => $actorPublicId,
            'entity_type' => 'ai_suggestion',
            'entity_public_id' => $suggestionPublicId,
            'intent_code' => 'my_day_plan',
            'scope_type' => 'user',
            'scope_public_id' => $actorPublicId,
            'job_public_id' => $jobPublicId,
            'provider_public_id' => (string)($provider['public_id'] ?? ''),
            'provider_code' => (string)($provider['provider_code'] ?? ''),
        ]);

        return [
            'ok' => true,
            'suggestion' => $this->normalizeSuggestion(
                (array)($this->runtime->findSuggestionByPublicId($suggestionPublicId) ?? []),
                true
            ),
            'job_public_id' => $jobPublicId,
        ];
    }

    public function createMyWeekPlan(array $input, array $actor): array
    {
        if (!$this->isFeatureEnabledForActor('ai.enabled', $actor, false)) {
            return ['ok' => false, 'code' => 'AI_DISABLED'];
        }
        if (!$this->isFeatureEnabledForActor('ai.calendar', $actor, false)) {
            return ['ok' => false, 'code' => 'AI_FEATURE_DISABLED'];
        }
        if (!in_array('my_week_plan', $this->actionAllowlist(), true)) {
            return ['ok' => false, 'code' => 'AI_ACTION_TYPE_NOT_ALLOWED'];
        }
        $rate = $this->rateLimit->assertWithinLimits('my_week_plan', $actor);
        if (!(bool)($rate['ok'] ?? false)) {
            return $this->limitFailure($rate, 'AI_RATE_LIMITED');
        }
        $cost = $this->costLimit->assertWithinLimits('my_week_plan', $actor);
        if (!(bool)($cost['ok'] ?? false)) {
            return $this->limitFailure($cost, 'AI_COST_LIMIT_EXCEEDED');
        }

        $intent = $this->intentSettings->findByIntentCode('my_week_plan');
        if ($intent && !(bool)($intent['is_enabled'] ?? true)) {
            return ['ok' => false, 'code' => 'AI_INTENT_DISABLED'];
        }
        if ($intent && trim((string)($intent['feature_flag'] ?? '')) !== '') {
            if (!$this->isFeatureEnabledForActor(trim((string)$intent['feature_flag']), $actor, false)) {
                return ['ok' => false, 'code' => 'AI_FEATURE_DISABLED'];
            }
        }
        if ($intent && trim((string)($intent['required_permission'] ?? '')) !== '') {
            if (!$this->hasActorPermission($actor, trim((string)$intent['required_permission']))) {
                return ['ok' => false, 'code' => 'FORBIDDEN'];
            }
        }

        $provider = null;
        $intentProviderId = (int)($intent['provider_id'] ?? 0);
        if ($intentProviderId > 0) {
            $provider = $this->providers->findById($intentProviderId);
            if ($provider && !(bool)($provider['is_active'] ?? false)) {
                $provider = null;
            }
        }
        if (!$provider) {
            $provider = $this->providers->findDefaultActive() ?? $this->providers->findAnyActive();
        }
        $provider = $this->resolveUsableProvider($provider);
        if (!$provider) {
            return ['ok' => false, 'code' => 'AI_PROVIDER_NOT_CONFIGURED'];
        }

        $myWeekContext = $this->minimizeContextForIntent(
            'my_week_plan',
            $this->contextBuilder->buildMyWeekPlanContext($input, $actor)
        );
        $agenda = is_array($myWeekContext['agenda'] ?? null) ? (array)$myWeekContext['agenda'] : [];
        $candidateTasks = is_array($myWeekContext['candidate_tasks'] ?? null) ? (array)$myWeekContext['candidate_tasks'] : [];
        $fallbackPlanPayload = $this->buildMyWeekPlanSuggestion($agenda, $candidateTasks);
        $planPayload = $fallbackPlanPayload;
        $schemaValidation = $this->promptSchemas->validatePayloadBySchema('my_week_plan', $planPayload);
        if (!(bool)($schemaValidation['ok'] ?? false)) {
            return ['ok' => false, 'code' => (string)($schemaValidation['code'] ?? 'AI_SCHEMA_VALIDATION_FAILED')];
        }
        $planPayload = $this->sanitizePayloadByIntentSchema('my_week_plan', $planPayload);

        $locale = trim((string)($actor['locale'] ?? '')) !== '' ? trim((string)$actor['locale']) : 'ru-ru';
        $prompt = $this->promptSchemas->resolveActivePrompt('my_week_plan', $locale);
        $strictPromptMasking = $this->useStrictPromptMaskingForProvider($provider);
        $promptEnvelope = $this->promptBuilder->buildPromptEnvelope(
            'my_week_plan',
            $prompt,
            $myWeekContext,
            $input,
            0,
            $strictPromptMasking
        );
        $resolvedModel = trim((string)($intent['model'] ?? '')) !== '' ? trim((string)$intent['model']) : (string)($provider['default_model'] ?? '');
        $forceRefresh = $this->isForceRefreshRequested($input);
        $actorPublicId = (string)($actor['public_id'] ?? '');
        if (!$this->isValidPublicId($actorPublicId)) {
            return ['ok' => false, 'code' => 'AI_SCOPE_PUBLIC_ID_INVALID'];
        }
        $dateBucket = $this->resolveCacheDateBucket('my_week_plan');
        $cacheKey = $this->buildCacheKey(
            (int)($actor['id'] ?? 0),
            $actor,
            'my_week_plan',
            'user',
            $actorPublicId,
            (string)($provider['public_id'] ?? ''),
            $resolvedModel,
            $dateBucket,
            $input,
            (int)($prompt['version'] ?? 0)
        );
        $dependencyFingerprint = $this->buildDependencyFingerprint('my_week_plan', $myWeekContext, $actor, $dateBucket, (int)($prompt['version'] ?? 0));
        $cachedForFallback = $this->runtime->findLatestSuggestionByCacheKey(
            'my_week_plan',
            'user',
            $actorPublicId,
            (int)($actor['id'] ?? 0),
            $cacheKey
        );
        if (!$forceRefresh) {
            $cachedResponse = $this->resolveCachedSuggestionResponse(
                $cachedForFallback,
                $dependencyFingerprint,
                $dateBucket,
                (string)($provider['public_id'] ?? ''),
                $resolvedModel
            );
            if ($cachedResponse !== null) {
                $this->runtime->markSuggestionUsed((string)($cachedForFallback['public_id'] ?? ''), gmdate('Y-m-d H:i:s'));
                return [
                    'ok' => true,
                    'suggestion' => $cachedResponse,
                    'job_public_id' => '',
                ];
            }
        }
        $structuredIntent = $this->isStructuredIntent('my_week_plan');
        $llmPayload = [
            'intent_code' => 'my_week_plan',
            'system_prompt' => (string)($promptEnvelope['system_prompt'] ?? '') . ($structuredIntent ? "\n\n" . $this->structuredResponseInstruction('my_week_plan') : ''),
            'user_prompt' => (string)($promptEnvelope['user_prompt'] ?? ''),
            'context' => (array)($promptEnvelope['context'] ?? []),
            'model' => $resolvedModel,
        ];
        if ($structuredIntent) {
            $llmPayload['response_format'] = ['type' => 'json_object'];
        }
        $llm = $this->aiProviderService->completeText((string)($provider['public_id'] ?? ''), $llmPayload);
        $llmOk = (bool)($llm['ok'] ?? false) && trim((string)($llm['text'] ?? '')) !== '';
        $llmResolution = $this->resolveLlmExecution($provider, $llm);
        if (!(bool)($llmResolution['ok'] ?? false)) {
            $cachedDueError = $this->resolveStaleCacheDueToAiError(
                $cachedForFallback,
                $dependencyFingerprint,
                $dateBucket,
                (string)($provider['public_id'] ?? ''),
                $resolvedModel,
                (string)($llmResolution['code'] ?? 'AI_PROVIDER_UNAVAILABLE')
            );
            if ($cachedDueError !== null) {
                $this->runtime->markSuggestionUsed((string)($cachedForFallback['public_id'] ?? ''), gmdate('Y-m-d H:i:s'));
                return [
                    'ok' => true,
                    'suggestion' => $cachedDueError,
                    'job_public_id' => '',
                ];
            }
            return ['ok' => false, 'code' => (string)($llmResolution['code'] ?? 'AI_PROVIDER_UNAVAILABLE')];
        }
        $llmMode = $llmOk ? 'llm' : 'safe_mock';
        if ($structuredIntent) {
            if (!$llmOk) {
                return ['ok' => false, 'code' => 'AI_STRUCTURED_RESPONSE_INVALID'];
            }
            $structured = $this->parseStructuredIntentWithRepair(
                'my_week_plan',
                (string)($provider['public_id'] ?? ''),
                (string)$resolvedModel,
                (string)($promptEnvelope['system_prompt'] ?? ''),
                (string)($llm['text'] ?? '')
            );
            if (!(bool)($structured['ok'] ?? false)) {
                $this->logStructuredIntentInvalid('my_week_plan', $provider, $resolvedModel, $structured);
                return ['ok' => false, 'code' => 'AI_STRUCTURED_RESPONSE_INVALID'];
            }
            $planPayload = $this->mergeMyWeekPlanWithFallback((array)($structured['payload'] ?? $planPayload), $fallbackPlanPayload);
            $meta = is_array($planPayload['meta'] ?? null) ? (array)$planPayload['meta'] : [];
            $meta['mode'] = 'llm';
            $meta['parse_ok'] = true;
            $meta['validation_ok'] = true;
            $meta['repair_attempted'] = (bool)($structured['repair_attempted'] ?? false);
            $meta['fallback_used'] = false;
            $meta['raw_text_used'] = false;
            $planPayload['meta'] = $meta;
            $llmMode = 'llm';
            $this->logger->audit([
                'action' => 'ai_structured_intent_result',
                'intent_code' => 'my_week_plan',
                'provider_code' => (string)($provider['provider_code'] ?? ''),
                'model' => $resolvedModel,
                'expected_schema' => 'my_week_plan',
                'parse_ok' => true,
                'validation_ok' => true,
                'repair_attempted' => (bool)($structured['repair_attempted'] ?? false),
                'fallback_used' => false,
                'raw_text_used' => false,
                'action_count' => count((array)($planPayload['suggested_actions'] ?? [])),
                'risk_count' => count((array)($planPayload['risks'] ?? [])),
                'question_count' => count((array)($planPayload['questions'] ?? [])),
                'suggested_task_count' => count((array)($planPayload['suggested_tasks'] ?? [])),
                'error_code' => null,
            ]);
        } elseif ($llmOk) {
            $planPayload = $this->mergeLlmTextIntoPayload('my_week_plan', $planPayload, (string)$llm['text']);
        }
        $planPayload = $this->mergeMyWeekPlanWithFallback($planPayload, $fallbackPlanPayload);
        $now = gmdate('Y-m-d H:i:s');
        $suggestionPublicId = $this->runtime->createSuggestion([
            'intent_code' => 'my_week_plan',
            'entity_type' => 'user',
            'entity_public_id' => $actorPublicId,
            'summary' => (string)($planPayload['summary'] ?? ''),
            'suggestion_json' => json_encode($planPayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'status' => 'draft',
            'cache_key' => $cacheKey,
            'dependency_fingerprint' => $dependencyFingerprint,
            'cache_status' => 'fresh',
            'stale_reason' => null,
            'date_bucket' => $dateBucket,
            'provider_public_id' => (string)($provider['public_id'] ?? ''),
            'provider_code' => (string)($provider['provider_code'] ?? ''),
            'model' => $resolvedModel,
            'last_used_at' => $now,
            'usage_count' => 1,
            'result_meta_json' => json_encode([
                'cache' => [
                    'dependency_fingerprint' => $dependencyFingerprint,
                    'date_bucket' => $dateBucket,
                ],
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'created_by_user_id' => (int)($actor['id'] ?? 0) ?: null,
            'confirmed_by_user_id' => null,
            'created_at' => $now,
            'updated_at' => $now,
            'expires_at' => null,
        ]);

        $jobPayload = [
            'action_type' => 'my_week_plan',
            'intent_code' => 'my_week_plan',
            'provider_public_id' => (string)($provider['public_id'] ?? ''),
            'model' => $resolvedModel,
            'scope_type' => 'user',
            'scope_public_id' => $actorPublicId,
            'suggestion_public_id' => $suggestionPublicId,
            'intent_setting_public_id' => (string)($intent['public_id'] ?? ''),
            'prompt_public_id' => (string)($prompt['public_id'] ?? ''),
            'prompt_version' => (int)($prompt['version'] ?? 0),
            'prompt_runtime' => $this->sanitizePromptRuntimeForStorage($promptEnvelope),
            'input' => $this->sanitizeInput($input),
        ];
        $resultPayload = [
            'mode' => $llmMode,
            'suggestion_public_id' => $suggestionPublicId,
        ];

        $jobPublicId = $this->runtime->createJob([
            'job_type' => 'interactive',
            'action_type' => 'my_week_plan',
            'intent_code' => 'my_week_plan',
            'status' => 'completed',
            'requested_by_user_id' => (int)($actor['id'] ?? 0) ?: null,
            'scope_type' => 'user',
            'scope_public_id' => $actorPublicId,
            'idempotency_key_hash' => null,
            'payload_json' => json_encode($jobPayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'result_json' => json_encode($resultPayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'error_code' => $llmOk ? null : (string)($llm['code'] ?? 'AI_PROVIDER_UNAVAILABLE'),
            'error_message' => null,
            'created_at' => $now,
            'started_at' => $now,
            'finished_at' => $now,
            'updated_at' => $now,
        ]);

        $this->writeUsageLog([
            'user_id' => (int)($actor['id'] ?? 0) ?: null,
            'provider_public_id' => (string)($provider['public_id'] ?? ''),
            'action_type' => 'my_week_plan',
            'intent_code' => 'my_week_plan',
            'status' => 'completed',
            'error_code' => $llmOk ? null : (string)($llm['code'] ?? 'AI_PROVIDER_UNAVAILABLE'),
            'request_tokens' => (int)($llm['request_tokens'] ?? 0),
            'response_tokens' => (int)($llm['response_tokens'] ?? 0),
            'total_tokens' => (int)($llm['total_tokens'] ?? 0),
            'latency_ms' => (int)($llm['latency_ms'] ?? 0),
            'is_sensitive_context' => 0,
            'request_meta' => json_encode([
                'mode' => $llmMode,
                'scope_type' => 'user',
                'scope_public_id' => $actorPublicId,
                'suggestion_public_id' => $suggestionPublicId,
                'intent_setting_public_id' => (string)($intent['public_id'] ?? ''),
                'prompt_public_id' => (string)($prompt['public_id'] ?? ''),
                'prompt_runtime' => [
                    'context_budget_tokens' => (int)($promptEnvelope['meta']['context_budget_tokens'] ?? 0),
                    'context_estimated_tokens' => (int)($promptEnvelope['meta']['context_estimated_tokens'] ?? 0),
                    'context_truncated' => (bool)($promptEnvelope['meta']['context_truncated'] ?? false),
                ],
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'created_at' => $now,
        ]);

        $this->logger->audit([
            'action' => 'ai_suggestion_created',
            'actor_public_id' => $actorPublicId,
            'entity_type' => 'ai_suggestion',
            'entity_public_id' => $suggestionPublicId,
            'intent_code' => 'my_week_plan',
            'scope_type' => 'user',
            'scope_public_id' => $actorPublicId,
            'job_public_id' => $jobPublicId,
            'provider_public_id' => (string)($provider['public_id'] ?? ''),
            'provider_code' => (string)($provider['provider_code'] ?? ''),
        ]);

        return [
            'ok' => true,
            'suggestion' => $this->normalizeSuggestion(
                (array)($this->runtime->findSuggestionByPublicId($suggestionPublicId) ?? []),
                true
            ),
            'job_public_id' => $jobPublicId,
        ];
    }

    public function createTaskListPriority(array $input, array $actor): array
    {
        if (!$this->isFeatureEnabledForActor('ai.enabled', $actor, false)) {
            return ['ok' => false, 'code' => 'AI_DISABLED'];
        }
        if (!$this->isFeatureEnabledForActor('ai.task', $actor, false)) {
            return ['ok' => false, 'code' => 'AI_FEATURE_DISABLED'];
        }
        if (!in_array('task_list_priority', $this->actionAllowlist(), true)) {
            return ['ok' => false, 'code' => 'AI_ACTION_TYPE_NOT_ALLOWED'];
        }
        $rate = $this->rateLimit->assertWithinLimits('task_list_priority', $actor);
        if (!(bool)($rate['ok'] ?? false)) {
            return $this->limitFailure($rate, 'AI_RATE_LIMITED');
        }
        $cost = $this->costLimit->assertWithinLimits('task_list_priority', $actor);
        if (!(bool)($cost['ok'] ?? false)) {
            return $this->limitFailure($cost, 'AI_COST_LIMIT_EXCEEDED');
        }

        $intent = $this->intentSettings->findByIntentCode('task_list_priority');
        if ($intent && !(bool)($intent['is_enabled'] ?? true)) {
            return ['ok' => false, 'code' => 'AI_INTENT_DISABLED'];
        }
        if ($intent && trim((string)($intent['feature_flag'] ?? '')) !== '') {
            if (!$this->isFeatureEnabledForActor(trim((string)$intent['feature_flag']), $actor, false)) {
                return ['ok' => false, 'code' => 'AI_FEATURE_DISABLED'];
            }
        }
        if ($intent && trim((string)($intent['required_permission'] ?? '')) !== '') {
            if (!$this->hasActorPermission($actor, trim((string)$intent['required_permission']))) {
                return ['ok' => false, 'code' => 'FORBIDDEN'];
            }
        }

        $provider = null;
        $intentProviderId = (int)($intent['provider_id'] ?? 0);
        if ($intentProviderId > 0) {
            $provider = $this->providers->findById($intentProviderId);
            if ($provider && !(bool)($provider['is_active'] ?? false)) {
                $provider = null;
            }
        }
        if (!$provider) {
            $provider = $this->providers->findDefaultActive() ?? $this->providers->findAnyActive();
        }
        $provider = $this->resolveUsableProvider($provider);
        if (!$provider) {
            return ['ok' => false, 'code' => 'AI_PROVIDER_NOT_CONFIGURED'];
        }

        $taskPublicIds = is_array($input['task_public_ids'] ?? null) ? (array)$input['task_public_ids'] : [];
        $taskPublicIds = array_values(array_unique(array_filter(array_map(static function (mixed $value): string {
            return trim((string)$value);
        }, $taskPublicIds), static function (string $value): bool {
            return $value !== '';
        })));

        $tasks = [];
        foreach (array_slice($taskPublicIds, 0, 200) as $taskPublicId) {
            $task = $this->tasks->get($taskPublicId, $actor);
            if (!$task) {
                continue;
            }
            $tasks[] = [
                'public_id' => (string)($task['public_id'] ?? ''),
                'title' => trim((string)($task['title'] ?? '')),
                'status_code' => trim((string)($task['status_code'] ?? '')),
                'priority_code' => trim((string)($task['priority_code'] ?? '')),
                'due_at' => (string)($task['due_at'] ?? ''),
                'parent_task_public_id' => trim((string)($task['parent_task_public_id'] ?? '')),
                'parent_task_title' => trim((string)($task['parent_task_title'] ?? '')),
                'has_subtasks' => (bool)($task['has_subtasks'] ?? false),
            ];
        }

        if ($tasks === []) {
            return ['ok' => false, 'code' => 'AI_TASK_LIST_EMPTY'];
        }

        $viewMode = trim((string)($input['view_mode'] ?? 'list'));
        if (!in_array($viewMode, ['list', 'tree', 'cards'], true)) {
            $viewMode = 'list';
        }
        $filters = is_array($input['filters'] ?? null) ? (array)$input['filters'] : [];
        $context = $this->minimizeContextForIntent('task_list_priority', [
            'tasks' => $tasks,
            'view_mode' => $viewMode,
            'filters' => [
                'search' => trim((string)($filters['search'] ?? '')),
                'status' => trim((string)($filters['status'] ?? '')),
                'priority' => trim((string)($filters['priority'] ?? '')),
                'sort' => trim((string)($filters['sort'] ?? '')),
                'order' => trim((string)($filters['order'] ?? '')),
            ],
        ]);

        $planPayload = $this->buildTaskListPrioritySuggestion($context);
        $schemaValidation = $this->promptSchemas->validatePayloadBySchema('task_list_priority', $planPayload);
        if (!(bool)($schemaValidation['ok'] ?? false)) {
            return ['ok' => false, 'code' => (string)($schemaValidation['code'] ?? 'AI_SCHEMA_VALIDATION_FAILED')];
        }
        $planPayload = $this->sanitizePayloadByIntentSchema('task_list_priority', $planPayload);

        $locale = trim((string)($actor['locale'] ?? '')) !== '' ? trim((string)$actor['locale']) : 'ru-ru';
        $prompt = $this->promptSchemas->resolveActivePrompt('task_list_priority', $locale);
        $strictPromptMasking = $this->useStrictPromptMaskingForProvider($provider);
        $promptEnvelope = $this->promptBuilder->buildPromptEnvelope(
            'task_list_priority',
            $prompt,
            $context,
            $input,
            0,
            $strictPromptMasking
        );
        $resolvedModel = trim((string)($intent['model'] ?? '')) !== '' ? trim((string)$intent['model']) : (string)($provider['default_model'] ?? '');
        $forceRefresh = $this->isForceRefreshRequested($input);
        $actorPublicId = (string)($actor['public_id'] ?? '');
        if (!$this->isValidPublicId($actorPublicId)) {
            return ['ok' => false, 'code' => 'AI_SCOPE_PUBLIC_ID_INVALID'];
        }
        $dateBucket = $this->resolveCacheDateBucket('task_list_priority');
        $cacheKey = $this->buildCacheKey(
            (int)($actor['id'] ?? 0),
            $actor,
            'task_list_priority',
            'task_list',
            $actorPublicId,
            (string)($provider['public_id'] ?? ''),
            $resolvedModel,
            $dateBucket,
            $input,
            (int)($prompt['version'] ?? 0)
        );
        $dependencyFingerprint = $this->buildDependencyFingerprint('task_list_priority', $context, $actor, $dateBucket, (int)($prompt['version'] ?? 0));
        $cachedForFallback = $this->runtime->findLatestSuggestionByCacheKey(
            'task_list_priority',
            'task_list',
            $actorPublicId,
            (int)($actor['id'] ?? 0),
            $cacheKey
        );
        if (!$forceRefresh) {
            $cachedResponse = $this->resolveCachedSuggestionResponse(
                $cachedForFallback,
                $dependencyFingerprint,
                $dateBucket,
                (string)($provider['public_id'] ?? ''),
                $resolvedModel
            );
            if ($cachedResponse !== null) {
                $this->runtime->markSuggestionUsed((string)($cachedForFallback['public_id'] ?? ''), gmdate('Y-m-d H:i:s'));
                return [
                    'ok' => true,
                    'suggestion' => $cachedResponse,
                    'job_public_id' => '',
                ];
            }
        }
        $structuredIntent = $this->isStructuredIntent('task_list_priority');
        $llmPayload = [
            'intent_code' => 'task_list_priority',
            'system_prompt' => (string)($promptEnvelope['system_prompt'] ?? '') . ($structuredIntent ? "\n\n" . $this->structuredResponseInstruction('task_list_priority') : ''),
            'user_prompt' => (string)($promptEnvelope['user_prompt'] ?? ''),
            'context' => (array)($promptEnvelope['context'] ?? []),
            'model' => $resolvedModel,
        ];
        if ($structuredIntent) {
            $llmPayload['response_format'] = ['type' => 'json_object'];
        }
        $llm = $this->aiProviderService->completeText((string)($provider['public_id'] ?? ''), $llmPayload);
        $llmOk = (bool)($llm['ok'] ?? false) && trim((string)($llm['text'] ?? '')) !== '';
        $llmResolution = $this->resolveLlmExecution($provider, $llm);
        if (!(bool)($llmResolution['ok'] ?? false)) {
            $cachedDueError = $this->resolveStaleCacheDueToAiError(
                $cachedForFallback,
                $dependencyFingerprint,
                $dateBucket,
                (string)($provider['public_id'] ?? ''),
                $resolvedModel,
                (string)($llmResolution['code'] ?? 'AI_PROVIDER_UNAVAILABLE')
            );
            if ($cachedDueError !== null) {
                $this->runtime->markSuggestionUsed((string)($cachedForFallback['public_id'] ?? ''), gmdate('Y-m-d H:i:s'));
                return [
                    'ok' => true,
                    'suggestion' => $cachedDueError,
                    'job_public_id' => '',
                ];
            }
            return ['ok' => false, 'code' => (string)($llmResolution['code'] ?? 'AI_PROVIDER_UNAVAILABLE')];
        }
        $llmMode = $llmOk ? 'llm' : 'safe_mock';
        if ($structuredIntent) {
            if (!$llmOk) {
                return ['ok' => false, 'code' => 'AI_STRUCTURED_RESPONSE_INVALID'];
            }
            $structured = $this->parseStructuredIntentWithRepair(
                'task_list_priority',
                (string)($provider['public_id'] ?? ''),
                (string)$resolvedModel,
                (string)($promptEnvelope['system_prompt'] ?? ''),
                (string)($llm['text'] ?? '')
            );
            if (!(bool)($structured['ok'] ?? false)) {
                $this->logStructuredIntentInvalid('task_list_priority', $provider, $resolvedModel, $structured);
                return ['ok' => false, 'code' => 'AI_STRUCTURED_RESPONSE_INVALID'];
            }
            $planPayload = (array)($structured['payload'] ?? $planPayload);
            $meta = is_array($planPayload['meta'] ?? null) ? (array)$planPayload['meta'] : [];
            $meta['mode'] = 'llm';
            $meta['parse_ok'] = true;
            $meta['validation_ok'] = true;
            $meta['repair_attempted'] = (bool)($structured['repair_attempted'] ?? false);
            $meta['fallback_used'] = false;
            $meta['raw_text_used'] = false;
            $planPayload['meta'] = $meta;
            $llmMode = 'llm';
            $this->logger->audit([
                'action' => 'ai_structured_intent_result',
                'intent_code' => 'task_list_priority',
                'provider_code' => (string)($provider['provider_code'] ?? ''),
                'model' => $resolvedModel,
                'expected_schema' => 'task_list_priority',
                'parse_ok' => true,
                'validation_ok' => true,
                'repair_attempted' => (bool)($structured['repair_attempted'] ?? false),
                'fallback_used' => false,
                'raw_text_used' => false,
                'action_count' => count((array)($planPayload['suggested_actions'] ?? [])),
                'risk_count' => count((array)($planPayload['risks'] ?? [])),
                'question_count' => count((array)($planPayload['questions'] ?? [])),
                'suggested_task_count' => count((array)($planPayload['suggested_tasks'] ?? [])),
                'error_code' => null,
            ]);
        } elseif ($llmOk) {
            $planPayload = $this->mergeLlmTextIntoPayload('task_list_priority', $planPayload, (string)$llm['text']);
        }
        $now = gmdate('Y-m-d H:i:s');
        $suggestionPublicId = $this->runtime->createSuggestion([
            'intent_code' => 'task_list_priority',
            'entity_type' => 'task_list',
            'entity_public_id' => $actorPublicId,
            'summary' => (string)($planPayload['summary'] ?? ''),
            'suggestion_json' => json_encode($planPayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'status' => 'draft',
            'cache_key' => $cacheKey,
            'dependency_fingerprint' => $dependencyFingerprint,
            'cache_status' => 'fresh',
            'stale_reason' => null,
            'date_bucket' => $dateBucket,
            'provider_public_id' => (string)($provider['public_id'] ?? ''),
            'provider_code' => (string)($provider['provider_code'] ?? ''),
            'model' => $resolvedModel,
            'last_used_at' => $now,
            'usage_count' => 1,
            'result_meta_json' => json_encode([
                'cache' => [
                    'dependency_fingerprint' => $dependencyFingerprint,
                    'date_bucket' => $dateBucket,
                ],
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'created_by_user_id' => (int)($actor['id'] ?? 0) ?: null,
            'confirmed_by_user_id' => null,
            'created_at' => $now,
            'updated_at' => $now,
            'expires_at' => null,
        ]);

        $jobPayload = [
            'action_type' => 'task_list_priority',
            'intent_code' => 'task_list_priority',
            'provider_public_id' => (string)($provider['public_id'] ?? ''),
            'model' => $resolvedModel,
            'scope_type' => 'task_list',
            'scope_public_id' => $actorPublicId,
            'suggestion_public_id' => $suggestionPublicId,
            'intent_setting_public_id' => (string)($intent['public_id'] ?? ''),
            'prompt_public_id' => (string)($prompt['public_id'] ?? ''),
            'prompt_version' => (int)($prompt['version'] ?? 0),
            'prompt_runtime' => $this->sanitizePromptRuntimeForStorage($promptEnvelope),
            'input' => $this->sanitizeInput($input),
        ];
        $resultPayload = [
            'mode' => $llmMode,
            'suggestion_public_id' => $suggestionPublicId,
        ];

        $jobPublicId = $this->runtime->createJob([
            'job_type' => 'interactive',
            'action_type' => 'task_list_priority',
            'intent_code' => 'task_list_priority',
            'status' => 'completed',
            'requested_by_user_id' => (int)($actor['id'] ?? 0) ?: null,
            'scope_type' => 'task_list',
            'scope_public_id' => $actorPublicId,
            'idempotency_key_hash' => null,
            'payload_json' => json_encode($jobPayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'result_json' => json_encode($resultPayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'error_code' => $llmOk ? null : (string)($llm['code'] ?? 'AI_PROVIDER_UNAVAILABLE'),
            'error_message' => null,
            'created_at' => $now,
            'started_at' => $now,
            'finished_at' => $now,
            'updated_at' => $now,
        ]);

        $this->writeUsageLog([
            'user_id' => (int)($actor['id'] ?? 0) ?: null,
            'provider_public_id' => (string)($provider['public_id'] ?? ''),
            'action_type' => 'task_list_priority',
            'intent_code' => 'task_list_priority',
            'status' => 'completed',
            'error_code' => $llmOk ? null : (string)($llm['code'] ?? 'AI_PROVIDER_UNAVAILABLE'),
            'request_tokens' => (int)($llm['request_tokens'] ?? 0),
            'response_tokens' => (int)($llm['response_tokens'] ?? 0),
            'total_tokens' => (int)($llm['total_tokens'] ?? 0),
            'latency_ms' => (int)($llm['latency_ms'] ?? 0),
            'is_sensitive_context' => 0,
            'request_meta' => json_encode([
                'mode' => $llmMode,
                'scope_type' => 'task_list',
                'scope_public_id' => $actorPublicId,
                'suggestion_public_id' => $suggestionPublicId,
                'selection_count' => count($tasks),
                'intent_setting_public_id' => (string)($intent['public_id'] ?? ''),
                'prompt_public_id' => (string)($prompt['public_id'] ?? ''),
                'prompt_runtime' => [
                    'context_budget_tokens' => (int)($promptEnvelope['meta']['context_budget_tokens'] ?? 0),
                    'context_estimated_tokens' => (int)($promptEnvelope['meta']['context_estimated_tokens'] ?? 0),
                    'context_truncated' => (bool)($promptEnvelope['meta']['context_truncated'] ?? false),
                ],
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'created_at' => $now,
        ]);

        $this->logger->audit([
            'action' => 'ai_suggestion_created',
            'actor_public_id' => $actorPublicId,
            'entity_type' => 'ai_suggestion',
            'entity_public_id' => $suggestionPublicId,
            'intent_code' => 'task_list_priority',
            'scope_type' => 'task_list',
            'scope_public_id' => $actorPublicId,
            'job_public_id' => $jobPublicId,
            'provider_public_id' => (string)($provider['public_id'] ?? ''),
            'provider_code' => (string)($provider['provider_code'] ?? ''),
        ]);

        return [
            'ok' => true,
            'suggestion' => $this->normalizeSuggestion(
                (array)($this->runtime->findSuggestionByPublicId($suggestionPublicId) ?? []),
                true
            ),
            'job_public_id' => $jobPublicId,
        ];
    }

    public function list(array $filters, array $actor): array
    {
        [$items, $total, $page, $limit] = $this->runtime->listSuggestions(
            $filters,
            $this->canViewAllSuggestions($actor),
            (int)($actor['id'] ?? 0)
        );

        $normalized = [];
        foreach ($items as $item) {
            if (!$this->canReadSuggestion((array)$item, $actor)) {
                continue;
            }
            $normalized[] = $this->normalizeSuggestion((array)$item, false);
        }

        return [
            'items' => $normalized,
            'meta' => [
                'pagination' => [
                    'page' => $page,
                    'limit' => $limit,
                    'total' => $total,
                    'pages' => (int)ceil($total / max(1, $limit)),
                ],
            ],
        ];
    }

    public function get(string $publicId, array $actor): ?array
    {
        $item = $this->runtime->findSuggestionByPublicId($publicId);
        if (!$item) {
            return null;
        }

        if (!$this->canReadSuggestion($item, $actor)) {
            return null;
        }

        return $this->normalizeSuggestion($item, true);
    }

    public function dismiss(string $publicId, array $actor): array
    {
        $item = $this->runtime->findSuggestionByPublicId($publicId);
        if (!$item || !$this->canReadSuggestion($item, $actor)) {
            return ['ok' => false, 'code' => 'AI_SUGGESTION_NOT_FOUND'];
        }

        $status = (string)($item['status'] ?? 'draft');
        if ($status === 'applied') {
            return ['ok' => true, 'suggestion' => $this->normalizeSuggestion($item, true)];
        }
        if ($status === 'dismissed') {
            return ['ok' => true, 'suggestion' => $this->normalizeSuggestion($item, true)];
        }

        $now = gmdate('Y-m-d H:i:s');
        $this->runtime->updateSuggestionByPublicId($publicId, [
            'status' => 'dismissed',
            'confirmed_by_user_id' => (int)($actor['id'] ?? 0) ?: null,
            'updated_at' => $now,
        ]);

        $updated = (array)($this->runtime->findSuggestionByPublicId($publicId) ?? []);
        $this->logger->audit([
            'action' => 'ai_suggestion_dismissed',
            'actor_public_id' => $actor['public_id'] ?? null,
            'entity_type' => 'ai_suggestion',
            'entity_public_id' => $publicId,
            'intent_code' => (string)($updated['intent_code'] ?? ''),
        ]);

        return ['ok' => true, 'suggestion' => $this->normalizeSuggestion($updated, true)];
    }

    public function previewApply(string $publicId, array $actor): array
    {
        $item = $this->runtime->findSuggestionByPublicId($publicId);
        if (!$item || !$this->canReadSuggestion($item, $actor)) {
            return ['ok' => false, 'code' => 'AI_SUGGESTION_NOT_FOUND'];
        }

        $status = strtolower(trim((string)($item['status'] ?? '')));
        if ($status !== '' && $status !== 'draft') {
            return ['ok' => false, 'code' => 'AI_SUGGESTION_NOT_ACTIONABLE'];
        }
        $expiresAt = trim((string)($item['expires_at'] ?? ''));
        if ($expiresAt !== '') {
            $expiresTs = strtotime($expiresAt);
            if (is_int($expiresTs) && $expiresTs > 0 && $expiresTs <= time()) {
                return ['ok' => false, 'code' => 'AI_SUGGESTION_NOT_ACTIONABLE'];
            }
        }

        $suggestion = $this->normalizeSuggestion($item, true);
        $intentCode = (string)($item['intent_code'] ?? '');
        $enabledActionTypes = array_fill_keys($this->getEnabledActionTypesForPreview(), true);

        $preview = [
            'intent_code' => $intentCode,
            'entity_type' => (string)($item['entity_type'] ?? ''),
            'entity_public_id' => (string)($item['entity_public_id'] ?? ''),
            'changes' => [],
            'supported_apply_endpoints' => [],
            'requires_confirmation' => true,
            'auto_apply' => false,
        ];

        if ($intentCode === 'task_summary' && isset($enabledActionTypes['update_task_description'])) {
            $payload = is_array($suggestion['payload'] ?? null) ? (array)$suggestion['payload'] : [];
            $improvedDescriptionRaw = $payload['improved_description'] ?? null;
            if (
                is_string($improvedDescriptionRaw)
                && $this->isActionPayloadValid('update_task_description', ['description' => $improvedDescriptionRaw])
            ) {
                $improvedDescription = trim($improvedDescriptionRaw);
                $preview['changes'][] = [
                    'type' => 'update_task_description',
                    'field' => 'task.description',
                    'label' => $this->t('ai_suggestion/messages.update_task_description'),
                    'value' => $improvedDescription,
                    'risk_level' => 'high',
                    'requires_row_version' => true,
                ];
                $preview['supported_apply_endpoints'] = [
                    '/api/v1/tasks/{public_id}',
                ];
            }
        }

        if ($intentCode === 'task_comment_draft' && isset($enabledActionTypes['create_comment_draft'])) {
            $payload = is_array($suggestion['payload'] ?? null) ? (array)$suggestion['payload'] : [];
            $commentDraftRaw = $payload['comment_draft'] ?? ($suggestion['summary'] ?? null);
            if (
                is_string($commentDraftRaw)
                && $this->isActionPayloadValid('create_comment_draft', ['body' => $commentDraftRaw])
            ) {
                $commentDraft = trim($commentDraftRaw);
                $preview['changes'][] = [
                    'type' => 'create_comment_draft',
                    'field' => 'task_comment_draft.body',
                    'label' => $this->t('ai_suggestion/messages.save_ai_comment_draft'),
                    'value' => $commentDraft,
                    'risk_level' => 'low',
                ];
                $preview['supported_apply_endpoints'] = [
                    '/api/v1/tasks/{public_id}/comment-draft',
                ];
            }
        }

        if ($intentCode === 'task_decomposition' && isset($enabledActionTypes['create_subtask'])) {
            $payload = is_array($suggestion['payload'] ?? null) ? (array)$suggestion['payload'] : [];
            $suggestedTasks = is_array($payload['suggested_tasks'] ?? null) ? (array)$payload['suggested_tasks'] : [];
            foreach ($suggestedTasks as $index => $task) {
                if (!is_array($task)) {
                    continue;
                }
                $titleRaw = $task['title'] ?? null;
                $descriptionRaw = $task['description'] ?? '';
                if (!is_string($titleRaw)) {
                    continue;
                }
                if ($descriptionRaw !== null && !is_string($descriptionRaw)) {
                    continue;
                }
                if (
                    !$this->isActionPayloadValid('create_subtask', [
                        'title' => $titleRaw,
                        'description' => (string)$descriptionRaw,
                    ])
                ) {
                    continue;
                }
                $title = trim($titleRaw);
                $description = trim((string)$descriptionRaw);
                $preview['changes'][] = [
                    'type' => 'create_subtask',
                    'field' => 'subtask.title',
                    'label' => $this->t('ai_suggestion/messages.create_subtask') . ' #' . (string)($index + 1),
                    'value' => $title,
                    'risk_level' => 'medium',
                    'meta' => [
                        'description' => $description,
                    ],
                ];
            }
            if ($preview['changes'] !== []) {
                $preview['supported_apply_endpoints'] = [
                    '/api/v1/tasks/{public_id}/subtasks',
                ];
            }
        }

        if ($intentCode === 'task_checklist') {
            $payload = is_array($suggestion['payload'] ?? null) ? (array)$suggestion['payload'] : [];
            $items = is_array($payload['checklist'] ?? null) ? (array)$payload['checklist'] : [];
            $canCreateChecklist = isset($enabledActionTypes['create_checklist']);
            $canCreateChecklistItem = isset($enabledActionTypes['create_checklist_item']);
            if ($items !== [] && ($canCreateChecklist || $canCreateChecklistItem)) {
                foreach ($items as $index => $item) {
                    if (!is_array($item)) {
                        continue;
                    }
                    $title = trim((string)($item['title'] ?? ''));
                    if ($title === '') {
                        continue;
                    }
                    if (!$canCreateChecklistItem || !$this->isActionPayloadValid('create_checklist_item', ['title' => $title, 'checklist_title' => 'AI'])) {
                        continue;
                    }
                    $preview['changes'][] = [
                        'type' => 'create_checklist_item',
                        'field' => 'task.checklist_item',
                        'label' => $this->t('ai_suggestion/messages.add_checklist_item') . ' #' . (string)($index + 1),
                        'value' => $title,
                        'risk_level' => 'low',
                        'meta' => [
                            'description' => trim((string)($item['description'] ?? '')),
                            'priority' => in_array((string)($item['priority'] ?? 'medium'), ['high', 'medium', 'low'], true) ? (string)$item['priority'] : 'medium',
                            'checklist_title' => 'AI checklist',
                        ],
                    ];
                }
                if ($preview['changes'] !== []) {
                    $preview['supported_apply_endpoints'] = ['/api/v1/checklists/{public_id}/items'];
                }
            }
        }

        if ($intentCode === 'task_next_action') {
            $payload = is_array($suggestion['payload'] ?? null) ? (array)$suggestion['payload'] : [];
            $summaryRaw = $payload['summary'] ?? ($suggestion['summary'] ?? null);
            if (
                is_string($summaryRaw)
                && isset($enabledActionTypes['create_comment_draft'])
                && $this->isActionPayloadValid('create_comment_draft', ['body' => $summaryRaw])
            ) {
                $summary = trim($summaryRaw);
                $preview['changes'][] = [
                    'type' => 'create_comment_draft',
                    'field' => 'task_comment_draft.body',
                    'label' => $this->t('ai_suggestion/messages.save_next_step_to_comment_draft'),
                    'value' => $summary,
                    'risk_level' => 'low',
                ];
            }
            $suggestedTasks = is_array($payload['suggested_tasks'] ?? null) ? (array)$payload['suggested_tasks'] : [];
            if ($suggestedTasks !== [] && is_array($suggestedTasks[0] ?? null) && isset($enabledActionTypes['create_subtask'])) {
                $firstTask = (array)$suggestedTasks[0];
                $titleRaw = $firstTask['title'] ?? null;
                if (is_string($titleRaw) && $this->isActionPayloadValid('create_follow_up_task', ['title' => $titleRaw])) {
                    $title = trim($titleRaw);
                    $preview['changes'][] = [
                        'type' => 'create_follow_up_task',
                        'field' => 'subtask.title',
                        'label' => $this->t('ai_suggestion/messages.create_follow_up_subtask'),
                        'value' => $title,
                        'risk_level' => 'high',
                        'requires_explicit_selection' => true,
                    ];
                }
            }
            if ($preview['changes'] !== []) {
                $supportedEndpoints = [];
                if (isset($enabledActionTypes['create_comment_draft'])) {
                    $supportedEndpoints[] = '/api/v1/tasks/{public_id}/comment-draft';
                }
                if (isset($enabledActionTypes['create_subtask'])) {
                    $supportedEndpoints[] = '/api/v1/tasks/{public_id}/subtasks';
                }
                $preview['supported_apply_endpoints'] = $supportedEndpoints;
            }
        }

        return [
            'ok' => true,
            'suggestion' => $suggestion,
            'preview' => $preview,
        ];
    }

    public function confirm(string $publicId, array $input, array $actor): array
    {
        $item = $this->runtime->findSuggestionByPublicId($publicId);
        if (!$item || !$this->canReadSuggestion($item, $actor)) {
            return ['ok' => false, 'code' => 'AI_SUGGESTION_NOT_FOUND'];
        }

        $decision = strtolower(trim((string)($input['decision'] ?? 'applied')));
        if (!in_array($decision, ['applied', 'dismissed', 'failed', 'partially_applied'], true)) {
            return ['ok' => false, 'code' => 'AI_SUGGESTION_CONFIRM_INVALID_DECISION'];
        }

        $expectedRowVersion = (int)($input['row_version'] ?? 0);
        $entityType = trim((string)($item['entity_type'] ?? ''));
        $entityPublicId = trim((string)($item['entity_public_id'] ?? ''));
        if ($expectedRowVersion > 0 && $entityType === 'task' && $entityPublicId !== '') {
            $task = $this->tasks->get($entityPublicId, $actor);
            if (!$task) {
                return ['ok' => false, 'code' => 'TASK_NOT_FOUND'];
            }

            $currentRowVersion = (int)($task['row_version'] ?? 0);
            if ($currentRowVersion > 0 && $currentRowVersion !== $expectedRowVersion) {
                return [
                    'ok' => false,
                    'code' => 'AI_ROW_VERSION_CONFLICT',
                    'row_version' => $currentRowVersion,
                ];
            }
        }

        $now = gmdate('Y-m-d H:i:s');
        $set = [
            'status' => $decision,
            'confirmed_by_user_id' => (int)($actor['id'] ?? 0) ?: null,
            'updated_at' => $now,
        ];
        $this->runtime->updateSuggestionByPublicId($publicId, $set);

        $updated = (array)($this->runtime->findSuggestionByPublicId($publicId) ?? []);
        $meta = [
            'decision' => $decision,
        ];
        if (!empty($input['apply_target'])) {
            $meta['apply_target'] = trim((string)$input['apply_target']);
        }
        if (!empty($input['apply_target_public_id'])) {
            $meta['apply_target_public_id'] = trim((string)$input['apply_target_public_id']);
        }
        if (is_array($input['warnings'] ?? null)) {
            $warnings = [];
            foreach ((array)$input['warnings'] as $warning) {
                if (!is_string($warning)) {
                    continue;
                }
                $value = trim($warning);
                if ($value === '') {
                    continue;
                }
                $warnings[] = $value;
                if (count($warnings) >= 10) {
                    break;
                }
            }
            if ($warnings !== []) {
                $meta['warnings'] = $warnings;
            }
        }
        if (is_array($input['applied_action_types'] ?? null)) {
            $applied = [];
            foreach ((array)$input['applied_action_types'] as $actionType) {
                if (!is_string($actionType)) {
                    continue;
                }
                $value = trim($actionType);
                if ($value === '') {
                    continue;
                }
                $applied[] = $value;
                if (count($applied) >= 50) {
                    break;
                }
            }
            if ($applied !== []) {
                $meta['applied_action_types'] = array_values(array_unique($applied));
            }
        }
        if (is_array($input['skipped_action_types'] ?? null)) {
            $skipped = [];
            foreach ((array)$input['skipped_action_types'] as $actionType) {
                if (!is_string($actionType)) {
                    continue;
                }
                $value = trim($actionType);
                if ($value === '') {
                    continue;
                }
                $skipped[] = $value;
                if (count($skipped) >= 50) {
                    break;
                }
            }
            if ($skipped !== []) {
                $meta['skipped_action_types'] = array_values(array_unique($skipped));
            }
        }

        $this->logger->audit([
            'action' => 'ai_suggestion_confirmed',
            'actor_public_id' => $actor['public_id'] ?? null,
            'entity_type' => 'ai_suggestion',
            'entity_public_id' => $publicId,
            'intent_code' => (string)($updated['intent_code'] ?? ''),
            'meta' => $meta,
        ]);

        return [
            'ok' => true,
            'suggestion' => $this->normalizeSuggestion($updated, true),
        ];
    }

    private function buildTaskSummarySuggestion(array $context): array
    {
        $title = (string)($context['title'] ?? $this->t('ai_suggestion/messages.default_task_title'));
        $status = (string)($context['status'] ?? 'new');
        $priority = (string)($context['priority'] ?? 'normal');
        $dueAt = trim((string)($context['due_at'] ?? ''));
        $dueLabel = $dueAt !== '' ? $dueAt : $this->t('ai_suggestion/messages.no_due_date');

        $shortDescription = trim((string)($context['description'] ?? ''));
        if (strlen($shortDescription) > 280) {
            $shortDescription = substr($shortDescription, 0, 277) . '...';
        }

        $prompt = trim((string)($context['prompt'] ?? ''));
        $isDescriptionImprovePrompt = (bool)preg_match('/(улучш|перепиш|описан|description)/iu', $prompt);
        if ($isDescriptionImprovePrompt) {
            $improvedDescription = $this->buildImprovedTaskDescription($title, $shortDescription, $dueLabel);
            return [
                'summary' => $this->t('ai_suggestion/messages.description_improvement_prepared'),
                'improved_description' => $improvedDescription,
                'risks' => [],
                'suggested_tasks' => [],
                'checklist_items' => [],
                'calendar_slots' => [],
                'questions' => [],
                'meta' => [
                    'mode' => 'safe_mock',
                    'intent_code' => 'task_summary',
                    'scenario' => 'description_improvement',
                ],
            ];
        }

        $summary = sprintf($this->t('ai_suggestion/messages.task_summary_brief'), $title, $status, $priority, $dueLabel);
        if ($shortDescription !== '') {
            $summary .= ' ' . $this->t('ai_suggestion/messages.context_label') . $shortDescription;
        }

        return [
            'summary' => $summary,
            'risks' => $dueAt === '' ? [$this->t('ai_suggestion/messages.task_no_due_date_risk')] : [],
            'suggested_tasks' => [],
            'checklist_items' => [],
            'calendar_slots' => [],
            'questions' => [],
            'meta' => [
                'mode' => 'safe_mock',
                'intent_code' => 'task_summary',
            ],
        ];
    }

    private function buildImprovedTaskDescription(string $title, string $description, string $dueLabel): string
    {
        $normalizedDescription = trim($description);
        if ($normalizedDescription === '') {
            $normalizedDescription = $this->t('ai_suggestion/messages.add_current_context');
        }

        return sprintf($this->t('ai_suggestion/messages.goal_line'), $title, $dueLabel) . "\n"
            . $this->t('ai_suggestion/messages.context_line_prefix') . $normalizedDescription . "\n"
            . $this->t('ai_suggestion/messages.readiness_criteria')
            . $this->t('ai_suggestion/messages.result_checked')
            . $this->t('ai_suggestion/messages.changes_documented')
            . $this->t('ai_suggestion/messages.next_step_fixed');
    }

    /** @param array<int,array<string,mixed>> $candidateTasks */
    /**
     * @param array<string,mixed> $sourceMeta
     */
    private function buildMyDayPlanSuggestion(array $agenda, array $candidateTasks, array $sourceMeta = []): array
    {
        $events = is_array($agenda['events'] ?? null) ? (array)$agenda['events'] : [];
        $tasksDue = is_array($agenda['tasks_due'] ?? null) ? (array)$agenda['tasks_due'] : [];
        $tasks = $this->mergeMyDayTaskCandidates($tasksDue, $candidateTasks);

        $workItems = [];
        $nowTs = time();
        foreach ($tasks as $task) {
            if (!is_array($task)) {
                continue;
            }
            $status = strtolower(trim((string)($task['status_code'] ?? '')));
            if (in_array($status, ['done', 'completed', 'archived', 'cancelled'], true)) {
                continue;
            }
            $priority = strtolower(trim((string)($task['priority_code'] ?? 'normal')));
            $priorityScore = match ($priority) {
                'urgent', 'critical' => 420,
                'high' => 320,
                'normal' => 220,
                'low' => 120,
                default => 180,
            };
            $dueAt = trim((string)($task['due_at'] ?? ''));
            $dueTs = $dueAt !== '' ? strtotime($dueAt) : false;
            $dueScore = 0;
            if (is_int($dueTs) && $dueTs > 0) {
                if ($dueTs < $nowTs) {
                    $dueScore = 180;
                } elseif ($dueTs <= ($nowTs + 12 * 3600)) {
                    $dueScore = 120;
                } elseif ($dueTs <= ($nowTs + 24 * 3600)) {
                    $dueScore = 80;
                } else {
                    $dueScore = 20;
                }
            }
            $blockedPenalty = $status === 'blocked' ? -120 : 0;
            $score = $priorityScore + $dueScore + $blockedPenalty;

            $estimatedMinutes = (int)($task['estimated_minutes'] ?? 0);
            $recommendedMinutes = match ($priority) {
                'urgent', 'critical' => 90,
                'high' => 80,
                'normal' => 55,
                'low' => 45,
                default => 60,
            };
            if ($estimatedMinutes >= 20) {
                $recommendedMinutes = min(110, max(30, $estimatedMinutes));
            }

            $workItems[] = [
                'task_public_id' => (string)($task['public_id'] ?? ''),
                'title' => trim((string)($task['title'] ?? $this->t('ai_suggestion/messages.default_task_title'))),
                'project_title' => trim((string)($task['project_title'] ?? '')),
                'priority_code' => $priority,
                'status_code' => $status,
                'due_at' => $dueAt,
                'score' => $score,
                'recommended_minutes' => $recommendedMinutes,
            ];
        }

        usort($workItems, static function (array $left, array $right): int {
            return ($right['score'] <=> $left['score']);
        });
        $workItems = array_slice($workItems, 0, 6);

        $suggestedTasks = [];
        foreach ($workItems as $index => $item) {
            $reason = [];
            if (in_array((string)$item['priority_code'], ['urgent', 'critical', 'high'], true)) {
                $reason[] = $this->t('ai_suggestion/messages.high_priority');
            }
            if ((string)$item['due_at'] !== '') {
                $reason[] = $this->t('ai_suggestion/messages.has_due_date');
            } else {
                $reason[] = $this->t('ai_suggestion/messages.no_due_regular_work');
            }
            if ((string)$item['status_code'] === 'blocked') {
                $reason[] = $this->t('ai_suggestion/messages.needs_unblock');
            }

            $suggestedTasks[] = [
                'task_public_id' => (string)$item['task_public_id'],
                'title' => (string)$item['title'],
                'project_title' => (string)($item['project_title'] ?? ''),
                'recommended_order' => $index + 1,
                'recommended_minutes' => (int)$item['recommended_minutes'],
                'reason' => implode(', ', $reason),
                'business_reason' => $this->myDayBusinessReason($item),
                'care_note' => $this->myDayCareNote((int)$item['recommended_minutes'], (string)$item['status_code']),
                'due_at' => (string)$item['due_at'],
            ];
        }

        $dayDate = $this->resolveAgendaDayDate($agenda);
        $availableMinutes = $this->estimateMyDayAvailableMinutes($events, $dayDate);
        $reservedBufferMinutes = $this->recommendedMyDayBufferMinutes($availableMinutes);
        $planningBudgetMinutes = max(0, $availableMinutes - $reservedBufferMinutes);
        $demandMinutes = 0;
        foreach ($suggestedTasks as $task) {
            $demandMinutes += max(20, (int)($task['recommended_minutes'] ?? 0));
        }
        [$plannedTasks, $suggestedDeferrals] = $this->fitMyDayTasksIntoAvailableMinutes($suggestedTasks, $planningBudgetMinutes);
        $calendarSlots = $this->buildMyDayCalendarSlots($events, $plannedTasks, $dayDate);
        $plannedMinutes = 0;
        foreach ($plannedTasks as $task) {
            $plannedMinutes += max(0, (int)($task['recommended_minutes'] ?? 0));
        }
        $bufferMinutes = max(0, $availableMinutes - $plannedMinutes);
        $overloadWarnings = [];
        if ($demandMinutes > $planningBudgetMinutes) {
            $overloadWarnings[] = sprintf($this->t('ai_suggestion/messages.overload_demand_exceeds_budget'), $demandMinutes, $planningBudgetMinutes);
        }

        foreach ($plannedTasks as $index => &$plannedTask) {
            $plannedTask['recommended_order'] = $index + 1;
            $plannedTask['order'] = $index + 1;
            $plannedTask['recommended_slot'] = (string)($calendarSlots[$index]['start_at'] ?? '');
        }
        unset($plannedTask);

        $summary = sprintf($this->t('ai_suggestion/messages.day_plan_summary'), count($plannedTasks), $plannedMinutes);
        if ($bufferMinutes > 0) {
            $summary .= ' ' . sprintf($this->t('ai_suggestion/messages.buffer_left'), $bufferMinutes);
        }
        if (count($calendarSlots) > 0) {
            $summary .= ' ' . $this->t('ai_suggestion/messages.slots_distributed');
        }
        if ($suggestedDeferrals !== []) {
            $summary .= ' ' . $this->t('ai_suggestion/messages.tasks_deferred');
            $overloadWarnings[] = $this->t('ai_suggestion/messages.tasks_deferred_reason');
        }

        return [
            'summary' => $summary,
            'risks' => $this->myDayRisks($events, $plannedTasks),
            'suggested_tasks' => $plannedTasks,
            'work_items' => $plannedTasks,
            'available_minutes' => $availableMinutes,
            'planned_minutes' => $plannedMinutes,
            'buffer_minutes' => $bufferMinutes,
            'warnings' => $overloadWarnings,
            'overload_warnings' => $overloadWarnings,
            'suggested_deferrals' => $suggestedDeferrals,
            'checklist_items' => [
                $this->t('ai_suggestion/messages.check_priorities_before_day'),
                $this->t('ai_suggestion/messages.plan_breaks'),
                $this->t('ai_suggestion/messages.mark_progress_end_of_day'),
            ],
            'calendar_slots' => $calendarSlots,
            'questions' => [],
            'meta' => [
                'mode' => 'safe_mock',
                'intent_code' => 'my_day_plan',
                'source_marker' => (string)($sourceMeta['source_marker'] ?? 'manual'),
                'source' => (string)($sourceMeta['source'] ?? 'interactive.my_day_plan'),
                'job_code' => (string)($sourceMeta['job_code'] ?? ''),
                'execution_mode' => (string)($sourceMeta['execution_mode'] ?? 'manual'),
                'marker_version' => 'my_day_source_v1',
            ],
        ];
    }

    /** @param array<int,array<string,mixed>> $candidateTasks */
    private function buildMyWeekPlanSuggestion(array $agenda, array $candidateTasks): array
    {
        $events = is_array($agenda['events'] ?? null) ? (array)$agenda['events'] : [];
        $tasksDue = is_array($agenda['tasks_due'] ?? null) ? (array)$agenda['tasks_due'] : [];
        $tasks = $tasksDue !== [] ? $tasksDue : $candidateTasks;

        $today = new \DateTimeImmutable('today');
        $weekStart = $today->modify('monday this week');
        $days = [];
        for ($i = 0; $i < 7; $i += 1) {
            $dayDate = $weekStart->modify('+' . $i . ' day');
            $dayKey = $dayDate->format('Y-m-d');
            $days[$dayKey] = [
                'date' => $dayKey,
                'label' => $this->weekDayLabel($dayDate),
                'tasks' => [],
                'planned_minutes' => 0,
                'events_count' => 0,
            ];
        }

        foreach ($events as $event) {
            if (!is_array($event)) {
                continue;
            }
            $startsAt = trim((string)($event['starts_at'] ?? ''));
            if ($startsAt === '') {
                continue;
            }
            $dayKey = substr($startsAt, 0, 10);
            if (isset($days[$dayKey])) {
                $days[$dayKey]['events_count'] = (int)$days[$dayKey]['events_count'] + 1;
            }
        }

        foreach ($tasks as $task) {
            if (!is_array($task)) {
                continue;
            }
            $status = strtolower(trim((string)($task['status_code'] ?? '')));
            if (in_array($status, ['done', 'completed', 'archived', 'cancelled'], true)) {
                continue;
            }

            $dueAt = trim((string)($task['due_at'] ?? ''));
            $dueTs = $dueAt !== '' ? strtotime($dueAt) : false;
            $dayKey = is_int($dueTs) && $dueTs > 0
                ? gmdate('Y-m-d', $dueTs)
                : $weekStart->format('Y-m-d');
            if (!isset($days[$dayKey])) {
                continue;
            }

            $priority = strtolower(trim((string)($task['priority_code'] ?? 'normal')));
            $recommendedMinutes = match ($priority) {
                'urgent', 'critical', 'high' => 90,
                'normal' => 60,
                'low' => 45,
                default => 60,
            };

            $reason = [];
            if (in_array($priority, ['urgent', 'critical', 'high'], true)) {
                $reason[] = $this->t('ai_suggestion/messages.high_priority');
            }
            if ($dueAt !== '') {
                $reason[] = $this->t('ai_suggestion/messages.has_due_date');
            }
            if ($status === 'blocked') {
                $reason[] = $this->t('ai_suggestion/messages.has_blocker');
            }
            if ($reason === []) {
                $reason[] = $this->t('ai_suggestion/messages.planned_execution');
            }

            $days[$dayKey]['tasks'][] = [
                'task_public_id' => (string)($task['public_id'] ?? ''),
                'title' => trim((string)($task['title'] ?? $this->t('ai_suggestion/messages.default_task_title'))),
                'priority_code' => $priority,
                'status_code' => $status,
                'due_at' => $dueAt,
                'recommended_minutes' => $recommendedMinutes,
                'reason' => implode(', ', $reason),
            ];
            $days[$dayKey]['planned_minutes'] = (int)$days[$dayKey]['planned_minutes'] + $recommendedMinutes;
        }

        $tasksByDay = [];
        $overloadWarnings = [];
        $risks = [];
        $suggestedEvents = [];
        $totalPlannedMinutes = 0;
        foreach ($days as $dayKey => $dayData) {
            $tasksForDay = is_array($dayData['tasks'] ?? null) ? (array)$dayData['tasks'] : [];
            usort($tasksForDay, static function (array $left, array $right): int {
                $priorityRank = static function (string $value): int {
                    return match ($value) {
                        'critical' => 4,
                        'urgent' => 4,
                        'high' => 3,
                        'normal' => 2,
                        'low' => 1,
                        default => 0,
                    };
                };
                $priorityDiff = $priorityRank((string)($right['priority_code'] ?? '')) <=> $priorityRank((string)($left['priority_code'] ?? ''));
                if ($priorityDiff !== 0) {
                    return $priorityDiff;
                }

                return strcmp((string)($left['due_at'] ?? ''), (string)($right['due_at'] ?? ''));
            });
            $tasksForDay = array_slice($tasksForDay, 0, 6);

            $plannedMinutes = 0;
            foreach ($tasksForDay as $task) {
                $plannedMinutes += (int)($task['recommended_minutes'] ?? 0);
            }

            $eventsCount = (int)($dayData['events_count'] ?? 0);
            $totalPlannedMinutes += $plannedMinutes;
            if ($plannedMinutes > 360) {
                $overloadWarnings[] = sprintf($this->t('ai_suggestion/messages.overload_on_day'), $dayData['label'], $plannedMinutes);
            }
            if ($eventsCount >= 4) {
                $risks[] = sprintf($this->t('ai_suggestion/messages.dense_calendar'), $dayData['label'], $eventsCount);
            }
            if ($tasksForDay !== []) {
                $focusStartHour = $eventsCount >= 3 ? 14 : 10;
                $slotStart = $dayKey . ' ' . str_pad((string)$focusStartHour, 2, '0', STR_PAD_LEFT) . ':00:00';
                $slotEnd = $dayKey . ' ' . str_pad((string)($focusStartHour + 1), 2, '0', STR_PAD_LEFT) . ':00:00';
                $firstTask = $tasksForDay[0];
                $suggestedEvents[] = [
                    'title' => $this->t('ai_suggestion/messages.focus_prefix') . (string)($firstTask['title'] ?? $this->t('ai_suggestion/messages.key_task_fallback')),
                    'starts_at' => $slotStart,
                    'ends_at' => $slotEnd,
                    'task_public_id' => (string)($firstTask['task_public_id'] ?? ''),
                    'reason' => $this->t('ai_suggestion/messages.recommended_weekly_focus'),
                ];
            }

            $tasksByDay[] = [
                'date' => $dayKey,
                'label' => (string)($dayData['label'] ?? $dayKey),
                'planned_minutes' => $plannedMinutes,
                'events_count' => $eventsCount,
                'tasks' => $tasksForDay,
            ];
        }

        if ($risks === []) {
            $risks[] = $this->t('ai_suggestion/messages.no_critical_week_risks');
        }

        $summary = sprintf($this->t('ai_suggestion/messages.week_plan_summary'), array_reduce($tasksByDay, static function (int $carry, array $item): int {
            return $carry + count((array)($item['tasks'] ?? []));
        }, 0), $totalPlannedMinutes);

        return [
            'summary' => $summary,
            'tasks_by_day' => $tasksByDay,
            'risks' => $risks,
            'warnings' => $overloadWarnings,
            'overload_warnings' => $overloadWarnings,
            'suggested_events' => $suggestedEvents,
            'total_planned_minutes' => $totalPlannedMinutes,
            'meta' => [
                'mode' => 'safe_mock',
                'intent_code' => 'my_week_plan',
            ],
        ];
    }

    private function weekDayLabel(\DateTimeImmutable $date): string
    {
        $map = [
            'Mon' => $this->t('ai_suggestion/messages.weekday_mon'),
            'Tue' => $this->t('ai_suggestion/messages.weekday_tue'),
            'Wed' => $this->t('ai_suggestion/messages.weekday_wed'),
            'Thu' => $this->t('ai_suggestion/messages.weekday_thu'),
            'Fri' => $this->t('ai_suggestion/messages.weekday_fri'),
            'Sat' => $this->t('ai_suggestion/messages.weekday_sat'),
            'Sun' => $this->t('ai_suggestion/messages.weekday_sun'),
        ];

        $code = $date->format('D');
        return ($map[$code] ?? $code) . ' ' . $date->format('d.m');
    }

    /**
     * @param array<string,mixed> $context
     * @return array<string,mixed>
     */
    private function buildTaskListPrioritySuggestion(array $context): array
    {
        $tasks = is_array($context['tasks'] ?? null) ? (array)$context['tasks'] : [];
        $viewMode = trim((string)($context['view_mode'] ?? 'list'));
        $filters = is_array($context['filters'] ?? null) ? (array)$context['filters'] : [];
        $nowTs = time();

        $taskMap = [];
        $scores = [];
        $reasons = [];

        foreach ($tasks as $task) {
            if (!is_array($task)) {
                continue;
            }
            $taskPublicId = trim((string)($task['public_id'] ?? ''));
            if ($taskPublicId === '') {
                continue;
            }

            $priorityCode = strtolower(trim((string)($task['priority_code'] ?? 'normal')));
            $statusCode = strtolower(trim((string)($task['status_code'] ?? '')));
            $dueAt = trim((string)($task['due_at'] ?? ''));
            $dueTs = $dueAt !== '' ? strtotime($dueAt) : false;
            $priorityScore = match ($priorityCode) {
                'critical', 'urgent' => 350,
                'high' => 280,
                'normal' => 180,
                'low' => 80,
                default => 120,
            };
            $dueScore = 0;
            if (is_int($dueTs) && $dueTs > 0) {
                if ($dueTs < $nowTs) {
                    $dueScore = 200;
                } elseif ($dueTs <= ($nowTs + 24 * 3600)) {
                    $dueScore = 120;
                } elseif ($dueTs <= ($nowTs + 3 * 24 * 3600)) {
                    $dueScore = 70;
                } else {
                    $dueScore = 20;
                }
            }

            $statusPenalty = in_array($statusCode, ['done', 'completed', 'cancelled', 'archived'], true) ? -500 : 0;
            $score = $priorityScore + $dueScore + $statusPenalty;
            $reasonParts = [];
            if (in_array($priorityCode, ['critical', 'urgent', 'high'], true)) {
                $reasonParts[] = $this->t('ai_suggestion/messages.high_priority');
            }
            if ($dueAt !== '') {
                $reasonParts[] = $this->t('ai_suggestion/messages.has_due_date');
            }
            if ($dueTs !== false && is_int($dueTs) && $dueTs < $nowTs) {
                $reasonParts[] = $this->t('ai_suggestion/messages.task_overdue');
            }
            if ($statusCode === 'blocked') {
                $reasonParts[] = $this->t('ai_suggestion/messages.needs_unblock');
                $score += 40;
            }
            if ($reasonParts === []) {
                $reasonParts[] = $this->t('ai_suggestion/messages.planned_focus');
            }

            $taskMap[$taskPublicId] = [
                'task_public_id' => $taskPublicId,
                'title' => trim((string)($task['title'] ?? $this->t('ai_suggestion/messages.default_task_title'))),
                'status_code' => $statusCode,
                'priority_code' => $priorityCode,
                'due_at' => $dueAt,
                'parent_task_public_id' => trim((string)($task['parent_task_public_id'] ?? '')),
                'parent_task_title' => trim((string)($task['parent_task_title'] ?? '')),
                'has_subtasks' => (bool)($task['has_subtasks'] ?? false),
            ];
            $scores[$taskPublicId] = $score;
            $reasons[$taskPublicId] = implode(', ', $reasonParts);
        }

        $orderedByScore = array_keys($taskMap);
        usort($orderedByScore, function (string $left, string $right) use ($scores, $taskMap): int {
            $scoreDiff = ((int)($scores[$right] ?? 0)) <=> ((int)($scores[$left] ?? 0));
            if ($scoreDiff !== 0) {
                return $scoreDiff;
            }
            $leftDue = (string)(($taskMap[$left]['due_at'] ?? '') ?: '');
            $rightDue = (string)(($taskMap[$right]['due_at'] ?? '') ?: '');
            $dueDiff = strcmp($leftDue, $rightDue);
            if ($dueDiff !== 0) {
                return $dueDiff;
            }

            return strcmp((string)($taskMap[$left]['title'] ?? ''), (string)($taskMap[$right]['title'] ?? ''));
        });

        $orderedTaskIds = [];
        $visited = [];
        $placeTask = function (string $taskPublicId) use (&$placeTask, &$orderedTaskIds, &$visited, $taskMap): void {
            if (isset($visited[$taskPublicId])) {
                return;
            }
            $parentTaskPublicId = trim((string)($taskMap[$taskPublicId]['parent_task_public_id'] ?? ''));
            if ($parentTaskPublicId !== '' && isset($taskMap[$parentTaskPublicId])) {
                $placeTask($parentTaskPublicId);
            }
            $visited[$taskPublicId] = true;
            $orderedTaskIds[] = $taskPublicId;
        };

        foreach ($orderedByScore as $taskPublicId) {
            $placeTask($taskPublicId);
        }

        $rankedTasks = [];
        foreach ($orderedTaskIds as $index => $taskPublicId) {
            $item = $taskMap[$taskPublicId] ?? null;
            if (!is_array($item)) {
                continue;
            }
            $rankedTasks[] = [
                'task_public_id' => $taskPublicId,
                'title' => (string)($item['title'] ?? $this->t('ai_suggestion/messages.default_task_title')),
                'recommended_order' => $index + 1,
                'priority_score' => (int)($scores[$taskPublicId] ?? 0),
                'reason' => (string)($reasons[$taskPublicId] ?? $this->t('ai_suggestion/messages.planned_focus')),
                'status_code' => (string)($item['status_code'] ?? ''),
                'priority_code' => (string)($item['priority_code'] ?? ''),
                'due_at' => (string)($item['due_at'] ?? ''),
                'parent_task_public_id' => (string)($item['parent_task_public_id'] ?? ''),
                'has_subtasks' => (bool)($item['has_subtasks'] ?? false),
            ];
        }

        $selectionCount = count($rankedTasks);
        $summary = sprintf($this->t('ai_suggestion/messages.ai_priority_calculated'), $selectionCount, $selectionCount === 1 ? $this->t('ai_suggestion/messages.task_count_singular') : $this->t('ai_suggestion/messages.task_count_plural'))
            . ' (view: ' . ($viewMode !== '' ? $viewMode : 'list') . ').';

        return [
            'summary' => $summary,
            'ordered_task_ids' => $orderedTaskIds,
            'ranked_tasks' => $rankedTasks,
            'view_mode' => $viewMode !== '' ? $viewMode : 'list',
            'filter_snapshot' => [
                'search' => trim((string)($filters['search'] ?? '')),
                'status' => trim((string)($filters['status'] ?? '')),
                'priority' => trim((string)($filters['priority'] ?? '')),
                'sort' => trim((string)($filters['sort'] ?? '')),
                'order' => trim((string)($filters['order'] ?? '')),
            ],
            'risks' => [],
            'meta' => [
                'mode' => 'safe_mock',
                'intent_code' => 'task_list_priority',
                'selection_count' => $selectionCount,
                'parent_guard' => 'parent_before_subtask',
            ],
        ];
    }

    /**
     * @param array<string,mixed> $input
     * @param array<string,mixed> $actor
     * @return array{source_marker:string,source:string,job_code:string,execution_mode:string}
     */
    private function isRegenerateRequested(array $input): bool
    {
        if (array_key_exists('regenerate', $input)) {
            return (bool)$input['regenerate'];
        }
        $metaInput = is_array($input['meta'] ?? null) ? (array)$input['meta'] : [];
        if (array_key_exists('regenerate', $metaInput)) {
            return (bool)$metaInput['regenerate'];
        }

        return false;
    }

    /**
     * @param array<string,mixed> $input
     * @param array<string,mixed> $actor
     * @return array{source_marker:string,source:string,job_code:string,execution_mode:string}
     */
    private function resolveMyDaySourceMeta(array $input, array $actor): array
    {
        $metaInput = is_array($input['meta'] ?? null) ? (array)$input['meta'] : [];
        $requestedMarker = $this->sanitizeMarkerToken((string)($metaInput['source_marker'] ?? ($input['source_marker'] ?? '')));
        $requestedSource = $this->sanitizeMarkerToken((string)($metaInput['source'] ?? ($input['source'] ?? '')));
        $requestedJobCode = $this->sanitizeMarkerToken((string)($metaInput['job_code'] ?? ($input['job_code'] ?? '')));
        $requestedMode = $this->sanitizeMarkerToken((string)($metaInput['mode'] ?? ($input['mode'] ?? '')));

        $isCronRequested = $requestedMarker === 'daily_work_plan'
            || $requestedMode === 'cron'
            || str_contains($requestedSource, 'daily_work_plan')
            || str_contains($requestedSource, 'cron')
            || str_contains($requestedJobCode, 'daily-work-plan')
            || str_contains($requestedJobCode, 'daily_work_plan');

        if ($isCronRequested && $this->canManageCronJobs($actor)) {
            $jobCode = $requestedJobCode !== '' ? $requestedJobCode : 'ai:user-daily-work-plan';
            if (!preg_match('/^ai:[a-z0-9][a-z0-9:_\-]{2,96}$/', $jobCode)) {
                $jobCode = 'ai:user-daily-work-plan';
            }

            return [
                'source_marker' => 'daily_work_plan',
                'source' => 'cron.daily_work_plan',
                'job_code' => $jobCode,
                'execution_mode' => 'cron',
            ];
        }

        return [
            'source_marker' => 'manual',
            'source' => 'interactive.my_day_plan',
            'job_code' => '',
            'execution_mode' => 'manual',
        ];
    }

    private function isDailyPlanEnabledForScope(string $scopePublicId): bool
    {
        if ($scopePublicId === '') {
            return true;
        }

        $row = $this->settings->get('ai_user:' . $scopePublicId, 'daily_plan_enabled');
        if (!is_array($row) || !array_key_exists('value', $row)) {
            return true;
        }

        return (bool)$row['value'];
    }

    private function sanitizeMarkerToken(string $value): string
    {
        $value = strtolower(trim($value));
        if ($value === '') {
            return '';
        }
        if (mb_strlen($value) > 96) {
            $value = mb_substr($value, 0, 96);
        }

        return (string)preg_replace('/[^a-z0-9:_\.\-]/', '', $value);
    }

    private function buildBackgroundInputHash(array $payload): string
    {
        $normalized = $this->normalizeForHash($payload);
        $json = json_encode($normalized, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (!is_string($json)) {
            return '';
        }

        return hash('sha256', $json);
    }

    private function normalizeForHash(mixed $value): mixed
    {
        if (!is_array($value)) {
            return $value;
        }

        if (array_is_list($value)) {
            return array_map(fn(mixed $item): mixed => $this->normalizeForHash($item), $value);
        }

        $normalized = [];
        $keys = array_keys($value);
        sort($keys, SORT_STRING);
        foreach ($keys as $key) {
            $normalized[(string)$key] = $this->normalizeForHash($value[$key]);
        }

        return $normalized;
    }

    /** @param array<string,mixed> $actor */
    private function canManageCronJobs(array $actor): bool
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

    /**
     * @param callable(array<string,mixed>):array<string,mixed> $payloadBuilder
     */
    private function createTaskIntentSuggestion(
        string $taskPublicId,
        array $input,
        array $actor,
        string $intentCode,
        callable $payloadBuilder
    ): array {
        if (!$this->isFeatureEnabledForActor('ai.enabled', $actor, false)) {
            return ['ok' => false, 'code' => 'AI_DISABLED'];
        }
        if (!$this->isFeatureEnabledForActor('ai.task', $actor, false)) {
            return ['ok' => false, 'code' => 'AI_FEATURE_DISABLED'];
        }
        if (!in_array($intentCode, $this->actionAllowlist(), true)) {
            return ['ok' => false, 'code' => 'AI_ACTION_TYPE_NOT_ALLOWED'];
        }
        if (!$this->isValidPublicId($taskPublicId)) {
            return ['ok' => false, 'code' => 'AI_SCOPE_PUBLIC_ID_INVALID'];
        }
        $rate = $this->rateLimit->assertWithinLimits($intentCode, $actor);
        if (!(bool)($rate['ok'] ?? false)) {
            return $this->limitFailure($rate, 'AI_RATE_LIMITED');
        }
        $cost = $this->costLimit->assertWithinLimits($intentCode, $actor);
        if (!(bool)($cost['ok'] ?? false)) {
            return $this->limitFailure($cost, 'AI_COST_LIMIT_EXCEEDED');
        }

        $intent = $this->intentSettings->findByIntentCode($intentCode);
        if ($intent && !(bool)($intent['is_enabled'] ?? true)) {
            return ['ok' => false, 'code' => 'AI_INTENT_DISABLED'];
        }
        if ($intent && trim((string)($intent['feature_flag'] ?? '')) !== '') {
            if (!$this->isFeatureEnabledForActor(trim((string)$intent['feature_flag']), $actor, false)) {
                return ['ok' => false, 'code' => 'AI_FEATURE_DISABLED'];
            }
        }
        if ($intent && trim((string)($intent['required_permission'] ?? '')) !== '') {
            if (!$this->hasActorPermission($actor, trim((string)$intent['required_permission']))) {
                return ['ok' => false, 'code' => 'FORBIDDEN'];
            }
        }

        $provider = null;
        $intentProviderId = (int)($intent['provider_id'] ?? 0);
        if ($intentProviderId > 0) {
            $provider = $this->providers->findById($intentProviderId);
            if ($provider && !(bool)($provider['is_active'] ?? false)) {
                $provider = null;
            }
        }
        if (!$provider) {
            $provider = $this->providers->findDefaultActive() ?? $this->providers->findAnyActive();
        }
        $provider = $this->resolveUsableProvider($provider);
        if (!$provider) {
            return ['ok' => false, 'code' => 'AI_PROVIDER_NOT_CONFIGURED'];
        }

        $task = $this->tasks->get($taskPublicId, $actor);
        if (!$task) {
            return ['ok' => false, 'code' => 'TASK_NOT_FOUND'];
        }

        $context = $this->contextBuilder->buildFullTaskContext($task, $input, $actor);
        $minimalContext = $this->minimizeContextForIntent($intentCode, $context);
        $payload = $payloadBuilder($minimalContext);
        $schemaValidation = $this->promptSchemas->validatePayloadBySchema($intentCode, $payload);
        if (!(bool)($schemaValidation['ok'] ?? false)) {
            return ['ok' => false, 'code' => (string)($schemaValidation['code'] ?? 'AI_SCHEMA_VALIDATION_FAILED')];
        }
        $payload = $this->sanitizePayloadByIntentSchema($intentCode, $payload);

        $now = gmdate('Y-m-d H:i:s');
        $locale = trim((string)($actor['locale'] ?? '')) !== '' ? trim((string)$actor['locale']) : 'ru-ru';
        $prompt = $this->promptSchemas->resolveActivePrompt($intentCode, $locale);
        $strictPromptMasking = $this->useStrictPromptMaskingForProvider($provider);
        $promptEnvelope = $this->promptBuilder->buildPromptEnvelope($intentCode, $prompt, $minimalContext, $input, 0, $strictPromptMasking);
        $resolvedModel = trim((string)($intent['model'] ?? '')) !== '' ? trim((string)$intent['model']) : (string)($provider['default_model'] ?? '');
        $forceRefresh = $this->isForceRefreshRequested($input);
        $dateBucket = $this->resolveCacheDateBucket($intentCode);
        $cacheKey = $this->buildCacheKey(
            (int)($actor['id'] ?? 0),
            $actor,
            $intentCode,
            'task',
            $taskPublicId,
            (string)($provider['public_id'] ?? ''),
            $resolvedModel,
            $dateBucket,
            $input,
            (int)($prompt['version'] ?? 0)
        );
        $dependencyFingerprint = $this->buildDependencyFingerprint($intentCode, $minimalContext, $actor, $dateBucket, (int)($prompt['version'] ?? 0));
        if (!$forceRefresh) {
            $cached = $this->runtime->findLatestSuggestionByCacheKey(
                $intentCode,
                'task',
                $taskPublicId,
                (int)($actor['id'] ?? 0),
                $cacheKey
            );
            $cachedResponse = $this->resolveCachedSuggestionResponse(
                $cached,
                $dependencyFingerprint,
                $dateBucket,
                (string)($provider['public_id'] ?? ''),
                $resolvedModel
            );
            if ($cachedResponse !== null) {
                $this->runtime->markSuggestionUsed((string)($cached['public_id'] ?? ''), gmdate('Y-m-d H:i:s'));
                return [
                    'ok' => true,
                    'suggestion' => $cachedResponse,
                    'job_public_id' => '',
                ];
            }
        }
        $structuredIntent = $this->isStructuredIntent($intentCode);
        $llmPayload = [
            'intent_code' => $intentCode,
            'system_prompt' => (string)($promptEnvelope['system_prompt'] ?? '') . ($structuredIntent ? "\n\n" . $this->structuredResponseInstruction($intentCode) : ''),
            'user_prompt' => (string)($promptEnvelope['user_prompt'] ?? ''),
            'context' => (array)($promptEnvelope['context'] ?? []),
            'model' => $resolvedModel,
        ];
        if ($structuredIntent) {
            $llmPayload['response_format'] = ['type' => 'json_object'];
        }
        $llm = $this->aiProviderService->completeText((string)($provider['public_id'] ?? ''), $llmPayload);
        $llmOk = (bool)($llm['ok'] ?? false) && trim((string)($llm['text'] ?? '')) !== '';
        $llmResolution = $this->resolveLlmExecution($provider, $llm);
        if (!(bool)($llmResolution['ok'] ?? false)) {
            return ['ok' => false, 'code' => (string)($llmResolution['code'] ?? 'AI_PROVIDER_UNAVAILABLE')];
        }
        $llmMode = $llmOk ? 'llm' : 'safe_mock';
        if ($structuredIntent) {
            if (!$llmOk) {
                return ['ok' => false, 'code' => 'AI_STRUCTURED_RESPONSE_INVALID'];
            }
            $structured = $this->parseStructuredIntentWithRepair(
                $intentCode,
                (string)($provider['public_id'] ?? ''),
                (string)$resolvedModel,
                (string)($promptEnvelope['system_prompt'] ?? ''),
                (string)($llm['text'] ?? '')
            );
            if (!(bool)($structured['ok'] ?? false)) {
                $this->logStructuredIntentInvalid($intentCode, $provider, $resolvedModel, $structured);
                return ['ok' => false, 'code' => 'AI_STRUCTURED_RESPONSE_INVALID'];
            }
            $payload = (array)($structured['payload'] ?? $payload);
            $meta = is_array($payload['meta'] ?? null) ? (array)$payload['meta'] : [];
            $meta['mode'] = 'llm';
            $meta['parse_ok'] = true;
            $meta['validation_ok'] = true;
            $meta['repair_attempted'] = (bool)($structured['repair_attempted'] ?? false);
            $meta['fallback_used'] = false;
            $meta['raw_text_used'] = false;
            $payload['meta'] = $meta;
            $llmMode = 'llm';
            $this->logger->audit([
                'action' => 'ai_structured_intent_result',
                'intent_code' => $intentCode,
                'provider_code' => (string)($provider['provider_code'] ?? ''),
                'model' => $resolvedModel,
                'expected_schema' => $intentCode,
                'parse_ok' => true,
                'validation_ok' => true,
                'repair_attempted' => (bool)($structured['repair_attempted'] ?? false),
                'fallback_used' => false,
                'raw_text_used' => false,
                'checklist_count' => $intentCode === 'task_checklist' ? count((array)($payload['checklist'] ?? [])) : 0,
                'section_count' => $intentCode === 'dashboard_daily_digest' ? count((array)($payload['sections'] ?? [])) : 0,
                'insight_count' => $intentCode === 'dashboard_daily_digest' ? count((array)($payload['insights'] ?? [])) : 0,
                'action_count' => count((array)($payload['suggested_actions'] ?? [])),
                'risk_count' => count((array)($payload['risks'] ?? [])),
                'question_count' => count((array)($payload['questions'] ?? [])),
                'suggested_task_count' => count((array)($payload['suggested_tasks'] ?? [])),
                'error_code' => null,
            ]);
        } elseif ($llmOk) {
            $payload = $this->mergeLlmTextIntoPayload($intentCode, $payload, (string)$llm['text']);
        }

        $suggestionPublicId = $this->runtime->createSuggestion([
            'intent_code' => $intentCode,
            'entity_type' => 'task',
            'entity_public_id' => $taskPublicId,
            'summary' => (string)($payload['summary'] ?? ''),
            'suggestion_json' => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'status' => 'draft',
            'cache_key' => $cacheKey,
            'dependency_fingerprint' => $dependencyFingerprint,
            'cache_status' => 'fresh',
            'stale_reason' => null,
            'date_bucket' => $dateBucket,
            'provider_public_id' => (string)($provider['public_id'] ?? ''),
            'provider_code' => (string)($provider['provider_code'] ?? ''),
            'model' => $resolvedModel,
            'last_used_at' => $now,
            'usage_count' => 1,
            'result_meta_json' => json_encode([
                'cache' => [
                    'dependency_fingerprint' => $dependencyFingerprint,
                    'date_bucket' => $dateBucket,
                ],
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'created_by_user_id' => (int)($actor['id'] ?? 0) ?: null,
            'confirmed_by_user_id' => null,
            'created_at' => $now,
            'updated_at' => $now,
            'expires_at' => null,
        ]);

        $jobPayload = [
            'action_type' => $intentCode,
            'intent_code' => $intentCode,
            'provider_public_id' => (string)($provider['public_id'] ?? ''),
            'model' => $resolvedModel,
            'task_public_id' => $taskPublicId,
            'suggestion_public_id' => $suggestionPublicId,
            'intent_setting_public_id' => (string)($intent['public_id'] ?? ''),
            'prompt_public_id' => (string)($prompt['public_id'] ?? ''),
            'prompt_version' => (int)($prompt['version'] ?? 0),
            'prompt_runtime' => $this->sanitizePromptRuntimeForStorage($promptEnvelope),
            'input' => $this->sanitizeInput($input),
        ];
        $resultPayload = [
            'mode' => $llmMode,
            'suggestion_public_id' => $suggestionPublicId,
        ];

        $jobPublicId = $this->runtime->createJob([
            'job_type' => 'interactive',
            'action_type' => $intentCode,
            'intent_code' => $intentCode,
            'status' => 'completed',
            'requested_by_user_id' => (int)($actor['id'] ?? 0) ?: null,
            'scope_type' => 'task',
            'scope_public_id' => $taskPublicId,
            'idempotency_key_hash' => null,
            'payload_json' => json_encode($jobPayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'result_json' => json_encode($resultPayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'error_code' => $llmOk ? null : (string)($llm['code'] ?? 'AI_PROVIDER_UNAVAILABLE'),
            'error_message' => null,
            'created_at' => $now,
            'started_at' => $now,
            'finished_at' => $now,
            'updated_at' => $now,
        ]);

        $this->writeUsageLog([
            'user_id' => (int)($actor['id'] ?? 0) ?: null,
            'provider_public_id' => (string)($provider['public_id'] ?? ''),
            'action_type' => $intentCode,
            'intent_code' => $intentCode,
            'status' => 'completed',
            'error_code' => $llmOk ? null : (string)($llm['code'] ?? 'AI_PROVIDER_UNAVAILABLE'),
            'request_tokens' => (int)($llm['request_tokens'] ?? 0),
            'response_tokens' => (int)($llm['response_tokens'] ?? 0),
            'total_tokens' => (int)($llm['total_tokens'] ?? 0),
            'latency_ms' => (int)($llm['latency_ms'] ?? 0),
            'is_sensitive_context' => 0,
            'request_meta' => json_encode([
                'mode' => $llmMode,
                'scope_type' => 'task',
                'scope_public_id' => $taskPublicId,
                'suggestion_public_id' => $suggestionPublicId,
                'intent_setting_public_id' => (string)($intent['public_id'] ?? ''),
                'prompt_public_id' => (string)($prompt['public_id'] ?? ''),
                'prompt_runtime' => [
                    'context_budget_tokens' => (int)($promptEnvelope['meta']['context_budget_tokens'] ?? 0),
                    'context_estimated_tokens' => (int)($promptEnvelope['meta']['context_estimated_tokens'] ?? 0),
                    'context_truncated' => (bool)($promptEnvelope['meta']['context_truncated'] ?? false),
                ],
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'created_at' => $now,
        ]);

        $this->logger->audit([
            'action' => 'ai_suggestion_created',
            'actor_public_id' => $actor['public_id'] ?? null,
            'entity_type' => 'ai_suggestion',
            'entity_public_id' => $suggestionPublicId,
            'intent_code' => $intentCode,
            'scope_type' => 'task',
            'scope_public_id' => $taskPublicId,
            'job_public_id' => $jobPublicId,
            'provider_public_id' => (string)($provider['public_id'] ?? ''),
            'provider_code' => (string)($provider['provider_code'] ?? ''),
        ]);

        return [
            'ok' => true,
            'suggestion' => $this->normalizeSuggestion(
                (array)($this->runtime->findSuggestionByPublicId($suggestionPublicId) ?? []),
                true
            ),
            'job_public_id' => $jobPublicId,
        ];
    }

    /**
     * @param array<string,mixed> $context
     * @param callable(array<string,mixed>):array<string,mixed> $payloadBuilder
     */
    private function createContextIntentSuggestion(
        string $intentCode,
        string $entityType,
        string $entityPublicId,
        array $context,
        array $input,
        array $actor,
        callable $payloadBuilder
    ): array {
        if (!$this->isFeatureEnabledForActor('ai.enabled', $actor, false)) {
            return ['ok' => false, 'code' => 'AI_DISABLED'];
        }
        if (!in_array($intentCode, $this->actionAllowlist(), true)) {
            return ['ok' => false, 'code' => 'AI_ACTION_TYPE_NOT_ALLOWED'];
        }
        if (!$this->isValidPublicId($entityPublicId)) {
            return ['ok' => false, 'code' => 'AI_SCOPE_PUBLIC_ID_INVALID'];
        }
        $rate = $this->rateLimit->assertWithinLimits($intentCode, $actor);
        if (!(bool)($rate['ok'] ?? false)) {
            return $this->limitFailure($rate, 'AI_RATE_LIMITED');
        }
        $cost = $this->costLimit->assertWithinLimits($intentCode, $actor);
        if (!(bool)($cost['ok'] ?? false)) {
            return $this->limitFailure($cost, 'AI_COST_LIMIT_EXCEEDED');
        }

        $intent = $this->intentSettings->findByIntentCode($intentCode);
        if ($intent && !(bool)($intent['is_enabled'] ?? true)) {
            return ['ok' => false, 'code' => 'AI_INTENT_DISABLED'];
        }
        if ($intent && trim((string)($intent['feature_flag'] ?? '')) !== '') {
            if (!$this->isFeatureEnabledForActor(trim((string)$intent['feature_flag']), $actor, false)) {
                return ['ok' => false, 'code' => 'AI_FEATURE_DISABLED'];
            }
        }
        if ($intent && trim((string)($intent['required_permission'] ?? '')) !== '') {
            if (!$this->hasActorPermission($actor, trim((string)$intent['required_permission']))) {
                return ['ok' => false, 'code' => 'FORBIDDEN'];
            }
        }

        $provider = null;
        $intentProviderId = (int)($intent['provider_id'] ?? 0);
        if ($intentProviderId > 0) {
            $provider = $this->providers->findById($intentProviderId);
            if ($provider && !(bool)($provider['is_active'] ?? false)) {
                $provider = null;
            }
        }
        if (!$provider) {
            $provider = $this->providers->findDefaultActive() ?? $this->providers->findAnyActive();
        }
        $provider = $this->resolveUsableProvider($provider);
        if (!$provider) {
            return ['ok' => false, 'code' => 'AI_PROVIDER_NOT_CONFIGURED'];
        }

        $minimalContext = $this->minimizeContextForIntent($intentCode, $context);
        $payload = $payloadBuilder($minimalContext);
        $schemaValidation = $this->promptSchemas->validatePayloadBySchema($intentCode, $payload);
        if (!(bool)($schemaValidation['ok'] ?? false)) {
            return ['ok' => false, 'code' => (string)($schemaValidation['code'] ?? 'AI_SCHEMA_VALIDATION_FAILED')];
        }
        $payload = $this->sanitizePayloadByIntentSchema($intentCode, $payload);

        $now = gmdate('Y-m-d H:i:s');
        $locale = trim((string)($actor['locale'] ?? '')) !== '' ? trim((string)$actor['locale']) : 'ru-ru';
        $prompt = $this->promptSchemas->resolveActivePrompt($intentCode, $locale);
        $strictPromptMasking = $this->useStrictPromptMaskingForProvider($provider);
        $promptEnvelope = $this->promptBuilder->buildPromptEnvelope($intentCode, $prompt, $minimalContext, $input, 0, $strictPromptMasking);
        $resolvedModel = trim((string)($intent['model'] ?? '')) !== '' ? trim((string)$intent['model']) : (string)($provider['default_model'] ?? '');
        $forceRefresh = $this->isForceRefreshRequested($input);
        $dateBucket = $this->resolveCacheDateBucket($intentCode);
        $cacheKey = $this->buildCacheKey(
            (int)($actor['id'] ?? 0),
            $actor,
            $intentCode,
            $entityType,
            $entityPublicId,
            (string)($provider['public_id'] ?? ''),
            $resolvedModel,
            $dateBucket,
            $input,
            (int)($prompt['version'] ?? 0)
        );
        $dependencyFingerprint = $this->buildDependencyFingerprint($intentCode, $minimalContext, $actor, $dateBucket, (int)($prompt['version'] ?? 0));
        if (!$forceRefresh) {
            $cached = $this->runtime->findLatestSuggestionByCacheKey(
                $intentCode,
                $entityType,
                $entityPublicId,
                (int)($actor['id'] ?? 0),
                $cacheKey
            );
            $cachedResponse = $this->resolveCachedSuggestionResponse(
                $cached,
                $dependencyFingerprint,
                $dateBucket,
                (string)($provider['public_id'] ?? ''),
                $resolvedModel
            );
            if ($cachedResponse !== null) {
                $this->runtime->markSuggestionUsed((string)($cached['public_id'] ?? ''), gmdate('Y-m-d H:i:s'));
                return [
                    'ok' => true,
                    'suggestion' => $cachedResponse,
                    'job_public_id' => '',
                ];
            }
        }
        $structuredIntent = $this->isStructuredIntent($intentCode);
        $llmPayload = [
            'intent_code' => $intentCode,
            'system_prompt' => (string)($promptEnvelope['system_prompt'] ?? '') . ($structuredIntent ? "\n\n" . $this->structuredResponseInstruction($intentCode) : ''),
            'user_prompt' => (string)($promptEnvelope['user_prompt'] ?? ''),
            'context' => (array)($promptEnvelope['context'] ?? []),
            'model' => $resolvedModel,
        ];
        if ($structuredIntent) {
            $llmPayload['response_format'] = ['type' => 'json_object'];
        }
        $llm = $this->aiProviderService->completeText((string)($provider['public_id'] ?? ''), $llmPayload);
        $llmOk = (bool)($llm['ok'] ?? false) && trim((string)($llm['text'] ?? '')) !== '';
        $llmResolution = $this->resolveLlmExecution($provider, $llm);
        if (!(bool)($llmResolution['ok'] ?? false)) {
            return ['ok' => false, 'code' => (string)($llmResolution['code'] ?? 'AI_PROVIDER_UNAVAILABLE')];
        }
        $llmMode = $llmOk ? 'llm' : 'safe_mock';
        if ($structuredIntent) {
            if (!$llmOk) {
                return ['ok' => false, 'code' => 'AI_STRUCTURED_RESPONSE_INVALID'];
            }
            $structured = $this->parseStructuredIntentWithRepair(
                $intentCode,
                (string)($provider['public_id'] ?? ''),
                (string)$resolvedModel,
                (string)($promptEnvelope['system_prompt'] ?? ''),
                (string)($llm['text'] ?? '')
            );
            if (!(bool)($structured['ok'] ?? false)) {
                $this->logStructuredIntentInvalid($intentCode, $provider, $resolvedModel, $structured);
                return ['ok' => false, 'code' => 'AI_STRUCTURED_RESPONSE_INVALID'];
            }
            $payload = (array)($structured['payload'] ?? $payload);
            $meta = is_array($payload['meta'] ?? null) ? (array)$payload['meta'] : [];
            $meta['mode'] = 'llm';
            $meta['parse_ok'] = true;
            $meta['validation_ok'] = true;
            $meta['repair_attempted'] = (bool)($structured['repair_attempted'] ?? false);
            $meta['fallback_used'] = false;
            $meta['raw_text_used'] = false;
            $payload['meta'] = $meta;
            $llmMode = 'llm';
            $this->logger->audit([
                'action' => 'ai_structured_intent_result',
                'intent_code' => $intentCode,
                'provider_code' => (string)($provider['provider_code'] ?? ''),
                'model' => $resolvedModel,
                'expected_schema' => $intentCode,
                'parse_ok' => true,
                'validation_ok' => true,
                'repair_attempted' => (bool)($structured['repair_attempted'] ?? false),
                'fallback_used' => false,
                'raw_text_used' => false,
                'checklist_count' => $intentCode === 'task_checklist' ? count((array)($payload['checklist'] ?? [])) : 0,
                'section_count' => $intentCode === 'dashboard_daily_digest' ? count((array)($payload['sections'] ?? [])) : 0,
                'insight_count' => $intentCode === 'dashboard_daily_digest' ? count((array)($payload['insights'] ?? [])) : 0,
                'action_count' => count((array)($payload['suggested_actions'] ?? [])),
                'risk_count' => count((array)($payload['risks'] ?? [])),
                'question_count' => count((array)($payload['questions'] ?? [])),
                'suggested_task_count' => count((array)($payload['suggested_tasks'] ?? [])),
                'error_code' => null,
            ]);
        } elseif ($llmOk) {
            $payload = $this->mergeLlmTextIntoPayload($intentCode, $payload, (string)$llm['text']);
        }

        $suggestionPublicId = $this->runtime->createSuggestion([
            'intent_code' => $intentCode,
            'entity_type' => $entityType,
            'entity_public_id' => $entityPublicId,
            'summary' => (string)($payload['summary'] ?? ''),
            'suggestion_json' => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'status' => 'draft',
            'cache_key' => $cacheKey,
            'dependency_fingerprint' => $dependencyFingerprint,
            'cache_status' => 'fresh',
            'stale_reason' => null,
            'date_bucket' => $dateBucket,
            'provider_public_id' => (string)($provider['public_id'] ?? ''),
            'provider_code' => (string)($provider['provider_code'] ?? ''),
            'model' => $resolvedModel,
            'last_used_at' => $now,
            'usage_count' => 1,
            'result_meta_json' => json_encode([
                'cache' => [
                    'dependency_fingerprint' => $dependencyFingerprint,
                    'date_bucket' => $dateBucket,
                ],
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'created_by_user_id' => (int)($actor['id'] ?? 0) ?: null,
            'confirmed_by_user_id' => null,
            'created_at' => $now,
            'updated_at' => $now,
            'expires_at' => null,
        ]);

        $jobPayload = [
            'action_type' => $intentCode,
            'intent_code' => $intentCode,
            'provider_public_id' => (string)($provider['public_id'] ?? ''),
            'model' => $resolvedModel,
            'scope_type' => $entityType,
            'scope_public_id' => $entityPublicId,
            'suggestion_public_id' => $suggestionPublicId,
            'intent_setting_public_id' => (string)($intent['public_id'] ?? ''),
            'prompt_public_id' => (string)($prompt['public_id'] ?? ''),
            'prompt_version' => (int)($prompt['version'] ?? 0),
            'prompt_runtime' => $this->sanitizePromptRuntimeForStorage($promptEnvelope),
            'input' => $this->sanitizeInput($input),
        ];

        $jobPublicId = $this->runtime->createJob([
            'job_type' => 'interactive',
            'action_type' => $intentCode,
            'intent_code' => $intentCode,
            'status' => 'completed',
            'requested_by_user_id' => (int)($actor['id'] ?? 0) ?: null,
            'scope_type' => $entityType,
            'scope_public_id' => $entityPublicId,
            'idempotency_key_hash' => null,
            'payload_json' => json_encode($jobPayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'result_json' => json_encode([
                'mode' => $llmMode,
                'suggestion_public_id' => $suggestionPublicId,
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'error_code' => $llmOk ? null : (string)($llm['code'] ?? 'AI_PROVIDER_UNAVAILABLE'),
            'error_message' => null,
            'created_at' => $now,
            'started_at' => $now,
            'finished_at' => $now,
            'updated_at' => $now,
        ]);

        $this->writeUsageLog([
            'user_id' => (int)($actor['id'] ?? 0) ?: null,
            'provider_public_id' => (string)($provider['public_id'] ?? ''),
            'action_type' => $intentCode,
            'intent_code' => $intentCode,
            'status' => 'completed',
            'error_code' => $llmOk ? null : (string)($llm['code'] ?? 'AI_PROVIDER_UNAVAILABLE'),
            'request_tokens' => (int)($llm['request_tokens'] ?? 0),
            'response_tokens' => (int)($llm['response_tokens'] ?? 0),
            'total_tokens' => (int)($llm['total_tokens'] ?? 0),
            'latency_ms' => (int)($llm['latency_ms'] ?? 0),
            'is_sensitive_context' => 0,
            'request_meta' => json_encode([
                'mode' => $llmMode,
                'scope_type' => $entityType,
                'scope_public_id' => $entityPublicId,
                'suggestion_public_id' => $suggestionPublicId,
                'intent_setting_public_id' => (string)($intent['public_id'] ?? ''),
                'prompt_public_id' => (string)($prompt['public_id'] ?? ''),
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'created_at' => $now,
        ]);

        $this->logger->audit([
            'action' => 'ai_suggestion_created',
            'actor_public_id' => $actor['public_id'] ?? null,
            'entity_type' => 'ai_suggestion',
            'entity_public_id' => $suggestionPublicId,
            'intent_code' => $intentCode,
            'scope_type' => $entityType,
            'scope_public_id' => $entityPublicId,
            'job_public_id' => $jobPublicId,
            'provider_public_id' => (string)($provider['public_id'] ?? ''),
            'provider_code' => (string)($provider['provider_code'] ?? ''),
        ]);

        return [
            'ok' => true,
            'suggestion' => $this->normalizeSuggestion(
                (array)($this->runtime->findSuggestionByPublicId($suggestionPublicId) ?? []),
                true
            ),
            'job_public_id' => $jobPublicId,
        ];
    }

    /** @param array<string,mixed> $context */
    private function buildTaskDecompositionSuggestion(array $context): array
    {
        $title = trim((string)($context['title'] ?? $this->t('ai_suggestion/messages.default_task_title')));
        return [
            'summary' => sprintf($this->t('ai_suggestion/messages.decomposition_prepared'), $title),
            'risks' => [],
            'suggested_tasks' => [
                ['title' => $this->t('ai_suggestion/messages.clarify_readiness_criteria'), 'recommended_minutes' => 20],
                ['title' => $this->t('ai_suggestion/messages.split_implementation'), 'recommended_minutes' => 40],
                ['title' => $this->t('ai_suggestion/messages.check_result_and_notes'), 'recommended_minutes' => 20],
            ],
            'checklist_items' => [],
            'calendar_slots' => [],
            'questions' => [],
            'meta' => ['mode' => 'safe_mock', 'intent_code' => 'task_decomposition'],
        ];
    }

    /** @param array<string,mixed> $context */
    private function buildTaskChecklistSuggestion(array $context): array
    {
        $title = trim((string)($context['title'] ?? $this->t('ai_suggestion/messages.default_task_title')));
        return [
            'summary' => sprintf($this->t('ai_suggestion/messages.checklist_prepared'), $title),
            'risks' => [],
            'suggested_tasks' => [],
            'checklist_items' => [
                ['title' => $this->t('ai_suggestion/messages.check_input_data'), 'description' => '', 'priority' => 'medium', 'done' => false],
                ['title' => $this->t('ai_suggestion/messages.agree_expected_result'), 'description' => '', 'priority' => 'medium', 'done' => false],
                ['title' => $this->t('ai_suggestion/messages.implement_and_self_review'), 'description' => '', 'priority' => 'medium', 'done' => false],
                ['title' => $this->t('ai_suggestion/messages.prepare_brief_report'), 'description' => '', 'priority' => 'low', 'done' => false],
            ],
            'calendar_slots' => [],
            'questions' => [],
            'meta' => ['mode' => 'safe_mock', 'intent_code' => 'task_checklist'],
        ];
    }

    /** @param array<string,mixed> $context */
    private function buildTaskQualitySuggestion(array $context): array
    {
        $status = trim((string)($context['status'] ?? 'new'));
        $priority = trim((string)($context['priority'] ?? 'normal'));
        return [
            'summary' => $this->t('ai_suggestion/messages.quality_check_summary'),
            'risks' => [
                $status === 'blocked' ? $this->t('ai_suggestion/messages.blocked_status_warning') : $this->t('ai_suggestion/messages.status_criteria_needs_clarification'),
                $priority === 'high' ? $this->t('ai_suggestion/messages.high_priority_deadline_control') : $this->t('ai_suggestion/messages.add_measurable_readiness'),
            ],
            'suggested_tasks' => [],
            'checklist_items' => [
                $this->t('ai_suggestion/messages.add_acceptance_criteria'),
                $this->t('ai_suggestion/messages.fix_owner_and_deadline'),
            ],
            'calendar_slots' => [],
            'questions' => [$this->t('ai_suggestion/messages.what_is_successful_result')],
            'meta' => ['mode' => 'safe_mock', 'intent_code' => 'task_quality'],
        ];
    }

    /** @param array<string,mixed> $context */
    private function buildTaskNextActionSuggestion(array $context): array
    {
        $title = trim((string)($context['title'] ?? $this->t('ai_suggestion/messages.default_task_title')));
        return [
            'summary' => sprintf($this->t('ai_suggestion/messages.next_step_summary'), $title),
            'risks' => [],
            'suggested_tasks' => [
                ['title' => $this->t('ai_suggestion/messages.first_minimal_step'), 'recommended_minutes' => 25, 'recommended_order' => 1],
            ],
            'checklist_items' => [],
            'calendar_slots' => [],
            'questions' => [],
            'meta' => ['mode' => 'safe_mock', 'intent_code' => 'task_next_action'],
        ];
    }

    /** @param array<string,mixed> $context */
    private function buildTaskCommentDraftSuggestion(array $context): array
    {
        $title = trim((string)($context['title'] ?? $this->t('ai_suggestion/messages.default_task_title')));
        return [
            'summary' => sprintf($this->t('ai_suggestion/messages.comment_draft_prepared'), $title),
            'risks' => [],
            'suggested_tasks' => [],
            'checklist_items' => [],
            'calendar_slots' => [],
            'questions' => [],
            'comment_draft' => $this->t('ai_suggestion/messages.interim_update_draft'),
            'meta' => ['mode' => 'safe_mock', 'intent_code' => 'task_comment_draft'],
        ];
    }

    /** @param array<string,mixed> $context */
    private function buildProjectSummarySuggestion(array $context): array
    {
        $title = trim((string)($context['title'] ?? $this->t('ai_suggestion/messages.default_project_title')));
        $evidence = is_array($context['evidence'] ?? null) ? (array)$context['evidence'] : [];
        $overdueTasks = (int)($evidence['overdue_tasks'] ?? 0);
        $blockedTasks = (int)($evidence['blocked_tasks'] ?? 0);
        $upcomingMilestones = (int)($evidence['milestones_upcoming_7_days'] ?? 0);
        $suggestedTasks = [];
        if ($overdueTasks > 0) {
            $suggestedTasks[] = sprintf($this->t('ai_suggestion/messages.follow_up_overdue_tasks'), $overdueTasks);
        }
        if ($blockedTasks > 0) {
            $suggestedTasks[] = sprintf($this->t('ai_suggestion/messages.reminder_sync_blockers'), $blockedTasks);
        }
        if ($upcomingMilestones > 0) {
            $suggestedTasks[] = $this->t('ai_suggestion/messages.calendar_status_meeting');
        }
        if ($suggestedTasks === []) {
            $suggestedTasks[] = $this->t('ai_suggestion/messages.comment_draft_stable_status');
        }

        return [
            'summary' => sprintf($this->t('ai_suggestion/messages.project_summary_prepared'), $title),
            'risks' => [],
            'suggested_tasks' => $suggestedTasks,
            'checklist_items' => [],
            'calendar_slots' => [],
            'questions' => [],
            'meta' => ['mode' => 'safe_mock', 'intent_code' => 'project_summary'],
        ];
    }

    /** @param array<string,mixed> $context */
    private function buildProjectRiskSuggestion(array $context): array
    {
        $evidence = is_array($context['evidence'] ?? null) ? (array)$context['evidence'] : [];
        $overdueTasks = (int)($evidence['overdue_tasks'] ?? 0);
        $blockedTasks = (int)($evidence['blocked_tasks'] ?? 0);
        $milestonesOverdue = (int)($evidence['milestones_overdue'] ?? 0);
        $milestonesUpcoming = (int)($evidence['milestones_upcoming_7_days'] ?? 0);

        $risks = [];
        if ($overdueTasks > 0) {
            $risks[] = sprintf($this->t('ai_suggestion/messages.overdue_tasks_risk'), $overdueTasks);
        }
        if ($blockedTasks > 0) {
            $risks[] = sprintf($this->t('ai_suggestion/messages.blocked_tasks_risk'), $blockedTasks);
        }
        if ($milestonesOverdue > 0) {
            $risks[] = sprintf($this->t('ai_suggestion/messages.milestones_overdue_risk'), $milestonesOverdue);
        }
        if ($milestonesUpcoming > 0) {
            $risks[] = sprintf($this->t('ai_suggestion/messages.milestones_upcoming_risk'), $milestonesUpcoming);
        }
        if ($risks === []) {
            $risks[] = $this->t('ai_suggestion/messages.no_critical_risks_current');
        }

        return [
            'summary' => $this->t('ai_suggestion/messages.project_risks_analyzed'),
            'risks' => $risks,
            'suggested_tasks' => [
                $this->t('ai_suggestion/messages.follow_up_update_risk_register'),
                $this->t('ai_suggestion/messages.reminder_check_deadlines_blocked'),
            ],
            'checklist_items' => [$this->t('ai_suggestion/messages.update_risk_register')],
            'calendar_slots' => [],
            'questions' => [
                $this->t('ai_suggestion/messages.who_risk_owner'),
                $this->t('ai_suggestion/messages.external_escalation_blockers'),
            ],
            'meta' => ['mode' => 'safe_mock', 'intent_code' => 'project_risk_summary'],
        ];
    }

    /** @param array<string,mixed> $context */
    private function buildProjectClientReportSuggestion(array $context): array
    {
        $title = trim((string)($context['title'] ?? $this->t('ai_suggestion/messages.default_project_title')));
        return [
            'summary' => sprintf($this->t('ai_suggestion/messages.client_report_prepared'), $title),
            'risks' => [],
            'suggested_tasks' => [],
            'checklist_items' => [$this->t('ai_suggestion/messages.check_formulations_before_sending')],
            'calendar_slots' => [],
            'questions' => [],
            'report_draft' => $this->t('ai_suggestion/messages.project_status_draft'),
            'meta' => ['mode' => 'safe_mock', 'intent_code' => 'project_client_report'],
        ];
    }

    /** @param array<string,mixed> $context */
    private function buildClientSummarySuggestion(array $context): array
    {
        $title = trim((string)($context['title'] ?? $this->t('ai_suggestion/messages.default_client_title')));
        return [
            'summary' => sprintf($this->t('ai_suggestion/messages.client_summary_prepared'), $title),
            'risks' => [],
            'suggested_tasks' => [],
            'checklist_items' => [],
            'calendar_slots' => [],
            'questions' => [],
            'meta' => ['mode' => 'safe_mock', 'intent_code' => 'client_summary'],
        ];
    }

    /** @param array<string,mixed> $context */
    private function buildClientMeetingPrepSuggestion(array $context): array
    {
        $title = trim((string)($context['title'] ?? $this->t('ai_suggestion/messages.default_client_title')));
        $events = is_array($context['upcoming_events'] ?? null) ? (array)$context['upcoming_events'] : [];
        $openTasks = is_array($context['open_tasks'] ?? null) ? (array)$context['open_tasks'] : [];
        $projects = is_array($context['recent_projects'] ?? null) ? (array)$context['recent_projects'] : [];

        $facts = [
            $this->t('ai_suggestion/messages.client_label') . $title,
            $this->t('ai_suggestion/messages.upcoming_events_count') . (string)count($events),
            $this->t('ai_suggestion/messages.open_tasks_count') . (string)count($openTasks),
            $this->t('ai_suggestion/messages.active_recent_projects_count') . (string)count($projects),
        ];

        $checklist = [];
        if ($events !== []) {
            $firstEvent = (array)$events[0];
            $checklist[] = sprintf($this->t('ai_suggestion/messages.confirm_meeting_goal'), trim((string)($firstEvent['title'] ?? '')));
        } else {
            $checklist[] = $this->t('ai_suggestion/messages.agree_meeting_slot_with_client');
        }
        if ($openTasks !== []) {
            $firstTask = (array)$openTasks[0];
            $checklist[] = sprintf($this->t('ai_suggestion/messages.prepare_task_update_before_meeting'), trim((string)($firstTask['title'] ?? '')));
        }
        if ($projects !== []) {
            $firstProject = (array)$projects[0];
            $checklist[] = sprintf($this->t('ai_suggestion/messages.check_project_status_risks'), trim((string)($firstProject['title'] ?? '')));
        }

        return [
            'summary' => sprintf($this->t('ai_suggestion/messages.meeting_prep_prepared'), $title),
            'facts' => $facts,
            'risks' => [],
            'suggested_tasks' => [],
            'checklist_items' => $checklist,
            'calendar_slots' => [],
            'questions' => [
                $this->t('ai_suggestion/messages.decisions_from_client'),
                $this->t('ai_suggestion/messages.blockers_to_escalate'),
                $this->t('ai_suggestion/messages.next_steps_and_deadlines'),
            ],
            'upcoming_events' => array_slice($events, 0, 5),
            'open_tasks' => array_slice($openTasks, 0, 5),
            'recent_projects' => array_slice($projects, 0, 5),
            'meta' => ['mode' => 'safe_mock', 'intent_code' => 'client_meeting_prep'],
        ];
    }

    /** @param array<string,mixed> $context */
    private function buildClientDataQualitySuggestion(array $context): array
    {
        $title = trim((string)($context['title'] ?? $this->t('ai_suggestion/messages.default_client_title')));
        $profile = is_array($context['quality_profile'] ?? null) ? (array)$context['quality_profile'] : [];
        $issues = [];

        $emailPresent = (bool)($profile['email_present'] ?? false);
        $phonePresent = (bool)($profile['phone_present'] ?? false);
        $websitePresent = (bool)($profile['website_present'] ?? false);
        $innDigits = (int)($profile['tax_inn_digits'] ?? 0);
        $kppDigits = (int)($profile['tax_kpp_digits'] ?? 0);
        $ogrnDigits = (int)($profile['tax_ogrn_digits'] ?? 0);
        $ogrnipDigits = (int)($profile['tax_ogrnip_digits'] ?? 0);
        $accountDigits = (int)($profile['bank_account_digits'] ?? 0);
        $bikDigits = (int)($profile['bank_bik_digits'] ?? 0);
        $corrDigits = (int)($profile['bank_corr_account_digits'] ?? 0);

        if (!$emailPresent && !$phonePresent) {
            $issues[] = $this->t('ai_suggestion/messages.contact_channels_empty');
        }
        if ($innDigits > 0 && $innDigits !== 10 && $innDigits !== 12) {
            $issues[] = $this->t('ai_suggestion/messages.inn_incomplete');
        }
        if ($kppDigits > 0 && $kppDigits !== 9) {
            $issues[] = $this->t('ai_suggestion/messages.kpp_must_be_9');
        }
        if ($ogrnDigits > 0 && $ogrnDigits !== 13) {
            $issues[] = $this->t('ai_suggestion/messages.ogrn_must_be_13');
        }
        if ($ogrnipDigits > 0 && $ogrnipDigits !== 15) {
            $issues[] = $this->t('ai_suggestion/messages.ogrnip_must_be_15');
        }
        if ($accountDigits > 0 && $accountDigits !== 20) {
            $issues[] = $this->t('ai_suggestion/messages.account_must_be_20');
        }
        if ($bikDigits > 0 && $bikDigits !== 9) {
            $issues[] = $this->t('ai_suggestion/messages.bik_must_be_9');
        }
        if ($corrDigits > 0 && $corrDigits !== 20) {
            $issues[] = $this->t('ai_suggestion/messages.corr_account_must_be_20');
        }
        if (!$websitePresent) {
            $issues[] = $this->t('ai_suggestion/messages.no_website');
        }

        $summary = $issues === []
            ? sprintf($this->t('ai_suggestion/messages.no_critical_quality_issues'), $title)
            : sprintf($this->t('ai_suggestion/messages.quality_issues_found'), $title);

        $checklist = $issues === []
            ? [$this->t('ai_suggestion/messages.periodically_update_contacts')]
            : array_map(fn(string $item): string => $this->t('ai_suggestion/messages.check_and_fix') . $item, array_slice($issues, 0, 6));

        return [
            'summary' => $summary,
            'facts' => [
                $this->t('ai_suggestion/messages.email_filled') . ($emailPresent ? $this->t('ai_suggestion/messages.yes') : $this->t('ai_suggestion/messages.no')),
                $this->t('ai_suggestion/messages.phone_filled') . ($phonePresent ? $this->t('ai_suggestion/messages.yes') : $this->t('ai_suggestion/messages.no')),
                $this->t('ai_suggestion/messages.website_filled') . ($websitePresent ? $this->t('ai_suggestion/messages.yes') : $this->t('ai_suggestion/messages.no')),
            ],
            'problems' => $issues,
            'risks' => [],
            'suggested_tasks' => [],
            'checklist_items' => $checklist,
            'calendar_slots' => [],
            'questions' => $issues === []
                ? [$this->t('ai_suggestion/messages.update_client_card_question')]
                : [$this->t('ai_suggestion/messages.confirm_fields_question')],
            'meta' => ['mode' => 'safe_mock', 'intent_code' => 'client_data_quality'],
        ];
    }

    /** @param array<string,mixed> $context */
    private function buildClientSafeReportSuggestion(array $context): array
    {
        $title = trim((string)($context['title'] ?? $this->t('ai_suggestion/messages.default_client_title')));
        $events = is_array($context['upcoming_events'] ?? null) ? (array)$context['upcoming_events'] : [];
        $openTasks = is_array($context['open_tasks'] ?? null) ? (array)$context['open_tasks'] : [];
        $projects = is_array($context['recent_projects'] ?? null) ? (array)$context['recent_projects'] : [];

        $firstEvent = $events !== [] ? trim((string)((array)$events[0])['title'] ?? '') : '';
        $firstTask = $openTasks !== [] ? trim((string)((array)$openTasks[0])['title'] ?? '') : '';
        $firstProject = $projects !== [] ? trim((string)((array)$projects[0])['title'] ?? '') : '';

        $reportDraft = sprintf($this->t('ai_suggestion/messages.client_report_header'), $title) . "\n"
            . $this->t('ai_suggestion/messages.current_status_default') . "\n"
            . $this->t('ai_suggestion/messages.nearest_activities_label') . ($firstEvent !== '' ? $firstEvent : $this->t('ai_suggestion/messages.meeting_slot_pending')) . '.' . "\n"
            . $this->t('ai_suggestion/messages.task_focus_label') . ($firstTask !== '' ? $firstTask : $this->t('ai_suggestion/messages.active_tasks_in_progress')) . '.' . "\n"
            . $this->t('ai_suggestion/messages.projects_context_label') . ($firstProject !== '' ? $firstProject : $this->t('ai_suggestion/messages.projects_up_to_date')) . '.' . "\n"
            . $this->t('ai_suggestion/messages.next_step_agree_deadlines');

        return [
            'summary' => sprintf($this->t('ai_suggestion/messages.client_safe_report_prepared'), $title),
            'facts' => [
                $this->t('ai_suggestion/messages.nearest_events_count') . (string)count($events),
                $this->t('ai_suggestion/messages.open_tasks_count2') . (string)count($openTasks),
                $this->t('ai_suggestion/messages.recent_projects_count') . (string)count($projects),
            ],
            'risks' => [],
            'suggested_tasks' => [],
            'checklist_items' => [$this->t('ai_suggestion/messages.check_formulations_before_sending2')],
            'calendar_slots' => [],
            'questions' => [$this->t('ai_suggestion/messages.clarify_next_meeting_goals')],
            'report_draft' => $reportDraft,
            'meta' => ['mode' => 'safe_mock', 'intent_code' => 'client_safe_report', 'client_safe' => true],
        ];
    }

    /** @param array<string,mixed> $context */
    private function buildCalendarAgendaSuggestion(array $context): array
    {
        return [
            'summary' => $this->t('ai_suggestion/messages.event_agenda_prepared'),
            'risks' => [],
            'suggested_tasks' => [],
            'checklist_items' => [
                $this->t('ai_suggestion/messages.agree_meeting_goal'),
                $this->t('ai_suggestion/messages.prepare_key_questions'),
                $this->t('ai_suggestion/messages.fix_follow_up_actions'),
            ],
            'calendar_slots' => [],
            'questions' => [],
            'meta' => ['mode' => 'safe_mock', 'intent_code' => 'calendar_event_agenda'],
        ];
    }

    /** @param array<string,mixed> $context */
    private function buildDashboardDigestSuggestion(array $context): array
    {
        return [
            'summary' => $this->t('ai_suggestion/messages.dashboard_digest_prepared'),
            'risks' => [],
            'suggested_tasks' => [],
            'checklist_items' => [],
            'calendar_slots' => [],
            'questions' => [],
            'meta' => ['mode' => 'safe_mock', 'intent_code' => 'dashboard_daily_digest'],
        ];
    }

    /** @param array<string,mixed> $context */
    private function buildAnalyticsKpiExplanationSuggestion(array $context): array
    {
        $analytics = is_array($context['analytics'] ?? null) ? (array)$context['analytics'] : [];
        $period = trim((string)($context['period'] ?? 'week'));
        if ($period === '') {
            $period = 'week';
        }
        $completion = (float)($analytics['completion_rate_percent'] ?? 0);
        $overdue = (int)($analytics['overdue_tasks'] ?? 0);
        $total = (int)($analytics['total_tasks'] ?? 0);
        $worklog = (int)($analytics['worklog_minutes_week'] ?? 0);

        $facts = [
            $this->t('ai_suggestion/messages.analysis_period') . $period,
            $this->t('ai_suggestion/messages.total_tasks') . $total,
            $this->t('ai_suggestion/messages.completion_rate') . $completion . '%',
            $this->t('ai_suggestion/messages.overdue_tasks_count') . $overdue,
            $this->t('ai_suggestion/messages.weekly_worklog') . $worklog . $this->t('ai_suggestion/messages.minutes_short'),
        ];
        $questions = [
            $this->t('ai_suggestion/messages.blocked_tasks_overdue_share'),
            $this->t('ai_suggestion/messages.team_completion_rate_impact'),
            $this->t('ai_suggestion/messages.wip_limits_adjustment'),
        ];

        return [
            'summary' => sprintf($this->t('ai_suggestion/messages.kpi_explanation_prepared'), $period),
            'facts' => $facts,
            'risks' => $overdue > 0 ? [$this->t('ai_suggestion/messages.overdue_risk_delivery')] : [],
            'suggested_tasks' => [],
            'checklist_items' => [
                $this->t('ai_suggestion/messages.check_overdue_assign_owners'),
                $this->t('ai_suggestion/messages.check_teams_low_completion'),
            ],
            'calendar_slots' => [],
            'questions' => $questions,
            'meta' => ['mode' => 'safe_mock', 'intent_code' => 'analytics_kpi_explanation', 'period' => $period],
        ];
    }

    /** @param array<string,mixed> $context */
    private function buildAnalyticsRisksExplanationSuggestion(array $context): array
    {
        $projects = is_array($context['projects'] ?? null) ? (array)$context['projects'] : [];
        $topRisky = [];
        foreach (array_slice($projects, 0, 5) as $item) {
            if (!is_array($item)) {
                continue;
            }
            $overdue = (int)($item['overdue_tasks'] ?? 0);
            $blocked = (int)($item['blocked_tasks'] ?? 0);
            if ($overdue <= 0 && $blocked <= 0) {
                continue;
            }
            $title = trim((string)($item['project_title'] ?? $item['title'] ?? $this->t('ai_suggestion/messages.default_project_title')));
            $topRisky[] = $title . ': overdue=' . $overdue . ', blocked=' . $blocked;
        }
        if ($topRisky === []) {
            $topRisky[] = $this->t('ai_suggestion/messages.no_critical_project_risks');
        }

        return [
            'summary' => $this->t('ai_suggestion/messages.risks_explanation_prepared'),
            'facts' => $topRisky,
            'risks' => $topRisky,
            'suggested_tasks' => [],
            'checklist_items' => [
                $this->t('ai_suggestion/messages.update_project_risk_register'),
                $this->t('ai_suggestion/messages.assign_risk_owners'),
            ],
            'calendar_slots' => [],
            'questions' => [
                $this->t('ai_suggestion/messages.external_escalation_48h'),
                $this->t('ai_suggestion/messages.client_deadline_risks'),
            ],
            'meta' => ['mode' => 'safe_mock', 'intent_code' => 'analytics_risks_explanation'],
        ];
    }

    /** @param array<string,mixed> $context */
    private function buildAnalyticsTeamWorkloadSuggestion(array $context): array
    {
        $users = is_array($context['users'] ?? null) ? (array)$context['users'] : [];
        $items = [];
        $warnings = [];
        foreach (array_slice($users, 0, 8) as $row) {
            if (!is_array($row)) {
                continue;
            }
            $name = trim((string)($row['user_full_name'] ?? $row['user_login'] ?? $this->t('ai_suggestion/messages.default_user_name')));
            $active = (int)($row['assigned_active_tasks'] ?? 0);
            $overdue = (int)($row['assigned_overdue_tasks'] ?? 0);
            $worklog = (int)($row['worklog_minutes_week'] ?? 0);
            $items[] = $name . ': active=' . $active . ', overdue=' . $overdue . ', worklog=' . $worklog . $this->t('ai_suggestion/messages.minutes_unit');
            if ($active >= 12 || $overdue >= 4 || $worklog >= 2400) {
                $warnings[] = sprintf($this->t('ai_suggestion/messages.workload_overload_warning'), $name, $active, $overdue, $worklog);
            }
        }
        if ($items === []) {
            $items[] = $this->t('ai_suggestion/messages.insufficient_workload_data');
        }

        return [
            'summary' => $this->t('ai_suggestion/messages.team_workload_summary_prepared'),
            'facts' => $items,
            'risks' => $warnings,
            'suggested_tasks' => [],
            'checklist_items' => [
                $this->t('ai_suggestion/messages.balance_tasks_between_workers'),
                $this->t('ai_suggestion/messages.check_priorities_overdue_teams'),
            ],
            'calendar_slots' => [],
            'questions' => [
                $this->t('ai_suggestion/messages.delegatable_tasks'),
                $this->t('ai_suggestion/messages.capacity_redistribution'),
            ],
            'meta' => ['mode' => 'safe_mock', 'intent_code' => 'analytics_team_workload_summary'],
        ];
    }

    /** @param array<string,mixed> $context */
    private function buildAdminLogReviewSuggestion(array $context): array
    {
        $securityLogs = is_array($context['security_logs'] ?? null) ? (array)$context['security_logs'] : [];
        $events = [];
        foreach (array_slice($securityLogs, 0, 8) as $item) {
            if (!is_array($item)) {
                continue;
            }
            $eventType = trim((string)($item['event_type'] ?? 'security_event'));
            $createdAt = trim((string)($item['created_at'] ?? ''));
            $details = trim((string)($item['details'] ?? ''));
            $events[] = ($createdAt !== '' ? ($createdAt . ' • ') : '') . $eventType . ($details !== '' ? (' • ' . $details) : '');
        }
        if ($events === []) {
            $events[] = $this->t('ai_suggestion/messages.no_security_events');
        }

        return [
            'summary' => $this->t('ai_suggestion/messages.security_logs_prepared'),
            'facts' => $events,
            'risks' => array_values(array_filter($events, static fn(string $row): bool => (bool)preg_match('/forbidden|error|denied|failed|timeout/i', $row))),
            'suggested_tasks' => [],
            'checklist_items' => [
                $this->t('ai_suggestion/messages.check_repeated_denied_events'),
                $this->t('ai_suggestion/messages.confirm_sensitive_fields_masked'),
            ],
            'calendar_slots' => [],
            'questions' => [
                $this->t('ai_suggestion/messages.events_needing_investigation'),
                $this->t('ai_suggestion/messages.anomalous_error_spikes'),
            ],
            'meta' => ['mode' => 'safe_mock', 'intent_code' => 'admin_log_review', 'sanitized_only' => true],
        ];
    }

    /** @param array<string,mixed> $context */
    private function buildWebhookHealthReviewSuggestion(array $context): array
    {
        $summary = is_array($context['webhook_summary'] ?? null) ? (array)$context['webhook_summary'] : [];
        $subscriptions = is_array($context['webhook_subscriptions'] ?? null) ? (array)$context['webhook_subscriptions'] : [];
        $deliveries = is_array($context['webhook_deliveries'] ?? null) ? (array)$context['webhook_deliveries'] : [];

        $facts = [
            'Subscriptions total: ' . (string)((int)($summary['subscriptions_total'] ?? count($subscriptions))),
            'Subscriptions active: ' . (string)((int)($summary['subscriptions_active'] ?? 0)),
            'Deliveries total: ' . (string)((int)($summary['deliveries_total'] ?? count($deliveries))),
            'Deliveries failed: ' . (string)((int)($summary['deliveries_failed'] ?? 0)),
        ];

        $risks = [];
        foreach (array_slice($deliveries, 0, 10) as $item) {
            if (!is_array($item)) {
                continue;
            }
            $status = strtolower(trim((string)($item['status'] ?? '')));
            if ($status !== 'failed' && $status !== 'error') {
                continue;
            }
            $webhookId = trim((string)($item['webhook_public_id'] ?? 'unknown'));
            $eventCode = trim((string)($item['event_code'] ?? 'event'));
            $responseCode = (int)($item['response_code'] ?? 0);
            $risks[] = 'Delivery failed: ' . $webhookId . ' • ' . $eventCode . ' • HTTP ' . $responseCode;
        }
        if ($risks === []) {
            $risks[] = $this->t('ai_suggestion/messages.no_critical_webhook_issues');
        }

        return [
            'summary' => $this->t('ai_suggestion/messages.webhook_health_prepared'),
            'facts' => $facts,
            'risks' => $risks,
            'suggested_tasks' => [],
            'checklist_items' => [
                $this->t('ai_suggestion/messages.check_endpoint_retry_policy'),
                $this->t('ai_suggestion/messages.check_critical_webhook_subscriptions'),
            ],
            'calendar_slots' => [],
            'questions' => [
                $this->t('ai_suggestion/messages.retry_backoff_adjustment'),
                $this->t('ai_suggestion/messages.degraded_mode_webhooks'),
            ],
            'meta' => ['mode' => 'safe_mock', 'intent_code' => 'webhook_health_review'],
        ];
    }

    /** @param array<string,mixed> $context */
    private function buildWorkflowRuleAuditSuggestion(array $context): array
    {
        $rules = is_array($context['workflow_rules'] ?? null) ? (array)$context['workflow_rules'] : [];
        $runs = is_array($context['workflow_runs'] ?? null) ? (array)$context['workflow_runs'] : [];

        $facts = [
            'Workflow rules in scope: ' . (string)count($rules),
            'Recent workflow runs: ' . (string)count($runs),
        ];

        $risks = [];
        foreach (array_slice($runs, 0, 12) as $run) {
            if (!is_array($run)) {
                continue;
            }
            $status = strtolower(trim((string)($run['status'] ?? '')));
            if ($status !== 'failed') {
                continue;
            }
            $ruleTitle = trim((string)($run['rule_title'] ?? $run['rule_public_id'] ?? 'workflow_rule'));
            $errorMasked = trim((string)($run['error_masked'] ?? ''));
            $risks[] = 'Failed run: ' . $ruleTitle . ($errorMasked !== '' ? (' • ' . $errorMasked) : '');
        }
        if ($risks === []) {
            $risks[] = $this->t('ai_suggestion/messages.no_critical_workflow_failures');
        }

        return [
            'summary' => $this->t('ai_suggestion/messages.workflow_audit_prepared'),
            'facts' => $facts,
            'risks' => $risks,
            'suggested_tasks' => [],
            'checklist_items' => [
                $this->t('ai_suggestion/messages.check_disabled_rules'),
                $this->t('ai_suggestion.messages.check_failed_runs_payload'),
            ],
            'calendar_slots' => [],
            'questions' => [
                $this->t('ai_suggestion/messages.rules_needing_trigger_update'),
                $this->t('ai_suggestion.messages.repetitive_workflow_errors'),
            ],
            'meta' => ['mode' => 'safe_mock', 'intent_code' => 'workflow_rule_audit'],
        ];
    }

    /** @param array<int,array<string,mixed>> $events @param array<int,array<string,mixed>> $suggestedTasks */
    private function buildMyDayCalendarSlots(array $events, array $suggestedTasks, string $dayDate): array
    {
        $busy = [];
        $day = new \DateTimeImmutable($dayDate . ' 00:00:00');
        $workStart = $day->setTime(9, 0, 0);
        $workEnd = $day->setTime(18, 0, 0);

        foreach ($events as $event) {
            $startRaw = trim((string)($event['starts_at'] ?? ''));
            $endRaw = trim((string)($event['ends_at'] ?? ''));
            if ($startRaw === '' || $endRaw === '') {
                continue;
            }
            $startTs = strtotime($startRaw);
            $endTs = strtotime($endRaw);
            if (!is_int($startTs) || !is_int($endTs) || $endTs <= $startTs) {
                continue;
            }
            $busy[] = [
                'start' => (new \DateTimeImmutable('@' . $startTs))->setTimezone(new \DateTimeZone(date_default_timezone_get())),
                'end' => (new \DateTimeImmutable('@' . $endTs))->setTimezone(new \DateTimeZone(date_default_timezone_get())),
            ];
        }

        usort($busy, static function (array $a, array $b): int {
            return $a['start'] <=> $b['start'];
        });

        $slots = [];
        $cursor = $workStart;
        foreach ($suggestedTasks as $task) {
            $minutes = max(20, (int)($task['recommended_minutes'] ?? 45));
            $slotStart = $cursor;
            foreach ($busy as $interval) {
                if ($slotStart >= $interval['end']) {
                    continue;
                }
                $slotEndCandidate = $slotStart->modify('+' . $minutes . ' minutes');
                if ($slotEndCandidate <= $interval['start']) {
                    break;
                }
                if ($slotStart < $interval['end']) {
                    $slotStart = $interval['end']->modify('+10 minutes');
                }
            }
            $slotEnd = $slotStart->modify('+' . $minutes . ' minutes');
            if ($slotEnd > $workEnd) {
                break;
            }
            $slots[] = [
                'task_public_id' => (string)($task['task_public_id'] ?? ''),
                'title' => (string)($task['title'] ?? ''),
                'start' => $slotStart->format('Y-m-d H:i:s'),
                'end' => $slotEnd->format('Y-m-d H:i:s'),
                'start_at' => $slotStart->format('Y-m-d H:i:s'),
                'end_at' => $slotEnd->format('Y-m-d H:i:s'),
                'minutes' => $minutes,
                'kind' => 'focus',
                'reason' => (string)($task['reason'] ?? ''),
            ];
            $cursor = $slotEnd->modify('+10 minutes');
        }

        return $slots;
    }

    /** @param array<string,mixed> $agenda */
    private function resolveAgendaDayDate(array $agenda): string
    {
        $range = is_array($agenda['range'] ?? null) ? (array)$agenda['range'] : [];
        $from = trim((string)($range['from'] ?? ''));
        if ($from !== '' && preg_match('/^\d{4}-\d{2}-\d{2}/', $from) === 1) {
            return substr($from, 0, 10);
        }

        return date('Y-m-d');
    }

    /**
     * @param array<int,array<string,mixed>> $tasksDue
     * @param array<int,array<string,mixed>> $candidateTasks
     * @return array<int,array<string,mixed>>
     */
    private function mergeMyDayTaskCandidates(array $tasksDue, array $candidateTasks): array
    {
        $merged = [];
        $seen = [];
        foreach (array_merge($tasksDue, $candidateTasks) as $task) {
            if (!is_array($task)) {
                continue;
            }
            $publicId = trim((string)($task['public_id'] ?? ''));
            $fallbackKey = trim((string)($task['title'] ?? '')) . '|' . trim((string)($task['due_at'] ?? ''));
            $key = $publicId !== '' ? $publicId : $fallbackKey;
            if ($key === '' || isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $merged[] = $task;
        }

        return $merged;
    }

    private function recommendedMyDayBufferMinutes(int $availableMinutes): int
    {
        if ($availableMinutes <= 0) {
            return 0;
        }

        $baseBuffer = (int)round($availableMinutes * 0.15);
        return max(30, min(120, $baseBuffer));
    }

    /** @param array<int,array<string,mixed>> $events */
    private function estimateMyDayAvailableMinutes(array $events, string $dayDate): int
    {
        $workStartTs = strtotime($dayDate . ' 09:00:00');
        $workEndTs = strtotime($dayDate . ' 18:00:00');
        if (!is_int($workStartTs) || !is_int($workEndTs) || $workEndTs <= $workStartTs) {
            return 0;
        }

        $intervals = [];
        foreach ($events as $event) {
            if (!is_array($event)) {
                continue;
            }
            $startRaw = trim((string)($event['starts_at'] ?? ''));
            $endRaw = trim((string)($event['ends_at'] ?? ''));
            if ($startRaw === '' || $endRaw === '') {
                continue;
            }
            $startTs = strtotime($startRaw);
            $endTs = strtotime($endRaw);
            if (!is_int($startTs) || !is_int($endTs) || $endTs <= $startTs) {
                continue;
            }
            $startTs = max($startTs, $workStartTs);
            $endTs = min($endTs, $workEndTs);
            if ($endTs <= $startTs) {
                continue;
            }
            $intervals[] = [$startTs, $endTs];
        }

        if ($intervals === []) {
            return (int)(($workEndTs - $workStartTs) / 60);
        }

        usort($intervals, static function (array $left, array $right): int {
            return $left[0] <=> $right[0];
        });

        $busySeconds = 0;
        $mergedStart = $intervals[0][0];
        $mergedEnd = $intervals[0][1];
        for ($i = 1, $count = count($intervals); $i < $count; $i += 1) {
            $start = (int)$intervals[$i][0];
            $end = (int)$intervals[$i][1];
            if ($start > $mergedEnd) {
                $busySeconds += max(0, $mergedEnd - $mergedStart);
                $mergedStart = $start;
                $mergedEnd = $end;
                continue;
            }
            if ($end > $mergedEnd) {
                $mergedEnd = $end;
            }
        }
        $busySeconds += max(0, $mergedEnd - $mergedStart);

        $totalWorkSeconds = $workEndTs - $workStartTs;
        $availableSeconds = max(0, $totalWorkSeconds - $busySeconds);
        return (int)round($availableSeconds / 60);
    }

    /**
     * @param array<int,array<string,mixed>> $suggestedTasks
     * @return array{0:array<int,array<string,mixed>>,1:array<int,array<string,mixed>>}
     */
    private function fitMyDayTasksIntoAvailableMinutes(array $suggestedTasks, int $availableMinutes): array
    {
        $remaining = max(0, $availableMinutes);
        $planned = [];
        $deferrals = [];

        foreach ($suggestedTasks as $task) {
            if (!is_array($task)) {
                continue;
            }
            $taskPublicId = trim((string)($task['task_public_id'] ?? ''));
            $title = trim((string)($task['title'] ?? $this->t('ai_suggestion/messages.default_task_title')));
            $minutes = max(20, (int)($task['recommended_minutes'] ?? 0));

            if ($remaining < 20) {
                $deferrals[] = [
                    'task_public_id' => $taskPublicId,
                    'title' => $title,
                    'reason' => $this->t('ai_suggestion/messages.does_not_fit_available_minutes'),
                ];
                continue;
            }

            $assignedMinutes = min($minutes, $remaining);
            if ($assignedMinutes < 20) {
                $deferrals[] = [
                    'task_public_id' => $taskPublicId,
                    'title' => $title,
                    'reason' => $this->t('ai_suggestion/messages.does_not_fit_available_minutes'),
                ];
                continue;
            }

            $task['recommended_minutes'] = $assignedMinutes;
            $planned[] = $task;
            $remaining -= $assignedMinutes;

            if ($assignedMinutes < $minutes) {
                $deferrals[] = [
                    'task_public_id' => $taskPublicId,
                    'title' => $title,
                    'reason' => $this->t('ai_suggestion/messages.full_task_volume_too_large'),
                ];
            }
        }

        return [$planned, $deferrals];
    }

    private function myDayBusinessReason(array $task): string
    {
        $priority = strtolower(trim((string)($task['priority_code'] ?? 'normal')));
        $dueAt = trim((string)($task['due_at'] ?? ''));
        if (in_array($priority, ['urgent', 'critical'], true)) {
            return $this->t('ai_suggestion/messages.highest_risk_of_day');
        }
        if ($priority === 'high') {
            return $this->t('ai_suggestion/messages.supports_key_project_result');
        }
        if ($dueAt !== '') {
            return $this->t('ai_suggestion/messages.helps_meet_deadline');
        }

        return $this->t('ai_suggestion/messages.clear_progress_no_distraction');
    }

    private function myDayCareNote(int $minutes, string $status): string
    {
        if ($status === 'blocked') {
            return $this->t('ai_suggestion/messages.start_with_short_unblock');
        }
        if ($minutes >= 80) {
            return $this->t('ai_suggestion/messages.focus_block_and_pause');
        }

        return $this->t('ai_suggestion/messages.complete_step_then_switch');
    }

    /**
     * @param array<string,mixed> $payload
     * @param array<string,mixed> $fallback
     * @return array<string,mixed>
     */
    private function mergeMyDayPlanWithFallback(array $payload, array $fallback): array
    {
        $workItems = is_array($payload['work_items'] ?? null) ? (array)$payload['work_items'] : [];
        $calendarSlots = is_array($payload['calendar_slots'] ?? null) ? (array)$payload['calendar_slots'] : [];
        $hasConcreteItems = false;
        foreach ($workItems as $item) {
            if (!is_array($item)) {
                continue;
            }
            if (trim((string)($item['task_public_id'] ?? '')) !== '' || trim((string)($item['title'] ?? '')) !== '') {
                $hasConcreteItems = true;
                break;
            }
        }
        $hasUsableSlots = false;
        foreach ($calendarSlots as $slot) {
            if (!is_array($slot)) {
                continue;
            }
            $start = trim((string)($slot['start_at'] ?? $slot['start'] ?? ''));
            $end = trim((string)($slot['end_at'] ?? $slot['end'] ?? ''));
            if ($start !== '' && $end !== '') {
                $hasUsableSlots = true;
                break;
            }
        }
        $tooManyItemsForOneDay = count($workItems) > 7;
        $summary = trim((string)($payload['summary'] ?? ''));
        $summaryLooksForeign = $summary !== ''
            && preg_match('/[а-яё]/iu', $summary) !== 1
            && preg_match('/\b(today|task|tasks|scheduled|candidate|project|priority|blocked|reminder)\b/i', $summary) === 1;
        $useFallbackPlan = !$hasConcreteItems || !$hasUsableSlots || $tooManyItemsForOneDay || $summaryLooksForeign;

        if ($useFallbackPlan) {
            $payload['work_items'] = is_array($fallback['work_items'] ?? null) ? (array)$fallback['work_items'] : [];
            $payload['suggested_tasks'] = is_array($fallback['suggested_tasks'] ?? null) ? (array)$fallback['suggested_tasks'] : (array)$payload['work_items'];
            $payload['calendar_slots'] = is_array($fallback['calendar_slots'] ?? null) ? (array)$fallback['calendar_slots'] : [];
            $payload['summary'] = (string)($fallback['summary'] ?? $summary);
        } elseif ($calendarSlots === []) {
            $payload['calendar_slots'] = is_array($fallback['calendar_slots'] ?? null) ? (array)$fallback['calendar_slots'] : [];
        }
        if (trim((string)($payload['summary'] ?? '')) === '') {
            $payload['summary'] = (string)($fallback['summary'] ?? '');
        }
        if (!is_array($payload['warnings'] ?? null)) {
            $payload['warnings'] = is_array($fallback['warnings'] ?? null) ? (array)$fallback['warnings'] : [];
        }
        if (!is_array($payload['questions'] ?? null)) {
            $payload['questions'] = [];
        }
        $meta = is_array($payload['meta'] ?? null) ? (array)$payload['meta'] : [];
        $meta['intent_code'] = 'my_day_plan';
        if ($useFallbackPlan) {
            $meta['fallback_work_items_used'] = true;
            $meta['fallback_reason'] = !$hasConcreteItems ? 'empty_work_items' : (!$hasUsableSlots ? 'missing_slots' : ($tooManyItemsForOneDay ? 'too_many_items' : 'foreign_summary'));
        }
        $payload['meta'] = $meta;

        return $payload;
    }

    /**
     * @param array<string,mixed> $payload
     * @param array<string,mixed> $fallback
     * @return array<string,mixed>
     */
    private function mergeMyWeekPlanWithFallback(array $payload, array $fallback): array
    {
        $tasksByDay = is_array($payload['tasks_by_day'] ?? null) ? (array)$payload['tasks_by_day'] : [];
        $hasConcreteTasks = false;
        $totalTasks = 0;
        foreach ($tasksByDay as $day) {
            if (!is_array($day)) {
                continue;
            }
            $tasks = is_array($day['tasks'] ?? null) ? (array)$day['tasks'] : [];
            $totalTasks += count($tasks);
            foreach ($tasks as $task) {
                if (!is_array($task)) {
                    continue;
                }
                if (trim((string)($task['task_public_id'] ?? '')) !== '' || trim((string)($task['title'] ?? '')) !== '') {
                    $hasConcreteTasks = true;
                    break 2;
                }
            }
        }

        $summary = trim((string)($payload['summary'] ?? ''));
        $summaryLooksForeign = $summary !== ''
            && preg_match('/[а-яё]/iu', $summary) !== 1
            && preg_match('/\b(week|weekly|task|tasks|scheduled|candidate|project|priority|blocked|focus)\b/i', $summary) === 1;
        $tooManyTasks = $totalTasks > 30;
        $useFallbackPlan = !$hasConcreteTasks || $summaryLooksForeign || $tooManyTasks;

        if ($useFallbackPlan) {
            $payload['tasks_by_day'] = is_array($fallback['tasks_by_day'] ?? null) ? (array)$fallback['tasks_by_day'] : [];
            $payload['suggested_events'] = is_array($fallback['suggested_events'] ?? null) ? (array)$fallback['suggested_events'] : [];
            $payload['summary'] = (string)($fallback['summary'] ?? $summary);
            $payload['overload_warnings'] = is_array($fallback['overload_warnings'] ?? null) ? (array)$fallback['overload_warnings'] : [];
        }
        if (!is_array($payload['risks'] ?? null)) {
            $payload['risks'] = is_array($fallback['risks'] ?? null) ? (array)$fallback['risks'] : [];
        }
        if (!is_array($payload['questions'] ?? null)) {
            $payload['questions'] = [];
        }
        $meta = is_array($payload['meta'] ?? null) ? (array)$payload['meta'] : [];
        $meta['intent_code'] = 'my_week_plan';
        if ($useFallbackPlan) {
            $meta['fallback_tasks_by_day_used'] = true;
            $meta['fallback_reason'] = !$hasConcreteTasks ? 'empty_tasks_by_day' : ($tooManyTasks ? 'too_many_tasks' : 'foreign_summary');
        }
        $payload['meta'] = $meta;

        return $payload;
    }

    /** @param array<int,array<string,mixed>> $events @param array<int,array<string,mixed>> $suggestedTasks */
    private function myDayRisks(array $events, array $suggestedTasks): array
    {
        $risks = [];
        if (count($events) >= 5) {
            $risks[] = $this->t('ai_suggestion/messages.day_overloaded_meetings');
        }
        if (count($suggestedTasks) > 4) {
            $risks[] = $this->t('ai_suggestion/messages.large_task_volume');
        }
        if ($risks === []) {
            $risks[] = $this->t('ai_suggestion/messages.no_critical_day_risks');
        }

        return $risks;
    }

    private function canReadSuggestion(array $item, array $actor): bool
    {
        if ((bool)($actor['is_root'] ?? false)) {
            return true;
        }

        if (!$this->canAccessSuggestionSourceEntity($item, $actor)) {
            return false;
        }

        if ($this->canViewAllSuggestions($actor)) {
            return true;
        }

        return (int)($item['created_by_user_id'] ?? 0) > 0
            && (int)($item['created_by_user_id'] ?? 0) === (int)($actor['id'] ?? 0);
    }

    private function canAccessSuggestionSourceEntity(array $item, array $actor): bool
    {
        $entityType = trim((string)($item['entity_type'] ?? ''));
        $entityPublicId = trim((string)($item['entity_public_id'] ?? ''));
        if ($entityType === '' || $entityPublicId === '') {
            return false;
        }

        return match ($entityType) {
            'task' => $this->tasks->get($entityPublicId, $actor) !== null,
            'project' => $this->projects->get($entityPublicId, $actor) !== null,
            'client' => $this->clients->get($entityPublicId, $actor) !== null,
            'calendar_event' => $this->calendar->getEvent($entityPublicId, $actor) !== null,
            'user', 'task_list', 'dashboard', 'analytics', 'admin' => $entityPublicId === (string)($actor['public_id'] ?? ''),
            default => false,
        };
    }

    private function canViewAllSuggestions(array $actor): bool
    {
        if ((bool)($actor['is_root'] ?? false)) {
            return true;
        }

        $codes = is_array($actor['permission_codes'] ?? null) ? (array)$actor['permission_codes'] : [];
        return in_array('ai.view_audit', $codes, true);
    }

    /** @param array<string,mixed> $row */
    private function normalizeSuggestion(array $row, bool $withPayload): array
    {
        $rawPayload = (string)($row['suggestion_json'] ?? '');
        $payload = json_decode($rawPayload, true);
        if (!is_array($payload)) {
            $payload = [];
        }

        $item = [
            'public_id' => (string)($row['public_id'] ?? ''),
            'intent_code' => (string)($row['intent_code'] ?? ''),
            'entity_type' => (string)($row['entity_type'] ?? ''),
            'entity_public_id' => (string)($row['entity_public_id'] ?? ''),
            'summary' => (string)($row['summary'] ?? ''),
            'status' => (string)($row['status'] ?? 'draft'),
            'created_at' => (string)($row['created_at'] ?? ''),
            'updated_at' => (string)($row['updated_at'] ?? ''),
            'expires_at' => (string)($row['expires_at'] ?? ''),
        ];

        if ($withPayload) {
            $normalizedPayload = $this->normalizeDateTimePayload($payload);
            if (is_array($normalizedPayload)) {
                $meta = is_array($normalizedPayload['meta'] ?? null) ? (array)$normalizedPayload['meta'] : [];
                if (!is_array($meta['cache'] ?? null)) {
                    $status = strtolower(trim((string)($row['status'] ?? 'draft')));
                    $isActionableStatus = in_array($status, ['draft', 'ready'], true);
                    $meta['cache'] = [
                        'hit' => false,
                        'status' => (string)($row['cache_status'] ?? 'miss'),
                        'cached_at' => (string)($row['created_at'] ?? ''),
                        'stale' => false,
                        'stale_reason' => (string)($row['stale_reason'] ?? ''),
                        'can_apply' => $isActionableStatus && $this->isPreviewApplicableIntent((string)($row['intent_code'] ?? '')),
                        'can_refresh' => true,
                        'ai_request_skipped' => false,
                        'date_bucket' => (string)($row['date_bucket'] ?? ''),
                    ];
                }
                $normalizedPayload['meta'] = $meta;
            }
            $item['payload'] = $normalizedPayload;
        }

        return $item;
    }

    private function isForceRefreshRequested(array $input): bool
    {
        $raw = $input['force_refresh'] ?? $input['force'] ?? null;
        if (is_bool($raw)) {
            return $raw;
        }
        $value = strtolower(trim((string)$raw));
        return in_array($value, ['1', 'true', 'yes', 'on'], true);
    }

    private function resolveCacheDateBucket(string $intentCode): string
    {
        $dailyIntents = [
            'dashboard_daily_digest',
            'my_day_plan',
            'task_list_priority',
            'analytics_kpi_explanation',
            'analytics_risks_explanation',
            'analytics_team_workload_summary',
        ];
        if (in_array($intentCode, $dailyIntents, true)) {
            return gmdate('Y-m-d');
        }

        if ($intentCode === 'my_week_plan') {
            return gmdate('o-\WW');
        }

        return 'static';
    }

    private function buildCacheKey(
        int $actorUserId,
        array $actor,
        string $intentCode,
        string $entityType,
        string $entityPublicId,
        string $providerPublicId,
        string $model,
        string $dateBucket,
        array $input,
        int $promptVersion
    ): string {
        $payload = [
            'u' => $actorUserId,
            'intent' => $intentCode,
            'scope' => $entityType,
            'scope_id' => $entityPublicId,
            'provider' => $providerPublicId,
            'model' => $model,
            'date_bucket' => $dateBucket,
            'prompt_version' => $promptVersion,
            'filters' => $this->sanitizeInput($input),
            'permissions' => array_values(array_map('strval', (array)($actor['permission_codes'] ?? []))),
        ];

        return hash('sha256', json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '');
    }

    private function buildDependencyFingerprint(string $intentCode, array $minimalContext, array $actor, string $dateBucket, int $promptVersion): string
    {
        $fingerprint = [
            'intent' => $intentCode,
            'date_bucket' => $dateBucket,
            'prompt_version' => $promptVersion,
            'context' => $minimalContext,
            'permission_codes' => array_values(array_map('strval', (array)($actor['permission_codes'] ?? []))),
            'roles' => array_values(array_map('strval', (array)($actor['roles'] ?? []))),
        ];
        return hash('sha256', json_encode($fingerprint, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '');
    }

    private function resolveCachedSuggestionResponse(?array $cachedRow, string $currentDependencyFingerprint, string $currentDateBucket, string $providerPublicId, string $model): ?array
    {
        if (!is_array($cachedRow) || $cachedRow === []) {
            return null;
        }

        $normalized = $this->normalizeSuggestion($cachedRow, true);
        $payload = is_array($normalized['payload'] ?? null) ? (array)$normalized['payload'] : [];
        if ($payload === []) {
            return null;
        }

        $storedFingerprint = trim((string)($cachedRow['dependency_fingerprint'] ?? ''));
        $storedDateBucket = trim((string)($cachedRow['date_bucket'] ?? ''));
        $storedProviderPublicId = trim((string)($cachedRow['provider_public_id'] ?? ''));
        $storedModel = trim((string)($cachedRow['model'] ?? ''));

        $staleReason = null;
        if ($storedFingerprint !== '' && $storedFingerprint !== $currentDependencyFingerprint) {
            $staleReason = 'dependencies_changed';
        } elseif ($storedDateBucket !== '' && $storedDateBucket !== $currentDateBucket) {
            $staleReason = 'date_changed';
        } elseif ($storedProviderPublicId !== '' && $storedProviderPublicId !== $providerPublicId) {
            $staleReason = 'provider_changed';
        } elseif ($storedModel !== '' && $storedModel !== $model) {
            $staleReason = 'model_changed';
        }

        $cacheMeta = [
            'hit' => true,
            'status' => $staleReason === null ? 'fresh' : 'stale',
            'cached_at' => (string)($cachedRow['created_at'] ?? ''),
            'stale' => $staleReason !== null,
            'stale_reason' => $staleReason,
            'can_apply' => $this->canApplyCachedSuggestion($cachedRow, $staleReason),
            'can_refresh' => true,
            'ai_request_skipped' => true,
            'date_bucket' => $storedDateBucket !== '' ? $storedDateBucket : $currentDateBucket,
        ];
        $meta = is_array($payload['meta'] ?? null) ? (array)$payload['meta'] : [];
        $meta['cache'] = $cacheMeta;
        if ($staleReason !== null) {
            $warnings = is_array($payload['warnings'] ?? null) ? (array)$payload['warnings'] : [];
            $warnings[] = sprintf($this->t('ai_suggestion/messages.stale_result_warning'), $staleReason);
            $payload['warnings'] = array_values(array_unique(array_map('strval', $warnings)));
        }
        $payload['meta'] = $meta;
        $normalized['payload'] = $payload;
        return $normalized;
    }

    private function canApplyCachedSuggestion(array $cachedRow, ?string $staleReason): bool
    {
        if ($staleReason !== null) {
            return false;
        }

        $status = strtolower(trim((string)($cachedRow['status'] ?? '')));
        if (!in_array($status, ['draft', 'ready'], true)) {
            return false;
        }

        return $this->isPreviewApplicableIntent((string)($cachedRow['intent_code'] ?? ''));
    }

    private function isPreviewApplicableIntent(string $intentCode): bool
    {
        return in_array(trim($intentCode), [
            'task_summary',
            'task_decomposition',
            'task_checklist',
            'task_next_action',
            'task_comment_draft',
        ], true);
    }

    private function resolveStaleCacheDueToAiError(
        ?array $cachedRow,
        string $currentDependencyFingerprint,
        string $currentDateBucket,
        string $providerPublicId,
        string $model,
        string $aiErrorCode
    ): ?array {
        $normalized = $this->resolveCachedSuggestionResponse(
            $cachedRow,
            $currentDependencyFingerprint,
            $currentDateBucket,
            $providerPublicId,
            $model
        );
        if ($normalized === null) {
            return null;
        }

        $payload = is_array($normalized['payload'] ?? null) ? (array)$normalized['payload'] : [];
        $meta = is_array($payload['meta'] ?? null) ? (array)$payload['meta'] : [];
        $cache = is_array($meta['cache'] ?? null) ? (array)$meta['cache'] : [];
        $cache['hit'] = true;
        $cache['status'] = 'stale_due_to_ai_error';
        $cache['stale'] = true;
        $cache['stale_reason'] = 'provider_error';
        $cache['ai_request_skipped'] = true;
        $cache['can_refresh'] = true;
        $meta['cache'] = $cache;
        $meta['ai_error_code'] = trim($aiErrorCode) !== '' ? $aiErrorCode : 'AI_PROVIDER_UNAVAILABLE';
        $payload['meta'] = $meta;

        $warnings = is_array($payload['warnings'] ?? null) ? (array)$payload['warnings'] : [];
        $warnings[] = $this->t('ai_suggestion/messages.ai_unavailable_cached');
        $payload['warnings'] = array_values(array_unique(array_map('strval', $warnings)));
        $normalized['payload'] = $payload;
        return $normalized;
    }

    /**
     * @param mixed $value
     * @return mixed
     */
    private function normalizeDateTimePayload(mixed $value, string $parentKey = ''): mixed
    {
        if (is_array($value)) {
            $normalized = [];
            foreach ($value as $key => $item) {
                $childKey = is_string($key) ? $key : $parentKey;
                $normalized[$key] = $this->normalizeDateTimePayload($item, $childKey);
            }
            return $normalized;
        }

        $key = strtolower(trim($parentKey));
        if ($key === 'estimated_minutes') {
            return $this->normalizeEstimatedMinutesValue($value);
        }

        if (!is_string($value)) {
            return $value;
        }

        if ($key === '') {
            return $value;
        }

        $isDateKey = in_array($key, [
            'date',
            'due_at',
            'starts_at',
            'ends_at',
            'start_at',
            'end_at',
            'created_at',
            'updated_at',
            'expires_at',
            'scheduled_for_utc',
        ], true) || str_ends_with($key, '_at');
        if (!$isDateKey) {
            return $value;
        }

        $trimmed = trim($value);
        if ($trimmed === '') {
            return '';
        }

        if ((bool)preg_match('/^\d{4}-\d{2}-\d{2}$/', $trimmed)) {
            return $trimmed;
        }

        try {
            if ((bool)preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $trimmed)) {
                $dt = new \DateTimeImmutable($trimmed, new \DateTimeZone('UTC'));
                return $dt->format('c');
            }
            $dt = new \DateTimeImmutable($trimmed);
            return $dt->format('c');
        } catch (\Throwable) {
            return $value;
        }
    }

    private function normalizeEstimatedMinutesValue(mixed $value): int
    {
        $min = 5;
        $max = 480;
        $fallback = 30;

        $minutes = $fallback;
        if (is_int($value) || is_float($value)) {
            $minutes = (int)round((float)$value);
        } elseif (is_string($value)) {
            $trimmed = trim($value);
            if ($trimmed !== '' && is_numeric($trimmed)) {
                $minutes = (int)round((float)$trimmed);
            }
        }

        return max($min, min($max, $minutes));
    }

    /** @param array<string,mixed> $payload @return array<string,mixed> */
    private function mergeLlmTextIntoPayload(string $intentCode, array $payload, string $llmText): array
    {
        $text = trim($llmText);
        if ($text === '') {
            return $payload;
        }

        $jsonPayload = $this->extractStrictJsonObject($text);
        if (is_array($jsonPayload)) {
            $payload = $this->mergeByPayloadShape($payload, $jsonPayload);
            $payload = $this->sanitizePayloadByIntentSchema($intentCode, $payload);
            $meta = is_array($payload['meta'] ?? null) ? (array)$payload['meta'] : [];
            $meta['mode'] = 'llm';
            $meta['parse_ok'] = true;
            $meta['validation_ok'] = true;
            $meta['fallback_used'] = false;
            $meta['raw_text_used'] = false;
            $payload['meta'] = $meta;
            return $payload;
        }

        return $payload;
    }

    /** @return array{ok:bool,code?:string,payload?:array<string,mixed>} */
    private function parseStructuredIntentResponse(string $intentCode, string $llmText): array
    {
        $text = trim($llmText);
        if ($text === '') {
            return ['ok' => false, 'code' => 'AI_STRUCTURED_RESPONSE_INVALID'];
        }
        $jsonPayload = $this->extractJsonObjectFromText($text);
        if (!is_array($jsonPayload)) {
            return ['ok' => false, 'code' => 'AI_STRUCTURED_RESPONSE_INVALID'];
        }

        if ($intentCode === 'task_checklist') {
            $normalized = $this->normalizeTaskChecklistPayload($jsonPayload);
            if (!$normalized['ok']) {
                return ['ok' => false, 'code' => 'AI_STRUCTURED_RESPONSE_INVALID'];
            }
            return ['ok' => true, 'payload' => $normalized['payload']];
        }
        if ($intentCode === 'dashboard_daily_digest') {
            $normalized = $this->normalizeDashboardDigestPayload($jsonPayload);
            if (!$normalized['ok']) {
                return ['ok' => false, 'code' => 'AI_STRUCTURED_RESPONSE_INVALID'];
            }
            return ['ok' => true, 'payload' => $normalized['payload']];
        }
        if (in_array($intentCode, [
            'task_summary',
            'task_decomposition',
            'task_quality',
            'task_next_action',
            'task_comment_draft',
            'project_summary',
            'project_risk_summary',
            'project_client_report',
            'client_summary',
            'client_meeting_prep',
            'client_data_quality',
            'client_safe_report',
            'calendar_event_agenda',
            'analytics_kpi_explanation',
            'analytics_risks_explanation',
            'analytics_team_workload_summary',
            'admin_log_review',
            'webhook_health_review',
            'workflow_rule_audit',
            'my_day_plan',
            'my_week_plan',
            'task_list_priority',
        ], true)) {
            $sanitized = $this->sanitizePayloadByIntentSchema($intentCode, $jsonPayload);
            $schemaValidation = $this->promptSchemas->validatePayloadBySchema($intentCode, $sanitized);
            if (!(bool)($schemaValidation['ok'] ?? false)) {
                return ['ok' => false, 'code' => 'AI_STRUCTURED_RESPONSE_INVALID'];
            }
            $meta = is_array($sanitized['meta'] ?? null) ? (array)$sanitized['meta'] : [];
            $meta['mode'] = 'llm';
            $meta['parse_ok'] = true;
            $meta['validation_ok'] = true;
            $meta['fallback_used'] = false;
            $meta['raw_text_used'] = false;
            $sanitized['meta'] = $meta;
            return ['ok' => true, 'payload' => $sanitized];
        }

        return ['ok' => false, 'code' => 'AI_STRUCTURED_RESPONSE_INVALID'];
    }

    /** @return array{ok:bool,payload?:array<string,mixed>,validation_error_path?:string,validation_error_message?:string,missing_fields?:array<int,string>,invalid_fields?:array<int,string>,actual_top_level_keys?:array<int,string>} */
    private function normalizeTaskChecklistPayload(array $payload): array
    {
        $summary = trim((string)($payload['summary'] ?? ''));
        $items = is_array($payload['checklist'] ?? null) ? (array)$payload['checklist'] : [];
        if ($items === [] && is_array($payload['checklist_items'] ?? null)) {
            foreach ((array)$payload['checklist_items'] as $legacy) {
                if (is_string($legacy)) {
                    $items[] = ['title' => trim($legacy), 'description' => '', 'priority' => 'medium'];
                    continue;
                }
                if (is_array($legacy)) {
                    $items[] = $legacy;
                }
            }
        }
        if ($summary === '') {
            $summary = trim((string)($payload['title'] ?? $payload['overview'] ?? ''));
        }
        if ($summary === '' || $items === []) {
            return [
                'ok' => false,
                'validation_error_path' => $summary === '' ? '$.summary' : '$.checklist',
                'validation_error_message' => $summary === '' ? 'summary is required' : 'checklist is required',
                'missing_fields' => array_values(array_filter([
                    $summary === '' ? 'summary' : '',
                    $items === [] ? 'checklist' : '',
                ])),
                'actual_top_level_keys' => array_map('strval', array_keys($payload)),
            ];
        }
        $normalizedItems = [];
        foreach ($items as $item) {
            if (!is_array($item)) continue;
            $title = trim((string)($item['title'] ?? ''));
            if ($title === '') continue;
            $normalizedItems[] = [
                'title' => $title,
                'description' => trim((string)($item['description'] ?? '')),
                'priority' => in_array((string)($item['priority'] ?? 'medium'), ['high','medium','low'], true) ? (string)$item['priority'] : 'medium',
            ];
        }
        if ($normalizedItems === []) {
            return [
                'ok' => false,
                'validation_error_path' => '$.checklist[*].title',
                'validation_error_message' => 'at least one checklist item title is required',
                'invalid_fields' => ['checklist'],
                'actual_top_level_keys' => array_map('strval', array_keys($payload)),
            ];
        }
        $actions = [];
        $incomingActions = is_array($payload['suggested_actions'] ?? null) ? (array)$payload['suggested_actions'] : [];
        foreach ($incomingActions as $action) {
            if (!is_array($action)) {
                continue;
            }
            $type = trim((string)($action['type'] ?? ''));
            if (!in_array($type, ['create_checklist_item', 'create_checklist'], true)) {
                continue;
            }
            $actionTitle = trim((string)($action['title'] ?? ''));
            $actionPayload = is_array($action['payload'] ?? null) ? (array)$action['payload'] : [];
            if ($type === 'create_checklist_item') {
                $itemTitle = trim((string)($actionPayload['title'] ?? ''));
                if ($itemTitle === '') {
                    continue;
                }
            }
            $actions[] = [
                'type' => $type,
                'title' => $actionTitle !== '' ? $actionTitle : ($type === 'create_checklist' ? $this->t('ai_suggestion/messages.create_checklist_title') : $this->t('ai_suggestion/messages.add_checklist_item_title')),
                'payload' => $actionPayload,
            ];
        }
        if ($actions === []) {
            foreach ($normalizedItems as $item) {
                $actions[] = ['type' => 'create_checklist_item', 'title' => $this->t('ai_suggestion/messages.add_checklist_item_title'), 'payload' => $item];
            }
        }
        return ['ok' => true, 'payload' => ['summary' => $summary, 'checklist' => $normalizedItems, 'suggested_actions' => $actions, 'warnings' => is_array($payload['warnings'] ?? null) ? array_values($payload['warnings']) : [], 'confidence' => in_array((string)($payload['confidence'] ?? 'medium'), ['high','medium','low'], true) ? (string)$payload['confidence'] : 'medium', 'meta' => ['mode' => 'llm', 'parse_ok' => true, 'validation_ok' => true, 'fallback_used' => false, 'raw_text_used' => false]]];
    }

    /** @return array{ok:bool,payload?:array<string,mixed>,validation_error_path?:string,validation_error_message?:string,missing_fields?:array<int,string>,invalid_fields?:array<int,string>,actual_top_level_keys?:array<int,string>} */
    private function normalizeDashboardDigestPayload(array $payload): array
    {
        $summary = trim((string)($payload['summary'] ?? ''));
        $sections = is_array($payload['sections'] ?? null) ? (array)$payload['sections'] : [];
        $insights = is_array($payload['insights'] ?? null) ? array_values((array)$payload['insights']) : [];
        if ($sections === []) {
            $legacyRisks = is_array($payload['risks'] ?? null) ? array_values((array)$payload['risks']) : [];
            $legacyFacts = is_array($payload['facts'] ?? null) ? array_values((array)$payload['facts']) : [];
            $legacyChecklist = is_array($payload['checklist_items'] ?? null) ? array_values((array)$payload['checklist_items']) : [];
            $items = [];
            foreach (array_slice($legacyFacts, 0, 6) as $fact) {
                $label = trim((string)$fact);
                if ($label === '') continue;
                $items[] = ['label' => $label, 'value' => '—', 'severity' => 'normal'];
            }
            if ($items !== []) {
                $sections[] = ['title' => $this->t('ai_suggestion/messages.overview_section_title'), 'items' => $items];
            }
            foreach (array_slice($legacyRisks, 0, 6) as $risk) {
                $text = trim((string)$risk);
                if ($text === '') continue;
                $insights[] = ['title' => $this->t('ai_suggestion/messages.risk_insight_title'), 'text' => $text, 'severity' => 'warning'];
            }
            foreach (array_slice($legacyChecklist, 0, 6) as $todo) {
                $text = trim((string)$todo);
                if ($text === '') continue;
                $insights[] = ['title' => $this->t('ai_suggestion/messages.focus_insight_title'), 'text' => $text, 'severity' => 'normal'];
            }
        }
        if ($summary === '') {
            $summary = trim((string)($payload['overview'] ?? $payload['title'] ?? ''));
        }
        if ($summary === '' || $sections === []) {
            return [
                'ok' => false,
                'validation_error_path' => $summary === '' ? '$.summary' : '$.sections',
                'validation_error_message' => $summary === '' ? 'summary is required' : 'sections is required',
                'missing_fields' => array_values(array_filter([
                    $summary === '' ? 'summary' : '',
                    $sections === [] ? 'sections' : '',
                ])),
                'actual_top_level_keys' => array_map('strval', array_keys($payload)),
            ];
        }
        $normalizedSections = [];
        foreach ($sections as $section) {
            if (!is_array($section)) {
                continue;
            }
            $title = trim((string)($section['title'] ?? ''));
            $items = is_array($section['items'] ?? null) ? (array)$section['items'] : [];
            if ($title === '' || $items === []) {
                continue;
            }
            $normalizedItems = [];
            foreach ($items as $item) {
                if (!is_array($item)) {
                    continue;
                }
                $label = trim((string)($item['label'] ?? ''));
                if ($label === '') {
                    continue;
                }
                $normalizedItems[] = [
                    'label' => $label,
                    'value' => trim((string)($item['value'] ?? '—')),
                    'severity' => in_array((string)($item['severity'] ?? 'normal'), ['normal', 'warning', 'critical'], true) ? (string)$item['severity'] : 'normal',
                ];
            }
            if ($normalizedItems === []) {
                continue;
            }
            $normalizedSections[] = ['title' => $title, 'items' => $normalizedItems];
        }
        if ($normalizedSections === []) {
            return [
                'ok' => false,
                'validation_error_path' => '$.sections[*].items',
                'validation_error_message' => 'sections must contain items',
                'invalid_fields' => ['sections'],
                'actual_top_level_keys' => array_map('strval', array_keys($payload)),
            ];
        }
        $actions = is_array($payload['suggested_actions'] ?? null) ? array_values((array)$payload['suggested_actions']) : [];
        return ['ok' => true, 'payload' => ['summary' => $summary, 'sections' => $normalizedSections, 'insights' => $insights, 'suggested_actions' => $actions, 'warnings' => is_array($payload['warnings'] ?? null) ? array_values($payload['warnings']) : [], 'confidence' => in_array((string)($payload['confidence'] ?? 'medium'), ['high','medium','low'], true) ? (string)$payload['confidence'] : 'medium', 'meta' => ['mode' => 'llm', 'parse_ok' => true, 'validation_ok' => true, 'fallback_used' => false, 'raw_text_used' => false]]];
    }

    /** @return array<string,mixed>|null */
    private function extractJsonObjectFromText(string $text): ?array
    {
        $trimmed = trim($text);
        if ($trimmed === '') {
            return null;
        }

        // P0 structured intents must reject markdown-wrapped JSON.
        if ($trimmed[0] === '{') {
            $decoded = json_decode($trimmed, true);
            return is_array($decoded) ? $decoded : null;
        }

        return null;
    }

    /** @return array<string,mixed>|null */
    private function extractStrictJsonObject(string $text): ?array
    {
        $trimmed = trim($text);
        if ($trimmed === '' || $trimmed[0] !== '{') {
            return null;
        }
        $decoded = json_decode($trimmed, true);
        return is_array($decoded) ? $decoded : null;
    }

    /** @return array{ok:bool,payload?:array<string,mixed>,repair_attempted:bool,raw_response_kind?:string,content_length?:int,content_starts_with?:string,content_ends_with?:string,json_first_char?:string,json_last_char?:string,json_decode_error?:string,validation_error_path?:string,validation_error_message?:string,expected_schema?:string,missing_fields?:array<int,string>,invalid_fields?:array<int,string>,actual_top_level_keys?:array<int,string>,repair_result?:string} */
    private function parseStructuredIntentWithRepair(string $intentCode, string $providerPublicId, string $model, string $systemPrompt, string $rawText): array
    {
        $debug = $this->buildStructuredDebugInfo($intentCode, $rawText);
        $parsed = $this->parseStructuredIntentResponse($intentCode, $rawText);
        if ((bool)($parsed['ok'] ?? false)) {
            return ['ok' => true, 'payload' => (array)$parsed['payload'], 'repair_attempted' => false] + $debug;
        }

        $repairPrompt = sprintf($this->t('ai_suggestion/messages.repair_prompt'), $intentCode) . "\n\nInput:\n" . $rawText;
        $repair = $this->aiProviderService->completeText($providerPublicId, [
            'intent_code' => $intentCode,
            'system_prompt' => $systemPrompt . "\n\n" . $this->structuredResponseInstruction($intentCode),
            'user_prompt' => $repairPrompt,
            'context' => [],
            'model' => $model,
            'response_format' => ['type' => 'json_object'],
        ]);
        if (!(bool)($repair['ok'] ?? false)) {
            return ['ok' => false, 'repair_attempted' => true, 'repair_result' => 'provider_error'] + $debug;
        }
        $repairParsed = $this->parseStructuredIntentResponse($intentCode, (string)($repair['text'] ?? ''));
        if (!(bool)($repairParsed['ok'] ?? false)) {
            $repairDebug = $this->buildStructuredDebugInfo($intentCode, (string)($repair['text'] ?? ''));
            return ['ok' => false, 'repair_attempted' => true, 'repair_result' => 'invalid'] + $repairDebug;
        }
        return ['ok' => true, 'payload' => (array)$repairParsed['payload'], 'repair_attempted' => true, 'repair_result' => 'valid'] + $debug;
    }

    private function isStructuredIntent(string $intentCode): bool
    {
        return in_array($intentCode, [
            'task_checklist',
            'dashboard_daily_digest',
            'task_summary',
            'task_decomposition',
            'task_quality',
            'task_next_action',
            'task_comment_draft',
            'project_summary',
            'project_risk_summary',
            'project_client_report',
            'client_summary',
            'client_meeting_prep',
            'client_data_quality',
            'client_safe_report',
            'calendar_event_agenda',
            'analytics_kpi_explanation',
            'analytics_risks_explanation',
            'analytics_team_workload_summary',
            'admin_log_review',
            'webhook_health_review',
            'workflow_rule_audit',
            'my_day_plan',
            'my_week_plan',
            'task_list_priority',
        ], true);
    }

    /** @param array<string,mixed> $provider @param array<string,mixed> $structured */
    private function logStructuredIntentInvalid(string $intentCode, array $provider, string $model, array $structured): void
    {
        $audit = [
            'action' => 'ai_structured_intent_invalid',
            'intent_code' => $intentCode,
            'provider_code' => (string)($provider['provider_code'] ?? ''),
            'provider_public_id' => (string)($provider['public_id'] ?? ''),
            'model' => $model,
            'expected_schema' => (string)($structured['expected_schema'] ?? $intentCode),
            'parse_ok' => false,
            'validation_ok' => false,
            'repair_attempted' => (bool)($structured['repair_attempted'] ?? false),
            'fallback_used' => false,
            'raw_text_used' => false,
            'raw_response_kind' => (string)($structured['raw_response_kind'] ?? ''),
            'content_length' => (int)($structured['content_length'] ?? 0),
            'content_starts_with' => (string)($structured['content_starts_with'] ?? ''),
            'content_ends_with' => (string)($structured['content_ends_with'] ?? ''),
            'json_first_char' => (string)($structured['json_first_char'] ?? ''),
            'json_last_char' => (string)($structured['json_last_char'] ?? ''),
            'json_decode_error' => (string)($structured['json_decode_error'] ?? ''),
            'validation_error_path' => (string)($structured['validation_error_path'] ?? ''),
            'validation_error_message' => (string)($structured['validation_error_message'] ?? ''),
            'missing_fields' => is_array($structured['missing_fields'] ?? null) ? array_values((array)$structured['missing_fields']) : [],
            'invalid_fields' => is_array($structured['invalid_fields'] ?? null) ? array_values((array)$structured['invalid_fields']) : [],
            'actual_top_level_keys' => is_array($structured['actual_top_level_keys'] ?? null) ? array_values((array)$structured['actual_top_level_keys']) : [],
            'repair_result' => (string)($structured['repair_result'] ?? ''),
            'error_code' => 'AI_STRUCTURED_RESPONSE_INVALID',
        ];
        if ($this->isDevRuntime()) {
            $audit['content_preview'] = (string)($structured['content_preview'] ?? '');
        }
        $this->logger->audit($audit);
    }

    private function isDevRuntime(): bool
    {
        $env = strtolower(trim((string)$this->config->get('default.app.env', 'prod')));
        return in_array($env, ['dev', 'local', 'test'], true);
    }

    /** @return array<string,mixed> */
    private function buildStructuredDebugInfo(string $intentCode, string $text): array
    {
        $trimmed = trim($text);
        $len = strlen($trimmed);
        $start = $len > 0 ? substr($trimmed, 0, min(24, $len)) : '';
        $end = $len > 0 ? substr($trimmed, max(0, $len - 24)) : '';
        $first = $len > 0 ? $trimmed[0] : '';
        $last = $len > 0 ? substr($trimmed, -1) : '';
        $kind = 'plain_text';
        if ($first === '{') {
            $kind = 'json_object_candidate';
        } elseif ($first === '[') {
            $kind = 'json_array_candidate';
        } elseif (str_starts_with($trimmed, '```')) {
            $kind = 'markdown_fence';
        } elseif ($trimmed === '') {
            $kind = 'empty';
        }

        $decoded = json_decode($trimmed, true);
        $jsonError = json_last_error() === JSON_ERROR_NONE ? '' : json_last_error_msg();
        $keys = [];
        if (is_array($decoded) && !array_is_list($decoded)) {
            $keys = array_map('strval', array_keys($decoded));
        }

        $out = [
            'expected_schema' => $intentCode,
            'raw_response_kind' => $kind,
            'content_length' => $len,
            'content_starts_with' => $start,
            'content_ends_with' => $end,
            'json_first_char' => $first,
            'json_last_char' => $last,
            'json_decode_error' => $jsonError,
            'actual_top_level_keys' => $keys,
        ];
        if ($this->isDevRuntime()) {
            $out['content_preview'] = mb_substr($trimmed, 0, 300);
        }

        if ($intentCode === 'task_checklist' && is_array($decoded)) {
            $norm = $this->normalizeTaskChecklistPayload($decoded);
            if (!(bool)($norm['ok'] ?? false)) {
                $out['validation_error_path'] = (string)($norm['validation_error_path'] ?? '');
                $out['validation_error_message'] = (string)($norm['validation_error_message'] ?? '');
                $out['missing_fields'] = is_array($norm['missing_fields'] ?? null) ? array_values((array)$norm['missing_fields']) : [];
                $out['invalid_fields'] = is_array($norm['invalid_fields'] ?? null) ? array_values((array)$norm['invalid_fields']) : [];
            }
        }
        if ($intentCode === 'dashboard_daily_digest' && is_array($decoded)) {
            $norm = $this->normalizeDashboardDigestPayload($decoded);
            if (!(bool)($norm['ok'] ?? false)) {
                $out['validation_error_path'] = (string)($norm['validation_error_path'] ?? '');
                $out['validation_error_message'] = (string)($norm['validation_error_message'] ?? '');
                $out['missing_fields'] = is_array($norm['missing_fields'] ?? null) ? array_values((array)$norm['missing_fields']) : [];
                $out['invalid_fields'] = is_array($norm['invalid_fields'] ?? null) ? array_values((array)$norm['invalid_fields']) : [];
            }
        }

        return $out;
    }

    /** @param array<string,mixed> $base @param array<string,mixed> $incoming @return array<string,mixed> */
    private function mergeByPayloadShape(array $base, array $incoming): array
    {
        foreach ($base as $key => $value) {
            if (!is_string($key) || !array_key_exists($key, $incoming)) {
                continue;
            }
            $next = $incoming[$key];
            if (is_array($value) && is_array($next) && !array_is_list($value) && !array_is_list($next)) {
                $base[$key] = $this->mergeByPayloadShape($value, $next);
                continue;
            }
            $base[$key] = $next;
        }

        return $base;
    }

    /** @param array<string,mixed> $payload */
    private function sanitizePayloadByIntentSchema(string $intentCode, array $payload): array
    {
        $schema = $this->promptSchemas->resolveActiveSchema($intentCode);
        $schemaJson = is_array($schema['schema_json'] ?? null) ? (array)$schema['schema_json'] : [];
        if ($schemaJson === []) {
            return $payload;
        }

        $sanitized = $this->sanitizePayloadBySchemaDefinition($payload, $schemaJson);
        if (!is_array($sanitized)) {
            return $payload;
        }

        // Keep description-improvement payload for task_summary preview/apply flow.
        // Some active schemas can be stricter than runtime payload and drop this field.
        if ($intentCode === 'task_summary' && !array_key_exists('improved_description', $sanitized)) {
            $improvedDescription = $payload['improved_description'] ?? null;
            if (is_string($improvedDescription)) {
                $normalized = trim($improvedDescription);
                if ($normalized !== '') {
                    $sanitized['improved_description'] = $normalized;
                }
            }
        }

        return $sanitized;
    }

    /** @param array<string,mixed> $schemaDefinition */
    private function sanitizePayloadBySchemaDefinition(mixed $value, array $schemaDefinition): mixed
    {
        $type = strtolower(trim((string)($schemaDefinition['type'] ?? 'object')));

        if ($type === 'array') {
            if (!is_array($value) || !array_is_list($value)) {
                return $value;
            }
            $itemSchema = is_array($schemaDefinition['items'] ?? null) ? (array)$schemaDefinition['items'] : [];
            if ($itemSchema === []) {
                return $value;
            }

            $sanitizedList = [];
            foreach ($value as $item) {
                $sanitizedList[] = $this->sanitizePayloadBySchemaDefinition($item, $itemSchema);
            }
            return $sanitizedList;
        }

        if ($type !== 'object' || !is_array($value)) {
            return $value;
        }

        $properties = is_array($schemaDefinition['properties'] ?? null) ? (array)$schemaDefinition['properties'] : [];
        if ($properties === []) {
            return $value;
        }

        $sanitized = [];
        foreach ($properties as $name => $rule) {
            if (!is_string($name) || !array_key_exists($name, $value)) {
                continue;
            }
            $itemSchema = is_array($rule) ? (array)$rule : [];
            if ($itemSchema === []) {
                $sanitized[$name] = $value[$name];
                continue;
            }
            $sanitized[$name] = $this->sanitizePayloadBySchemaDefinition($value[$name], $itemSchema);
        }

        return $sanitized;
    }

    /** @return list<string> */
    private function actionAllowlist(): array
    {
        $setting = $this->settings->get('ai_actions', 'allowlist');
        $fromSettings = is_array($setting['value'] ?? null) ? (array)$setting['value'] : [];
        $fromConfig = (array)$this->config->get('ai.actions.allowlist', []);
        $list = array_merge($fromConfig, $fromSettings);

        $normalized = [];
        foreach ($list as $item) {
            $value = trim((string)$item);
            if ($value !== '') {
                $normalized[] = $value;
            }
        }

        return array_values(array_unique($normalized));
    }

    /** @return list<string> */
    private function getEnabledActionTypesForPreview(): array
    {
        $mapping = [
            'task_summary' => ['update_task_description'],
            'task_comment_draft' => ['create_comment_draft'],
            'task_decomposition' => ['create_subtask'],
            'task_checklist' => ['create_checklist', 'create_checklist_item'],
            'task_next_action' => ['create_comment_draft', 'create_subtask'],
        ];

        $enabled = [];
        foreach ($mapping as $intentCode => $actionTypes) {
            $intent = $this->intentSettings->findByIntentCode($intentCode);
            if (is_array($intent) && !(bool)($intent['is_enabled'] ?? true)) {
                continue;
            }
            foreach ($actionTypes as $actionType) {
                $enabled[$actionType] = true;
            }
        }

        return array_keys($enabled);
    }

    /** @param array<string,mixed> $payload */
    private function isActionPayloadValid(string $actionType, array $payload): bool
    {
        $normalized = trim($actionType);
        if ($normalized === '') {
            return false;
        }

        if ($normalized === 'update_task_description') {
            $description = $payload['description'] ?? null;
            if (!is_string($description)) {
                return false;
            }
            $description = trim($description);
            return $description !== '' && strlen($description) <= 20000;
        }

        if ($normalized === 'create_comment_draft') {
            $body = $payload['body'] ?? null;
            if (!is_string($body)) {
                return false;
            }
            $body = trim($body);
            return $body !== '' && strlen($body) <= 20000;
        }

        if ($normalized === 'create_subtask' || $normalized === 'create_follow_up_task') {
            $title = $payload['title'] ?? null;
            if (!is_string($title)) {
                return false;
            }
            $title = trim($title);
            if ($title === '' || strlen($title) > 255) {
                return false;
            }
            if (array_key_exists('description', $payload) && !is_string($payload['description'])) {
                return false;
            }
            return true;
        }

        if ($normalized === 'create_checklist') {
            $title = $payload['checklist_title'] ?? null;
            if (!is_string($title)) {
                return false;
            }
            $title = trim($title);
            return $title !== '' && strlen($title) <= 255;
        }

        if ($normalized === 'create_checklist_item') {
            $title = $payload['title'] ?? null;
            if (!is_string($title)) {
                return false;
            }
            $title = trim($title);
            if ($title === '' || strlen($title) > 255) {
                return false;
            }
            if (array_key_exists('checklist_title', $payload) && !is_string($payload['checklist_title'])) {
                return false;
            }
            if (array_key_exists('checklist_public_id', $payload) && !is_string($payload['checklist_public_id'])) {
                return false;
            }
            return true;
        }

        return true;
    }

    private function sanitizeInput(array $input): array
    {
        $blockedKeys = [
            'provider_public_id',
            'provider_id',
            'provider_code',
            'model',
            'default_model',
            'base_url',
            'api_path',
            'embeddings_endpoint',
            'feature_flag',
            'required_permission',
            'prompt_public_id',
            'schema_public_id',
            'intent_code',
        ];

        $safe = [];
        foreach ($input as $key => $value) {
            if (!is_string($key)) {
                continue;
            }
            $normalized = strtolower($key);
            if (in_array($normalized, $blockedKeys, true)) {
                continue;
            }
            if (
                str_contains($normalized, 'token')
                || str_contains($normalized, 'secret')
                || str_contains($normalized, 'password')
                || str_contains($normalized, 'authorization')
                || str_contains($normalized, 'cookie')
                || str_contains($normalized, 'backup_code')
                || str_contains($normalized, 'webhook')
            ) {
                continue;
            }
            if (is_string($value)) {
                $safe[$key] = $this->sanitizeInputStringValue($normalized, $value);
                continue;
            }
            $safe[$key] = is_scalar($value) || $value === null ? $value : '[complex]';
        }

        return $safe;
    }

    private function sanitizeInputStringValue(string $normalizedKey, string $value): string
    {
        $trimmed = trim($value);
        if ($trimmed === '') {
            return '';
        }

        // Never persist raw free-form prompt/instruction text in runtime payloads.
        foreach (['prompt', 'instruction', 'message', 'content', 'query', 'text', 'comment', 'notes'] as $sensitiveKeyPart) {
            if (str_contains($normalizedKey, $sensitiveKeyPart)) {
                return '[redacted]';
            }
        }

        $hasSecretLikePayload = (bool)preg_match('/(bearer\s+[A-Za-z0-9\.\-_~\+\/]+=*)|((?:api[_ -]?key|token|secret|password|password_hash|auth_token_hash|backup codes?|webhook secret)\s*[:=]\s*[^\s,;]+)/iu', $trimmed);
        $hasSensitiveHeaders = (bool)preg_match('/\b(?:authorization|cookie)\b\s*[:=]/iu', $trimmed);
        $hasBase64Blob = (bool)preg_match('/^[A-Za-z0-9+\/]{120,}={0,2}$/', $trimmed);
        if ($hasSecretLikePayload || $hasSensitiveHeaders || $hasBase64Blob) {
            return '[masked]';
        }

        return $trimmed;
    }

    /**
     * @param array<string,mixed> $promptRuntime
     * @return array<string,mixed>
     */
    private function structuredResponseInstruction(string $intentCode): string
    {
        if ($intentCode === 'task_checklist') {
            return 'Return ONLY one JSON object. No markdown, no prose, no code fences. Required keys: '
                . '{"summary":string,"checklist":[{"title":string,"description":string,"priority":"high|medium|low"}],'
                . '"suggested_actions":[{"type":"create_checklist_item","title":string,"payload":{"title":string,"description":string,"priority":"high|medium|low"}}],'
                . '"warnings":[string],"confidence":"high|medium|low"}.';
        }
        if ($intentCode === 'dashboard_daily_digest') {
            return 'Return ONLY one JSON object. No markdown, no prose, no code fences. Required keys: '
                . '{"summary":string,"sections":[{"title":string,"items":[{"label":string,"value":string,"severity":"normal|warning|critical"}]}],'
                . '"insights":[{"title":string,"text":string,"severity":"normal|warning|critical"}],'
                . '"suggested_actions":[{"type":string,"title":string,"payload":object}],"warnings":[string],"confidence":"high|medium|low"}.';
        }
        if ($intentCode === 'task_summary') {
            return 'Return ONLY one JSON object. No markdown, no prose, no code fences. Required keys: '
                . '{"summary":string,"improved_description":string,"risks":[string],"suggested_tasks":[object],"checklist_items":[string|object],'
                . '"calendar_slots":[object],"questions":[string],"meta":{"intent_code":"task_summary"}}.';
        }
        if ($intentCode === 'task_decomposition') {
            return 'Return ONLY one JSON object. No markdown, no prose, no code fences. Required keys: '
                . '{"summary":string,"risks":[string],"suggested_tasks":[{"title":string,"description"?:string,"recommended_minutes"?:number}],'
                . '"checklist_items":[string|object],"calendar_slots":[object],"questions":[string],"meta":{"intent_code":"task_decomposition"}}.';
        }
        if ($intentCode === 'task_quality') {
            return 'Return ONLY one JSON object. No markdown, no prose, no code fences. Required keys: '
                . '{"summary":string,"risks":[string],"suggested_tasks":[object],"checklist_items":[string|object],"calendar_slots":[object],'
                . '"questions":[string],"meta":{"intent_code":"task_quality"}}.';
        }
        if ($intentCode === 'task_next_action') {
            return 'Return ONLY one JSON object. No markdown, no prose, no code fences. Required keys: '
                . '{"summary":string,"risks":[string],"suggested_tasks":[{"title":string,"description"?:string,"recommended_minutes"?:number}],'
                . '"checklist_items":[string|object],"calendar_slots":[object],"questions":[string],"meta":{"intent_code":"task_next_action"}}.';
        }
        if ($intentCode === 'task_comment_draft') {
            return 'Return ONLY one JSON object. No markdown, no prose, no code fences. Required keys: '
                . '{"summary":string,"comment_draft":string,"risks":[string],"suggested_tasks":[object],"checklist_items":[string|object],'
                . '"calendar_slots":[object],"questions":[string],"meta":{"intent_code":"task_comment_draft"}}.';
        }
        if ($intentCode === 'project_summary') {
            return 'Return ONLY one JSON object. No markdown, no prose, no code fences. Required keys: '
                . '{"summary":string,"risks":[string],"suggested_tasks":[string|object],"checklist_items":[string|object],'
                . '"calendar_slots":[object],"questions":[string],"meta":{"intent_code":"project_summary"}}.';
        }
        if ($intentCode === 'project_risk_summary') {
            return 'Return ONLY one JSON object. No markdown, no prose, no code fences. Required keys: '
                . '{"summary":string,"risks":[string],"suggested_tasks":[string|object],"checklist_items":[string|object],'
                . '"calendar_slots":[object],"questions":[string],"meta":{"intent_code":"project_risk_summary"}}.';
        }
        if ($intentCode === 'project_client_report') {
            return 'Return ONLY one JSON object. No markdown, no prose, no code fences. Required keys: '
                . '{"summary":string,"report_draft":string,"risks":[string],"suggested_tasks":[string|object],"checklist_items":[string|object],'
                . '"calendar_slots":[object],"questions":[string],"meta":{"intent_code":"project_client_report"}}.';
        }
        if ($intentCode === 'client_summary') {
            return 'Return ONLY one JSON object. No markdown, no prose, no code fences. Required keys: '
                . '{"summary":string,"facts":[string],"risks":[string],"suggested_tasks":[string|object],"questions":[string],'
                . '"meta":{"intent_code":"client_summary"}}.';
        }
        if ($intentCode === 'client_meeting_prep') {
            return 'Return ONLY one JSON object. No markdown, no prose, no code fences. Required keys: '
                . '{"summary":string,"facts":[string],"questions":[string],"suggested_tasks":[string|object],'
                . '"meta":{"intent_code":"client_meeting_prep"}}.';
        }
        if ($intentCode === 'client_data_quality') {
            return 'Return ONLY one JSON object. No markdown, no prose, no code fences. Required keys: '
                . '{"summary":string,"facts":[string],"risks":[string],"suggested_tasks":[string|object],"questions":[string],'
                . '"meta":{"intent_code":"client_data_quality"}}.';
        }
        if ($intentCode === 'client_safe_report') {
            return 'Return ONLY one JSON object. No markdown, no prose, no code fences. Required keys: '
                . '{"summary":string,"report_draft":string,"facts":[string],"risks":[string],"suggested_tasks":[string|object],'
                . '"questions":[string],"meta":{"intent_code":"client_safe_report"}}.';
        }
        if ($intentCode === 'calendar_event_agenda') {
            return 'Return ONLY one JSON object. No markdown, no prose, no code fences. Required keys: '
                . '{"summary":string,"agenda":[string|object],"risks":[string],"suggested_tasks":[string|object],"questions":[string],'
                . '"meta":{"intent_code":"calendar_event_agenda"}}.';
        }
        if ($intentCode === 'analytics_kpi_explanation') {
            return 'Return ONLY one JSON object. No markdown, no prose, no code fences. Required keys: '
                . '{"summary":string,"facts":[string],"risks":[string],"suggested_tasks":[string|object],"questions":[string],'
                . '"meta":{"intent_code":"analytics_kpi_explanation"}}.';
        }
        if ($intentCode === 'analytics_risks_explanation') {
            return 'Return ONLY one JSON object. No markdown, no prose, no code fences. Required keys: '
                . '{"summary":string,"facts":[string],"risks":[string],"suggested_tasks":[string|object],"questions":[string],'
                . '"meta":{"intent_code":"analytics_risks_explanation"}}.';
        }
        if ($intentCode === 'analytics_team_workload_summary') {
            return 'Return ONLY one JSON object. No markdown, no prose, no code fences. Required keys: '
                . '{"summary":string,"facts":[string],"risks":[string],"suggested_tasks":[string|object],"questions":[string],'
                . '"meta":{"intent_code":"analytics_team_workload_summary"}}.';
        }
        if ($intentCode === 'admin_log_review') {
            return 'Return ONLY one JSON object. No markdown, no prose, no code fences. Required keys: '
                . '{"summary":string,"facts":[string],"risks":[string],"suggested_tasks":[string|object],"questions":[string],'
                . '"meta":{"intent_code":"admin_log_review"}}.';
        }
        if ($intentCode === 'webhook_health_review') {
            return 'Return ONLY one JSON object. No markdown, no prose, no code fences. Required keys: '
                . '{"summary":string,"facts":[string],"risks":[string],"suggested_tasks":[string|object],"questions":[string],'
                . '"meta":{"intent_code":"webhook_health_review"}}.';
        }
        if ($intentCode === 'workflow_rule_audit') {
            return 'Return ONLY one JSON object. No markdown, no prose, no code fences. Required keys: '
                . '{"summary":string,"facts":[string],"risks":[string],"suggested_tasks":[string|object],"questions":[string],'
                . '"meta":{"intent_code":"workflow_rule_audit"}}.';
        }
        if ($intentCode === 'my_day_plan') {
            return 'Return ONLY one JSON object. No markdown, no prose, no code fences. Required keys: '
                . '{"summary":string,"work_items":[object],"calendar_slots":[object],"warnings":[string],"questions":[string],'
                . '"meta":{"intent_code":"my_day_plan"}}.';
        }
        if ($intentCode === 'my_week_plan') {
            return 'Return ONLY one JSON object. No markdown, no prose, no code fences. Required keys: '
                . '{"summary":string,"weekly_focus":[object],"calendar_slots":[object],"warnings":[string],"questions":[string],'
                . '"meta":{"intent_code":"my_week_plan"}}.';
        }
        if ($intentCode === 'task_list_priority') {
            return 'Return ONLY one JSON object. No markdown, no prose, no code fences. Required keys: '
                . '{"summary":string,"priority_ranking":[object],"risks":[string],"suggested_tasks":[string|object],"questions":[string],'
                . '"meta":{"intent_code":"task_list_priority"}}.';
        }
        return 'Return only a single JSON object in Russian for intent ' . $intentCode . '. No markdown, no prose, no code fences, no extra keys.';
    }

    private function sanitizePromptRuntimeForStorage(array $promptRuntime): array
    {
        return [
            'intent_code' => trim((string)($promptRuntime['intent_code'] ?? '')),
            'meta' => [
                'context_budget_tokens' => (int)($promptRuntime['meta']['context_budget_tokens'] ?? 0),
                'context_estimated_tokens' => (int)($promptRuntime['meta']['context_estimated_tokens'] ?? 0),
                'context_truncated' => (bool)($promptRuntime['meta']['context_truncated'] ?? false),
                'context_dropped_keys' => (array)($promptRuntime['meta']['context_dropped_keys'] ?? []),
                'user_prompt_estimated_tokens' => (int)($promptRuntime['meta']['user_prompt_estimated_tokens'] ?? 0),
            ],
        ];
    }

    /**
     * Keep only intent-relevant fields in prompt context to reduce leakage surface.
     *
     * @param array<string,mixed> $context
     * @return array<string,mixed>
     */
    private function minimizeContextForIntent(string $intentCode, array $context): array
    {
        $allowedByIntent = [
            'task_summary' => ['task_public_id', 'title', 'description', 'status', 'priority', 'due_at', 'project_public_id', 'project_title', 'project_summary', 'client_summary', 'parent_task', 'subtasks', 'comments', 'checklists', 'prompt'],
            'task_decomposition' => ['task_public_id', 'title', 'description', 'status', 'priority', 'due_at', 'project_summary', 'client_summary', 'parent_task', 'subtasks', 'comments', 'checklists', 'prompt'],
            'task_checklist' => ['task_public_id', 'title', 'description', 'status', 'priority', 'due_at', 'project_summary', 'client_summary', 'parent_task', 'subtasks', 'comments', 'checklists', 'prompt'],
            'task_quality' => ['task_public_id', 'title', 'description', 'status', 'priority', 'due_at', 'project_summary', 'client_summary', 'parent_task', 'subtasks', 'comments', 'checklists', 'prompt'],
            'task_next_action' => ['task_public_id', 'title', 'description', 'status', 'priority', 'due_at', 'project_summary', 'client_summary', 'parent_task', 'subtasks', 'comments', 'checklists', 'prompt'],
            'task_comment_draft' => ['task_public_id', 'title', 'description', 'status', 'priority', 'due_at', 'project_summary', 'client_summary', 'parent_task', 'subtasks', 'comments', 'checklists', 'prompt'],
            'project_summary' => ['project_public_id', 'title', 'description', 'status', 'priority', 'client_public_id', 'evidence', 'prompt'],
            'project_risk_summary' => ['project_public_id', 'title', 'description', 'status', 'priority', 'client_public_id', 'evidence', 'prompt'],
            'project_client_report' => ['project_public_id', 'title', 'description', 'status', 'priority', 'client_public_id', 'evidence', 'prompt'],
            'client_summary' => ['client_public_id', 'title', 'status', 'client_type', 'notes', 'prompt'],
            'client_meeting_prep' => ['client_public_id', 'title', 'status', 'client_type', 'notes', 'upcoming_events', 'open_tasks', 'recent_projects', 'prompt'],
            'client_data_quality' => ['client_public_id', 'title', 'status', 'client_type', 'email', 'phone', 'tax_inn', 'tax_kpp', 'tax_ogrn', 'tax_ogrnip', 'bank_account', 'bank_bik', 'bank_corr_account', 'bank_name', 'website', 'address_legal', 'address_postal', 'quality_profile', 'prompt'],
            'client_safe_report' => ['client_public_id', 'title', 'status', 'client_type', 'upcoming_events', 'open_tasks', 'recent_projects', 'prompt'],
            'calendar_event_agenda' => ['event_public_id', 'title', 'description', 'starts_at', 'ends_at', 'task_public_id', 'project_public_id', 'prompt'],
            'dashboard_daily_digest' => ['dashboard', 'analytics', 'prompt'],
            'analytics_kpi_explanation' => ['period', 'analytics', 'prompt'],
            'analytics_risks_explanation' => ['period', 'projects', 'analytics', 'prompt'],
            'analytics_team_workload_summary' => ['period', 'users', 'analytics', 'prompt'],
            'admin_log_review' => ['widgets_summary', 'widgets_system', 'security_logs'],
            'webhook_health_review' => ['widgets_summary', 'widgets_system', 'webhook_summary', 'webhook_subscriptions', 'webhook_deliveries'],
            'workflow_rule_audit' => ['workflow_rules', 'workflow_runs'],
            'my_day_plan' => ['agenda', 'candidate_tasks', 'date'],
            'my_week_plan' => ['agenda', 'candidate_tasks', 'date'],
            'task_list_priority' => ['tasks', 'view_mode', 'filters'],
        ];

        $allowlist = $allowedByIntent[$intentCode] ?? [];
        if ($allowlist === []) {
            return $context;
        }

        $filtered = [];
        foreach ($allowlist as $key) {
            if (array_key_exists($key, $context)) {
                $filtered[$key] = $context[$key];
            }
        }

        return $filtered;
    }

    /** @param array<string,mixed> $provider */
    private function useStrictPromptMaskingForProvider(array $provider): bool
    {
        $providerCode = strtolower(trim((string)($provider['provider_code'] ?? '')));
        if (in_array($providerCode, ['mock', 'local', 'self_hosted', 'self-hosted', 'ollama', 'lm_studio', 'lmstudio'], true)) {
            return false;
        }

        $baseUrl = trim((string)($provider['base_url'] ?? ''));
        if ($baseUrl === '') {
            return true;
        }

        $host = strtolower((string)parse_url($baseUrl, PHP_URL_HOST));
        if ($host === '') {
            return true;
        }

        if (
            $host === 'localhost'
            || str_ends_with($host, '.localhost')
            || str_ends_with($host, '.local')
            || str_ends_with($host, '.internal')
        ) {
            return false;
        }

        if (filter_var($host, FILTER_VALIDATE_IP)) {
            $isPublic = (bool)filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE);
            return $isPublic;
        }

        return true;
    }

    /**
     * @param array<string,mixed> $provider
     * @param array<string,mixed> $llm
     * @return array{ok:bool,code?:string}
     */
    private function resolveLlmExecution(array $provider, array $llm): array
    {
        $llmOk = (bool)($llm['ok'] ?? false) && trim((string)($llm['text'] ?? '')) !== '';
        if ($llmOk) {
            return ['ok' => true];
        }

        $llmCode = strtoupper(trim((string)($llm['code'] ?? '')));
        $providerCode = strtolower(trim((string)($provider['provider_code'] ?? '')));
        if (in_array($providerCode, ['mock', 'fake'], true)) {
            if ($llmCode !== '' && $llmCode !== 'OK') {
                return ['ok' => false, 'code' => $llmCode];
            }
            return ['ok' => true];
        }

        return ['ok' => false, 'code' => $llmCode !== '' ? $llmCode : 'AI_PROVIDER_UNAVAILABLE'];
    }

    /** @param array<string,mixed>|null $provider */
    private function isProviderConfigured(?array $provider): bool
    {
        if (!is_array($provider) || $provider === []) {
            return false;
        }
        $providerId = (int)($provider['id'] ?? 0);
        if ($providerId <= 0) {
            return false;
        }
        return $this->providers->hasSecret($providerId);
    }

    /** @param array<string,mixed>|null $provider
     *  @return array<string,mixed>|null
     */
    private function resolveUsableProvider(?array $provider): ?array
    {
        if (!$this->isProviderConfigured($provider)) {
            return null;
        }
        if (!is_array($provider)) {
            return null;
        }
        if ($this->isMockProvider($provider) && !$this->isMockRuntimeAllowed()) {
            return null;
        }

        return $provider;
    }

    /** @param array<string,mixed> $provider */
    private function isMockProvider(array $provider): bool
    {
        $code = strtolower(trim((string)($provider['provider_code'] ?? '')));
        return in_array($code, ['mock', 'fake'], true);
    }

    private function isMockRuntimeAllowed(): bool
    {
        $explicit = strtolower(trim((string)getenv('CRM_AI_ALLOW_MOCK_RUNTIME')));
        if (in_array($explicit, ['1', 'true', 'yes', 'on'], true)) {
            return true;
        }
        $env = strtolower(trim((string)getenv('APP_ENV')));
        return in_array($env, ['dev', 'local', 'test'], true);
    }

    /** @param array<string,mixed> $payload */
    private function writeUsageLog(array $payload): void
    {
        $this->runtime->createUsageLog($payload);
        $this->applyRetentionCleanup();
    }

    private function applyRetentionCleanup(): void
    {
        try {
            $this->runtime->cleanupByRetention($this->retention->getPolicies());
        } catch (\Throwable) {
            // Cleanup is best-effort and must not break suggestion flows.
        }
    }

    private function hasActorPermission(array $actor, string $permission): bool
    {
        if ((bool)($actor['is_root'] ?? false)) {
            return true;
        }
        $permission = trim($permission);
        if ($permission === '') {
            return true;
        }

        $codes = is_array($actor['permission_codes'] ?? null) ? (array)$actor['permission_codes'] : [];
        return in_array($permission, $codes, true);
    }

    /** @param array<string,mixed> $actor */
    private function isFeatureEnabledForActor(string $flagCode, array $actor, bool $default): bool
    {
        if ((bool)($actor['is_root'] ?? false)) {
            return true;
        }

        return $this->featureFlags->isEnabled($flagCode, $default);
    }

    private function isValidPublicId(string $value): bool
    {
        $trimmed = trim($value);
        if ($trimmed === '') {
            return false;
        }

        return (bool)preg_match('/^[A-Za-z0-9]+_[A-Za-z0-9]+$/', $trimmed);
    }

    /**
     * @param array<string,mixed> $actor
     * @return array{ok:false,code:string,retry_after?:int}|null
     */
    private function ensureIntentAccessBeforeContextBuild(string $intentCode, array $actor): ?array
    {
        if (!$this->isFeatureEnabledForActor('ai.enabled', $actor, false)) {
            return ['ok' => false, 'code' => 'AI_DISABLED'];
        }
        if (!in_array($intentCode, $this->actionAllowlist(), true)) {
            return ['ok' => false, 'code' => 'AI_ACTION_TYPE_NOT_ALLOWED'];
        }

        $intent = $this->intentSettings->findByIntentCode($intentCode);
        if ($intent && !(bool)($intent['is_enabled'] ?? true)) {
            return ['ok' => false, 'code' => 'AI_INTENT_DISABLED'];
        }
        if ($intent && trim((string)($intent['feature_flag'] ?? '')) !== '') {
            if (!$this->isFeatureEnabledForActor(trim((string)$intent['feature_flag']), $actor, false)) {
                return ['ok' => false, 'code' => 'AI_FEATURE_DISABLED'];
            }
        }
        if ($intent && trim((string)($intent['required_permission'] ?? '')) !== '') {
            if (!$this->hasActorPermission($actor, trim((string)$intent['required_permission']))) {
                return ['ok' => false, 'code' => 'FORBIDDEN'];
            }
        }

        return null;
    }

    /**
     * @param array<string,mixed> $limitResult
     * @return array{ok:false,code:string,retry_after?:int}
     */
    private function limitFailure(array $limitResult, string $defaultCode): array
    {
        $failure = [
            'ok' => false,
            'code' => (string)($limitResult['code'] ?? $defaultCode),
        ];
        if (isset($limitResult['retry_after'])) {
            $retryAfter = (int)$limitResult['retry_after'];
            if ($retryAfter > 0) {
                $failure['retry_after'] = $retryAfter;
            }
        }

        return $failure;
    }
}
