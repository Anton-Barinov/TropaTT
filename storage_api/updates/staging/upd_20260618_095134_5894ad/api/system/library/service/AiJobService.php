<?php
declare(strict_types=1);

namespace Api\System\Library\Service;

use Api\Model\Ai\AiProviderRepository;
use Api\Model\Ai\AiRuntimeRepository;
use Api\System\Library\Logger\JsonLogger;

final class AiJobService
{
    /** @var array<string,array<string,mixed>> */
    private const JOB_CATALOG = [
        'ai:user-daily-work-plan' => [
            'action_type' => 'my_day_plan',
            'intent_code' => 'my_day_plan',
            'feature_flag' => 'ai.cron.daily_work_plan',
            'require_provider' => true,
            'scope_type' => 'user',
            'idempotency_window' => 'day',
            'schedule' => ['mode' => 'workday_before_start', 'minutes_before' => 30],
            'limits' => ['max_entities_per_run' => 50, 'max_tokens_per_run' => 12000],
            'retry' => ['attempts' => 2, 'backoff_ms' => 1000],
        ],
        'ai:user-daily-digest' => [
            'action_type' => 'dashboard_daily_digest',
            'intent_code' => 'dashboard_daily_digest',
            'feature_flag' => 'ai.cron.user_daily_digest',
            'require_provider' => true,
            'scope_type' => 'user',
            'idempotency_window' => 'day',
            'schedule' => ['mode' => 'workday_end', 'minutes_after' => 15],
            'limits' => ['max_entities_per_run' => 100, 'max_tokens_per_run' => 12000],
            'retry' => ['attempts' => 2, 'backoff_ms' => 1000],
        ],
        'ai:user-weekly-plan' => [
            'action_type' => 'my_week_plan',
            'intent_code' => 'my_week_plan',
            'feature_flag' => 'ai.cron.user_weekly_plan',
            'require_provider' => true,
            'scope_type' => 'user',
            'idempotency_window' => 'week',
            'schedule' => ['mode' => 'weekly', 'day_of_week' => 1, 'time' => '08:30'],
            'limits' => ['max_entities_per_run' => 200, 'max_tokens_per_run' => 18000],
            'retry' => ['attempts' => 2, 'backoff_ms' => 1200],
        ],
        'ai:manager-weekly-digest' => [
            'action_type' => 'dashboard_daily_digest',
            'intent_code' => 'dashboard_daily_digest',
            'feature_flag' => 'ai.cron.manager_weekly_digest',
            'require_provider' => true,
            'scope_type' => 'system',
            'idempotency_window' => 'week',
            'schedule' => ['mode' => 'weekly', 'day_of_week' => 1, 'time' => '09:00'],
            'limits' => ['max_entities_per_run' => 50, 'max_tokens_per_run' => 20000],
            'retry' => ['attempts' => 3, 'backoff_ms' => 1500],
        ],
        'ai:task-risk-scan' => [
            'action_type' => 'project_risk_summary',
            'intent_code' => 'project_risk_summary',
            'feature_flag' => 'ai.cron.task_risk_scan',
            'require_provider' => true,
            'scope_type' => 'system',
            'idempotency_window' => 'day',
            'schedule' => ['mode' => 'daily', 'time' => '06:30'],
            'limits' => ['max_entities_per_run' => 200, 'max_tokens_per_run' => 22000],
            'retry' => ['attempts' => 3, 'backoff_ms' => 1500],
        ],
        'ai:task-quality-scan' => [
            'action_type' => 'task_quality',
            'intent_code' => 'task_quality',
            'feature_flag' => 'ai.cron.task_quality_scan',
            'require_provider' => true,
            'scope_type' => 'system',
            'idempotency_window' => 'day',
            'schedule' => ['mode' => 'daily', 'time' => '06:45'],
            'limits' => ['max_entities_per_run' => 200, 'max_tokens_per_run' => 18000],
            'retry' => ['attempts' => 2, 'backoff_ms' => 1200],
        ],
        'ai:task-decomposition-scan' => [
            'action_type' => 'task_decomposition',
            'intent_code' => 'task_decomposition',
            'feature_flag' => 'ai.cron.task_decomposition_scan',
            'require_provider' => true,
            'scope_type' => 'system',
            'idempotency_window' => 'day',
            'schedule' => ['mode' => 'daily', 'time' => '07:00'],
            'limits' => ['max_entities_per_run' => 150, 'max_tokens_per_run' => 18000],
            'retry' => ['attempts' => 2, 'backoff_ms' => 1200],
        ],
        'ai:meeting-agenda' => [
            'action_type' => 'calendar_event_agenda',
            'intent_code' => 'calendar_event_agenda',
            'feature_flag' => 'ai.cron.meeting_agenda',
            'require_provider' => true,
            'scope_type' => 'system',
            'idempotency_window' => 'day',
            'schedule' => ['mode' => 'hourly', 'minute' => 5],
            'limits' => ['max_entities_per_run' => 120, 'max_tokens_per_run' => 16000],
            'retry' => ['attempts' => 2, 'backoff_ms' => 1000],
        ],
        'ai:project-daily-summary' => [
            'action_type' => 'project_summary',
            'intent_code' => 'project_summary',
            'feature_flag' => 'ai.cron.project_daily_summary',
            'require_provider' => true,
            'scope_type' => 'system',
            'idempotency_window' => 'day',
            'schedule' => ['mode' => 'daily', 'time' => '07:15'],
            'limits' => ['max_entities_per_run' => 150, 'max_tokens_per_run' => 18000],
            'retry' => ['attempts' => 2, 'backoff_ms' => 1000],
        ],
        'ai:client-weekly-report' => [
            'action_type' => 'client_safe_report',
            'intent_code' => 'client_safe_report',
            'feature_flag' => 'ai.cron.client_weekly_report',
            'require_provider' => true,
            'scope_type' => 'system',
            'idempotency_window' => 'week',
            'schedule' => ['mode' => 'weekly', 'day_of_week' => 5, 'time' => '16:30'],
            'limits' => ['max_entities_per_run' => 120, 'max_tokens_per_run' => 22000],
            'retry' => ['attempts' => 3, 'backoff_ms' => 1500],
        ],
        'ai:team-workload-scan' => [
            'action_type' => 'analytics_team_workload_summary',
            'intent_code' => 'analytics_team_workload_summary',
            'feature_flag' => 'ai.cron.team_workload_scan',
            'require_provider' => true,
            'scope_type' => 'system',
            'idempotency_window' => 'day',
            'schedule' => ['mode' => 'daily', 'time' => '07:30'],
            'limits' => ['max_entities_per_run' => 100, 'max_tokens_per_run' => 20000],
            'retry' => ['attempts' => 2, 'backoff_ms' => 1200],
        ],
        'ai:sla-approval-scan' => [
            'action_type' => 'workflow_rule_audit',
            'intent_code' => 'workflow_rule_audit',
            'feature_flag' => 'ai.cron.sla_approval_scan',
            'require_provider' => true,
            'scope_type' => 'system',
            'idempotency_window' => 'day',
            'schedule' => ['mode' => 'hourly', 'minute' => 10],
            'limits' => ['max_entities_per_run' => 150, 'max_tokens_per_run' => 18000],
            'retry' => ['attempts' => 3, 'backoff_ms' => 1500],
        ],
        'ai:data-quality-scan' => [
            'action_type' => 'client_data_quality',
            'intent_code' => 'client_data_quality',
            'feature_flag' => 'ai.cron.data_quality_scan',
            'require_provider' => true,
            'scope_type' => 'system',
            'idempotency_window' => 'day',
            'schedule' => ['mode' => 'daily', 'time' => '07:45'],
            'limits' => ['max_entities_per_run' => 200, 'max_tokens_per_run' => 18000],
            'retry' => ['attempts' => 2, 'backoff_ms' => 1200],
        ],
        'ai:import-review' => [
            'action_type' => 'admin_log_review',
            'intent_code' => 'admin_log_review',
            'feature_flag' => 'ai.cron.import_review',
            'require_provider' => true,
            'scope_type' => 'system',
            'idempotency_window' => 'day',
            'schedule' => ['mode' => 'hourly', 'minute' => 20],
            'limits' => ['max_entities_per_run' => 120, 'max_tokens_per_run' => 16000],
            'retry' => ['attempts' => 2, 'backoff_ms' => 1200],
        ],
        'ai:security-log-review' => [
            'action_type' => 'admin_log_review',
            'intent_code' => 'admin_log_review',
            'feature_flag' => 'ai.cron.security_log_review',
            'require_provider' => true,
            'scope_type' => 'system',
            'idempotency_window' => 'day',
            'schedule' => ['mode' => 'hourly', 'minute' => 30],
            'limits' => ['max_entities_per_run' => 120, 'max_tokens_per_run' => 16000],
            'retry' => ['attempts' => 2, 'backoff_ms' => 1200],
        ],
        'ai:webhook-health-review' => [
            'action_type' => 'webhook_health_review',
            'intent_code' => 'webhook_health_review',
            'feature_flag' => 'ai.cron.webhook_health_review',
            'require_provider' => true,
            'scope_type' => 'system',
            'idempotency_window' => 'day',
            'schedule' => ['mode' => 'hourly', 'minute' => 40],
            'limits' => ['max_entities_per_run' => 120, 'max_tokens_per_run' => 16000],
            'retry' => ['attempts' => 2, 'backoff_ms' => 1200],
        ],
        'ai:workflow-audit' => [
            'action_type' => 'workflow_rule_audit',
            'intent_code' => 'workflow_rule_audit',
            'feature_flag' => 'ai.cron.workflow_audit',
            'require_provider' => true,
            'scope_type' => 'system',
            'idempotency_window' => 'day',
            'schedule' => ['mode' => 'daily', 'time' => '08:00'],
            'limits' => ['max_entities_per_run' => 120, 'max_tokens_per_run' => 18000],
            'retry' => ['attempts' => 2, 'backoff_ms' => 1200],
        ],
        'ai:semantic-index-refresh' => [
            'action_type' => 'task_list_priority',
            'intent_code' => 'task_list_priority',
            'feature_flag' => 'ai.cron.semantic_index_refresh',
            'require_provider' => false,
            'scope_type' => 'system',
            'idempotency_window' => 'day',
            'schedule' => ['mode' => 'daily', 'time' => '03:15'],
            'limits' => ['max_entities_per_run' => 500, 'max_tokens_per_run' => 0],
            'retry' => ['attempts' => 2, 'backoff_ms' => 1000],
        ],
        'ai:suggestion-cleanup' => [
            'action_type' => 'suggestion_cleanup',
            'intent_code' => 'suggestion_cleanup',
            'feature_flag' => 'ai.cron.suggestion_cleanup',
            'require_provider' => false,
            'scope_type' => 'system',
            'idempotency_window' => 'day',
            'schedule' => ['mode' => 'daily', 'time' => '02:30'],
            'limits' => ['max_entities_per_run' => 1000, 'max_tokens_per_run' => 0],
            'retry' => ['attempts' => 1, 'backoff_ms' => 0],
        ],
    ];

    public function __construct(
        private readonly AiRuntimeRepository $runtime,
        private readonly AiProviderRepository $providers,
        private readonly FeatureFlagService $featureFlags,
        private readonly SettingService $settings,
        private readonly JsonLogger $logger,
        private readonly AiRetentionPolicyService $retention
    ) {
    }

    /** @return list<string> */
    public function supportedJobCodes(): array
    {
        return array_values(array_keys(self::JOB_CATALOG));
    }

    /**
     * @param array<string,mixed> $actor
     * @return array{ok:bool,code?:string,job?:array<string,mixed>}
     */
    public function retry(string $publicId, array $actor): array
    {
        $source = $this->runtime->findJobByPublicId($publicId);
        if (!$source) {
            $this->logCronUsage('retry', '', (int)($actor['id'] ?? 0), 'error', 'AI_JOB_NOT_FOUND', [
                'trigger' => 'retry',
                'source_job_public_id' => $publicId,
            ]);
            return ['ok' => false, 'code' => 'AI_JOB_NOT_FOUND'];
        }
        if (!$this->canReadJob($source, $actor)) {
            $this->logCronUsage('retry', (string)($source['intent_code'] ?? ''), (int)($actor['id'] ?? 0), 'error', 'AI_JOB_NOT_FOUND', [
                'trigger' => 'retry',
                'source_job_public_id' => $publicId,
            ]);
            return ['ok' => false, 'code' => 'AI_JOB_NOT_FOUND'];
        }

        $status = trim((string)($source['status'] ?? ''));
        if (in_array($status, ['queued', 'running'], true)) {
            $this->logCronUsage((string)($source['action_type'] ?? 'retry'), (string)($source['intent_code'] ?? ''), (int)($actor['id'] ?? 0), 'error', 'AI_JOB_RETRY_NOT_ALLOWED', [
                'trigger' => 'retry',
                'source_job_public_id' => $publicId,
            ]);
            return ['ok' => false, 'code' => 'AI_JOB_RETRY_NOT_ALLOWED'];
        }

        $payload = $this->decodeJson((string)($source['payload_json'] ?? ''));
        $payload['retry_of_job_public_id'] = (string)($source['public_id'] ?? '');
        $payload['trigger'] = 'retry';

        $now = gmdate('Y-m-d H:i:s');
        $retryPublicId = $this->runtime->createJob([
            'job_type' => trim((string)($source['job_type'] ?? '')) !== '' ? (string)$source['job_type'] : 'cron',
            'action_type' => (string)($source['action_type'] ?? ''),
            'intent_code' => (string)($source['intent_code'] ?? ''),
            'status' => 'queued',
            'requested_by_user_id' => (int)($actor['id'] ?? 0) ?: null,
            'scope_type' => (string)($source['scope_type'] ?? ''),
            'scope_public_id' => (string)($source['scope_public_id'] ?? ''),
            'idempotency_key_hash' => null,
            'payload_json' => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'result_json' => null,
            'error_code' => null,
            'error_message' => null,
            'created_at' => $now,
            'started_at' => null,
            'finished_at' => null,
            'updated_at' => $now,
        ]);

        $this->logger->audit([
            'action' => 'ai_job_retry_requested',
            'actor_public_id' => (string)($actor['public_id'] ?? ''),
            'entity_type' => 'ai_job',
            'entity_public_id' => $retryPublicId,
            'source_job_public_id' => (string)($source['public_id'] ?? ''),
            'intent_code' => (string)($source['intent_code'] ?? ''),
            'scope_type' => (string)($source['scope_type'] ?? ''),
            'scope_public_id' => (string)($source['scope_public_id'] ?? ''),
        ]);

        $created = $this->runtime->findJobByPublicId($retryPublicId);
        if (!is_array($created)) {
            $this->logCronUsage((string)($source['action_type'] ?? 'retry'), (string)($source['intent_code'] ?? ''), (int)($actor['id'] ?? 0), 'error', 'AI_JOB_RETRY_FAILED', [
                'trigger' => 'retry',
                'source_job_public_id' => $publicId,
            ]);
            return ['ok' => false, 'code' => 'AI_JOB_RETRY_FAILED'];
        }

        $this->logCronUsage((string)($source['action_type'] ?? 'retry'), (string)($source['intent_code'] ?? ''), (int)($actor['id'] ?? 0), 'ok', null, [
            'trigger' => 'retry',
            'source_job_public_id' => $publicId,
            'job_public_id' => $retryPublicId,
        ]);

        return ['ok' => true, 'job' => $this->normalizeJob($created, true)];
    }

    /**
     * @param array<string,mixed> $input
     * @param array<string,mixed> $actor
     * @return array{ok:bool,code?:string,dry_run?:array<string,mixed>}
     */
    public function dryRun(string $jobCode, array $input, array $actor): array
    {
        $normalizedCode = trim($jobCode);
        $job = self::JOB_CATALOG[$normalizedCode] ?? null;
        if (!is_array($job)) {
            $this->logCronUsage('dry_run', '', (int)($actor['id'] ?? 0), 'error', 'AI_JOB_CODE_NOT_ALLOWED', [
                'trigger' => 'dry-run',
                'job_code' => $normalizedCode,
            ]);
            return ['ok' => false, 'code' => 'AI_JOB_CODE_NOT_ALLOWED'];
        }

        $scopeType = (string)($job['scope_type'] ?? 'system');
        $scopePublicId = $this->resolveScopePublicId($scopeType, $input, $actor);
        $checks = $this->buildRunChecks($job, $input, $actor, (bool)($input['with_provider'] ?? false), $scopePublicId);
        $canRun = (bool)($checks['can_run'] ?? false);
        $idempotencyHash = $this->buildIdempotencyHash($normalizedCode, $scopeType, $scopePublicId, $input);
        $executionContext = $this->buildExecutionContext($normalizedCode, $job, $input, $actor, $scopePublicId);

        $this->logCronUsage((string)($job['action_type'] ?? ''), (string)($job['intent_code'] ?? ''), (int)($actor['id'] ?? 0), $canRun ? 'dry_run_ok' : 'dry_run_blocked', $canRun ? null : 'AI_JOB_RUN_NOT_ALLOWED', [
            'trigger' => 'dry-run',
            'job_code' => $normalizedCode,
            'scope_type' => $scopeType,
            'scope_public_id' => $scopePublicId,
        ]);

        return [
            'ok' => true,
            'dry_run' => [
                'job_code' => $normalizedCode,
                'action_type' => (string)($job['action_type'] ?? ''),
                'intent_code' => (string)($job['intent_code'] ?? ''),
                'scope_type' => $scopeType,
                'scope_public_id' => $scopePublicId,
                'can_run' => $canRun,
                'with_provider' => (bool)($input['with_provider'] ?? false),
                'provider_call_performed' => false,
                'schedule' => is_array($job['schedule'] ?? null) ? (array)$job['schedule'] : [],
                'limits' => is_array($job['limits'] ?? null) ? (array)$job['limits'] : [],
                'retry' => is_array($job['retry'] ?? null) ? (array)$job['retry'] : [],
                'execution_context' => $executionContext,
                'idempotency_key_hash_preview' => $idempotencyHash,
                'checks' => $checks['checks'] ?? [],
                'warnings' => $checks['warnings'] ?? [],
            ],
        ];
    }

    /**
     * @param array<string,mixed> $input
     * @param array<string,mixed> $actor
     * @return array{ok:bool,code?:string,job?:array<string,mixed>}
     */
    public function runOnce(string $jobCode, array $input, array $actor): array
    {
        $normalizedCode = trim($jobCode);
        $job = self::JOB_CATALOG[$normalizedCode] ?? null;
        if (!is_array($job)) {
            $this->logCronUsage('run_once', '', (int)($actor['id'] ?? 0), 'error', 'AI_JOB_CODE_NOT_ALLOWED', [
                'trigger' => 'run-once',
                'job_code' => $normalizedCode,
            ]);
            return ['ok' => false, 'code' => 'AI_JOB_CODE_NOT_ALLOWED'];
        }

        $scopeType = (string)($job['scope_type'] ?? 'system');
        $scopePublicId = $this->resolveScopePublicId($scopeType, $input, $actor);
        $checks = $this->buildRunChecks($job, $input, $actor, false, $scopePublicId);
        if (!(bool)($checks['can_run'] ?? false)) {
            $errors = is_array($checks['errors'] ?? null) ? (array)$checks['errors'] : [];
            $firstError = (string)($errors[0] ?? 'AI_JOB_RUN_NOT_ALLOWED');
            $this->logCronUsage((string)($job['action_type'] ?? ''), (string)($job['intent_code'] ?? ''), (int)($actor['id'] ?? 0), 'error', $firstError, [
                'trigger' => 'run-once',
                'job_code' => $normalizedCode,
                'scope_type' => $scopeType,
                'scope_public_id' => $scopePublicId,
            ]);
            return ['ok' => false, 'code' => $firstError];
        }

        $idempotencyHash = $this->buildIdempotencyHash($normalizedCode, $scopeType, $scopePublicId, $input);
        $activeDuplicate = $this->runtime->findActiveJobByIdempotencyHash($idempotencyHash);
        if (is_array($activeDuplicate)) {
            $this->logCronUsage((string)($job['action_type'] ?? ''), (string)($job['intent_code'] ?? ''), (int)($actor['id'] ?? 0), 'error', 'AI_JOB_ALREADY_QUEUED', [
                'trigger' => 'run-once',
                'job_code' => $normalizedCode,
                'scope_type' => $scopeType,
                'scope_public_id' => $scopePublicId,
            ]);
            return ['ok' => false, 'code' => 'AI_JOB_ALREADY_QUEUED'];
        }

        $executionContext = $this->buildExecutionContext($normalizedCode, $job, $input, $actor, $scopePublicId);
        $runAsService = (bool)($input['run_as_service'] ?? false);
        $now = gmdate('Y-m-d H:i:s');
        $payload = [
            'job_code' => $normalizedCode,
            'trigger' => 'run-once',
            'requested_by_user_public_id' => $runAsService ? '' : (string)($actor['public_id'] ?? ''),
            'service_actor' => $runAsService ? 'cron.service' : null,
            'input' => $this->sanitizeInput($input),
            'schedule' => is_array($job['schedule'] ?? null) ? (array)$job['schedule'] : [],
            'limits' => is_array($job['limits'] ?? null) ? (array)$job['limits'] : [],
            'retry' => is_array($job['retry'] ?? null) ? (array)$job['retry'] : [],
            'execution_context' => $executionContext,
        ];

        $jobPublicId = $this->runtime->createJob([
            'job_type' => 'cron',
            'action_type' => (string)($job['action_type'] ?? ''),
            'intent_code' => (string)($job['intent_code'] ?? ''),
            'status' => 'queued',
            'requested_by_user_id' => $runAsService ? null : ((int)($actor['id'] ?? 0) ?: null),
            'scope_type' => $scopeType,
            'scope_public_id' => $scopePublicId,
            'idempotency_key_hash' => $idempotencyHash,
            'payload_json' => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'result_json' => null,
            'error_code' => null,
            'error_message' => null,
            'created_at' => $now,
            'started_at' => null,
            'finished_at' => null,
            'updated_at' => $now,
        ]);

        $this->logger->audit([
            'action' => 'ai_job_run_once_requested',
            'actor_public_id' => (string)($actor['public_id'] ?? ''),
            'entity_type' => 'ai_job',
            'entity_public_id' => $jobPublicId,
            'job_code' => $normalizedCode,
            'intent_code' => $job['intent_code'],
            'scope_type' => $scopeType,
            'scope_public_id' => $scopePublicId,
        ]);

        $created = $this->runtime->findJobByPublicId($jobPublicId);
        if (!is_array($created)) {
            $this->logCronUsage((string)($job['action_type'] ?? ''), (string)($job['intent_code'] ?? ''), (int)($actor['id'] ?? 0), 'error', 'AI_JOB_RUN_ONCE_FAILED', [
                'trigger' => 'run-once',
                'job_code' => $normalizedCode,
                'scope_type' => $scopeType,
                'scope_public_id' => $scopePublicId,
            ]);
            return ['ok' => false, 'code' => 'AI_JOB_RUN_ONCE_FAILED'];
        }

        $this->logCronUsage((string)($job['action_type'] ?? ''), (string)($job['intent_code'] ?? ''), (int)($actor['id'] ?? 0), 'queued', null, [
            'trigger' => 'run-once',
            'job_code' => $normalizedCode,
            'scope_type' => $scopeType,
            'scope_public_id' => $scopePublicId,
            'job_public_id' => $jobPublicId,
            'run_as_service' => $runAsService,
        ]);

        return ['ok' => true, 'job' => $this->normalizeJob($created, true)];
    }

    /**
     * @param array<string,mixed> $filters
     * @param array<string,mixed> $actor
     * @return array{items:array<int,array<string,mixed>>,meta:array<string,mixed>}
     */
    public function list(array $filters, array $actor): array
    {
        $canViewAll = $this->canViewAllJobs($actor);
        [$items, $total, $page, $limit] = $this->runtime->listJobs(
            $filters,
            $canViewAll,
            (int)($actor['id'] ?? 0),
            (string)($actor['public_id'] ?? '')
        );

        $normalized = [];
        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }
            $normalized[] = $this->normalizeJob($item, false);
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

    /**
     * @param array<string,mixed> $actor
     */
    public function get(string $publicId, array $actor): ?array
    {
        $item = $this->runtime->findJobByPublicId($publicId);
        if (!$item) {
            return null;
        }
        if (!$this->canReadJob($item, $actor)) {
            return null;
        }

        return $this->normalizeJob($item, true);
    }

    /** @param array<string,mixed> $item @return array<string,mixed> */
    private function normalizeJob(array $item, bool $includeDetail): array
    {
        $payload = $this->decodeJson((string)($item['payload_json'] ?? ''));
        $result = $this->decodeJson((string)($item['result_json'] ?? ''));
        $payloadMeta = $this->extractSafeMeta($payload);
        $resultMeta = $this->extractSafeMeta($result);

        $normalized = [
            'public_id' => (string)($item['public_id'] ?? ''),
            'job_type' => (string)($item['job_type'] ?? ''),
            'action_type' => (string)($item['action_type'] ?? ''),
            'intent_code' => (string)($item['intent_code'] ?? ''),
            'status' => (string)($item['status'] ?? ''),
            'requested_by_user_id' => (int)($item['requested_by_user_id'] ?? 0) ?: null,
            'scope_type' => (string)($item['scope_type'] ?? ''),
            'scope_public_id' => (string)($item['scope_public_id'] ?? ''),
            'error_code' => (string)($item['error_code'] ?? ''),
            'error_message' => $includeDetail ? $this->safeErrorMessage((string)($item['error_message'] ?? '')) : '',
            'created_at' => (string)($item['created_at'] ?? ''),
            'started_at' => (string)($item['started_at'] ?? ''),
            'finished_at' => (string)($item['finished_at'] ?? ''),
            'updated_at' => (string)($item['updated_at'] ?? ''),
            // Never expose raw payload/result from AI job rows in API.
            'payload_fields' => array_keys($payload),
            'result_fields' => array_keys($result),
            'provider_public_id' => (string)($payloadMeta['provider_public_id'] ?? ''),
        ];

        if ($includeDetail) {
            $normalized['payload_meta'] = $payloadMeta;
            $normalized['result_meta'] = $resultMeta;
        }

        return $normalized;
    }

    /** @param array<string,mixed> $job @param array<string,mixed> $actor */
    private function canReadJob(array $job, array $actor): bool
    {
        if ($this->canViewAllJobs($actor)) {
            return true;
        }

        $actorId = (int)($actor['id'] ?? 0);
        if ((int)($job['requested_by_user_id'] ?? 0) === $actorId && $actorId > 0) {
            return true;
        }

        // Service-actor created user-scope jobs remain visible to that user.
        return (string)($job['scope_type'] ?? '') === 'user'
            && (string)($job['scope_public_id'] ?? '') !== ''
            && (string)($job['scope_public_id'] ?? '') === (string)($actor['public_id'] ?? '');
    }

    /** @param array<string,mixed> $actor */
    private function canViewAllJobs(array $actor): bool
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

    /** @return array<string,mixed> */
    private function decodeJson(string $json): array
    {
        if ($json === '') {
            return [];
        }
        $decoded = json_decode($json, true);
        if (!is_array($decoded)) {
            return [];
        }

        // Keep raw payload for internal job control/meta extraction.
        // API responses still expose only whitelisted meta/field names in normalizeJob().
        return $decoded;
    }

    private function safeErrorMessage(string $message): string
    {
        $trimmed = trim($message);
        if ($trimmed === '') {
            return '';
        }
        if (mb_strlen($trimmed) <= 280) {
            return $trimmed;
        }

        return mb_substr($trimmed, 0, 277) . '...';
    }

    /**
     * @param array<string,mixed> $job
     * @param array<string,mixed> $input
     * @param array<string,mixed> $actor
     * @return array{can_run:bool,checks:array<int,array<string,mixed>>,warnings:array<int,string>,errors:array<int,string>}
     */
    private function buildRunChecks(array $job, array $input, array $actor, bool $withProvider, string $scopePublicId): array
    {
        $checks = [];
        $warnings = [];
        $errors = [];

        $aiEnabled = $this->featureFlags->isEnabled('ai.enabled', false);
        $checks[] = ['name' => 'ai.enabled', 'ok' => $aiEnabled];
        if (!$aiEnabled) {
            $errors[] = 'AI_DISABLED';
        }

        $flagCode = trim((string)($job['feature_flag'] ?? ''));
        if ($flagCode !== '') {
            $flagEnabled = $this->featureFlags->isEnabled($flagCode, false);
            $checks[] = ['name' => $flagCode, 'ok' => $flagEnabled];
            if (!$flagEnabled) {
                $errors[] = 'AI_FEATURE_DISABLED';
            }
        }

        $schedule = is_array($job['schedule'] ?? null) ? (array)$job['schedule'] : [];
        $scheduleConfigured = $schedule !== [];
        $checks[] = ['name' => 'schedule_configured', 'ok' => $scheduleConfigured];
        if (!$scheduleConfigured) {
            $warnings[] = 'Schedule configuration is missing.';
        }

        $limits = is_array($job['limits'] ?? null) ? (array)$job['limits'] : [];
        $maxEntities = max(0, (int)($limits['max_entities_per_run'] ?? 0));
        $maxTokens = max(0, (int)($limits['max_tokens_per_run'] ?? 0));
        $limitsConfigured = $maxEntities > 0 || $maxTokens > 0;
        $checks[] = ['name' => 'limits_configured', 'ok' => $limitsConfigured];
        if (!$limitsConfigured) {
            $warnings[] = 'Entity/token limits are not configured.';
        }

        $retry = is_array($job['retry'] ?? null) ? (array)$job['retry'] : [];
        $attempts = max(0, (int)($retry['attempts'] ?? 0));
        $retryConfigured = $attempts > 0;
        $checks[] = ['name' => 'retry_policy_configured', 'ok' => $retryConfigured];
        if (!$retryConfigured) {
            $warnings[] = 'Retry policy has zero attempts.';
        }

        $scopeType = trim((string)($job['scope_type'] ?? ''));
        if ($scopeType === 'user') {
            $timezone = $this->resolveTimezoneForScope($scopePublicId, $input, $actor);
            $timezoneOk = true;
            $checks[] = ['name' => 'timezone_available', 'ok' => $timezoneOk];
            if ($timezone === '') {
                $warnings[] = 'Timezone is not configured for selected user scope; default UTC will be used.';
            }

            $workHours = $this->resolveWorkHoursForScope($scopePublicId);
            $workHoursOk = true;
            $checks[] = ['name' => 'work_hours_available', 'ok' => $workHoursOk];
            if ((string)($workHours['start'] ?? '') === '' || (string)($workHours['end'] ?? '') === '') {
                $warnings[] = 'Work hours are not configured; default 09:00-18:00 will be used.';
            }

            $dailyPlanEnabled = $this->resolveDailyPlanEnabledForScope($scopePublicId);
            $checks[] = ['name' => 'daily_plan_enabled', 'ok' => $dailyPlanEnabled];
            if ((string)($job['action_type'] ?? '') === 'my_day_plan' && !$dailyPlanEnabled) {
                $errors[] = 'AI_DAILY_PLAN_OPTED_OUT';
            }
        }

        if ((bool)($job['require_provider'] ?? false)) {
            $provider = $this->providers->findDefaultActive() ?? $this->providers->findAnyActive();
            $providerConfigured = is_array($provider) && $this->providers->hasSecret((int)($provider['id'] ?? 0));
            $checks[] = ['name' => 'provider_configured', 'ok' => $providerConfigured];
            if (!$providerConfigured) {
                $errors[] = 'AI_PROVIDER_NOT_CONFIGURED';
            }
            if (!$withProvider) {
                $warnings[] = 'Dry-run mode does not execute provider calls by default.';
            }
        }

        return [
            'can_run' => $errors === [],
            'checks' => $checks,
            'warnings' => $warnings,
            'errors' => $errors,
        ];
    }

    /**
     * @param array<string,mixed> $input
     * @return array<string,mixed>
     */
    private function sanitizeInput(array $input): array
    {
        $safe = [];
        foreach ($input as $key => $value) {
            if (!is_string($key)) {
                continue;
            }
            if (in_array($key, ['scope_public_id', 'scope_type', 'timezone', 'date', 'with_provider', 'force', 'run_as_service'], true)) {
                $safe[$key] = is_scalar($value) || $value === null ? $value : (string)json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            }
        }
        return $safe;
    }

    /**
     * @param array<string,mixed> $input
     * @param array<string,mixed> $actor
     */
    private function resolveScopePublicId(string $scopeType, array $input, array $actor): string
    {
        $inputScope = trim((string)($input['scope_public_id'] ?? ''));
        if ($scopeType === 'user') {
            if ($inputScope !== '') {
                return $inputScope;
            }
            return (string)($actor['public_id'] ?? '');
        }

        return $inputScope !== '' ? $inputScope : 'global';
    }

    /**
     * @param array<string,mixed> $input
     */
    private function buildIdempotencyHash(string $jobCode, string $scopeType, string $scopePublicId, array $input): string
    {
        $date = $this->resolveExecutionDate($jobCode, $input);
        $timezone = trim((string)($input['timezone'] ?? ''));
        $force = (bool)($input['force'] ?? false);
        $raw = implode('|', ['ai-cron', $jobCode, $scopeType, $scopePublicId, $date, $timezone, $force ? '1' : '0']);
        return hash('sha256', $raw);
    }

    /**
     * @param array<string,mixed> $input
     */
    private function resolveExecutionDate(string $jobCode, array $input): string
    {
        $raw = trim((string)($input['date'] ?? ''));
        if ($raw !== '' && preg_match('/^\\d{4}-\\d{2}-\\d{2}$/', $raw) === 1) {
            return $raw;
        }

        $job = self::JOB_CATALOG[$jobCode] ?? [];
        $window = (string)($job['idempotency_window'] ?? 'day');
        if ($window === 'week') {
            return gmdate('o-\\WW');
        }

        return gmdate('Y-m-d');
    }

    /**
     * @param array<string,mixed> $input
     * @param array<string,mixed> $actor
     */
    private function resolveTimezoneForScope(string $scopePublicId, array $input, array $actor): string
    {
        $inputTimezone = trim((string)($input['timezone'] ?? ''));
        if ($this->isValidTimezone($inputTimezone)) {
            return $inputTimezone;
        }

        $actorPublicId = (string)($actor['public_id'] ?? '');
        if ($scopePublicId !== '' && $scopePublicId === $actorPublicId) {
            $actorTimezone = trim((string)($actor['timezone'] ?? ''));
            if ($this->isValidTimezone($actorTimezone)) {
                return $actorTimezone;
            }
        }

        $row = $this->settings->get('user:' . $scopePublicId, 'timezone');
        $storedTimezone = trim((string)($row['value'] ?? ''));
        return $this->isValidTimezone($storedTimezone) ? $storedTimezone : '';
    }

    /** @return array{start:string,end:string} */
    private function resolveWorkHoursForScope(string $scopePublicId): array
    {
        if ($scopePublicId === '') {
            return ['start' => '', 'end' => ''];
        }

        $scope = 'ai_user:' . $scopePublicId;
        $startRow = $this->settings->get($scope, 'work_hours_start');
        $endRow = $this->settings->get($scope, 'work_hours_end');
        $start = trim((string)($startRow['value'] ?? ''));
        $end = trim((string)($endRow['value'] ?? ''));

        return [
            'start' => preg_match('/^(2[0-3]|[01][0-9]):[0-5][0-9]$/', $start) === 1 ? $start : '',
            'end' => preg_match('/^(2[0-3]|[01][0-9]):[0-5][0-9]$/', $end) === 1 ? $end : '',
        ];
    }

    private function resolveDailyPlanEnabledForScope(string $scopePublicId): bool
    {
        if ($scopePublicId === '') {
            return true;
        }

        $scope = 'ai_user:' . $scopePublicId;
        $row = $this->settings->get($scope, 'daily_plan_enabled');
        if (!array_key_exists('value', $row)) {
            return true;
        }

        return (bool)$row['value'];
    }

    /** @return array{daily_plan_enabled:bool,preferred_response_length:string,focus_block_minutes:int} */
    private function resolveAiPreferencesForScope(string $scopePublicId): array
    {
        if ($scopePublicId === '') {
            return [
                'daily_plan_enabled' => true,
                'preferred_response_length' => 'short',
                'focus_block_minutes' => 90,
            ];
        }

        $scope = 'ai_user:' . $scopePublicId;
        $dailyPlanEnabledRow = $this->settings->get($scope, 'daily_plan_enabled');
        $preferredLengthRow = $this->settings->get($scope, 'preferred_response_length');
        $focusBlockRow = $this->settings->get($scope, 'focus_block_minutes');

        $preferred = trim((string)($preferredLengthRow['value'] ?? 'short'));
        if (!in_array($preferred, ['short', 'medium', 'long'], true)) {
            $preferred = 'short';
        }

        return [
            'daily_plan_enabled' => array_key_exists('value', $dailyPlanEnabledRow) ? (bool)$dailyPlanEnabledRow['value'] : true,
            'preferred_response_length' => $preferred,
            'focus_block_minutes' => max(15, min(480, (int)($focusBlockRow['value'] ?? 90))),
        ];
    }

    private function isValidTimezone(string $timezone): bool
    {
        $value = trim($timezone);
        if ($value === '') {
            return false;
        }

        try {
            new \DateTimeZone($value);
            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * @param array<string,mixed> $job
     * @param array<string,mixed> $input
     * @param array<string,mixed> $actor
     * @return array<string,mixed>
     */
    private function buildExecutionContext(string $jobCode, array $job, array $input, array $actor, string $scopePublicId): array
    {
        $timezone = 'UTC';
        $workHours = ['start' => '09:00', 'end' => '18:00'];
        $preferences = ['daily_plan_enabled' => true, 'preferred_response_length' => 'short', 'focus_block_minutes' => 90];
        $timezoneSource = 'default';
        $workHoursSource = 'default';
        $preferencesSource = 'default';

        if ((string)($job['scope_type'] ?? '') === 'user') {
            $explicitTimezone = trim((string)($input['timezone'] ?? ''));
            if ($this->isValidTimezone($explicitTimezone)) {
                $timezone = $explicitTimezone;
                $timezoneSource = 'input';
            } else {
                $resolvedTimezone = $this->resolveTimezoneForScope($scopePublicId, $input, $actor);
                if ($this->isValidTimezone($resolvedTimezone)) {
                    $timezone = $resolvedTimezone;
                    $timezoneSource = 'profile';
                }
            }

            $resolvedWorkHours = $this->resolveWorkHoursForScope($scopePublicId);
            if ((string)($resolvedWorkHours['start'] ?? '') !== '' && (string)($resolvedWorkHours['end'] ?? '') !== '') {
                $workHours = $resolvedWorkHours;
                $workHoursSource = 'preferences';
            }

            $preferences = $this->resolveAiPreferencesForScope($scopePublicId);
            $preferencesSource = 'preferences';
        }

        $date = trim((string)($input['date'] ?? ''));
        if ($date === '') {
            $date = $this->resolveExecutionDate($jobCode, $input);
        }
        if ($date === '' || preg_match('/^\\d{4}-\\d{2}-\\d{2}$/', $date) !== 1) {
            $date = gmdate('Y-m-d');
        }

        $scheduledUtc = $this->resolveScheduledUtc(
            is_array($job['schedule'] ?? null) ? (array)$job['schedule'] : [],
            $date,
            $timezone,
            $workHours
        );

        return [
            'date' => $date,
            'timezone' => $timezone,
            'timezone_source' => $timezoneSource,
            'work_hours' => $workHours,
            'work_hours_source' => $workHoursSource,
            'preferences' => $preferences,
            'preferences_source' => $preferencesSource,
            'scheduled_for_utc' => $scheduledUtc,
        ];
    }

    /** @param array<string,mixed> $schedule @param array{start:string,end:string} $workHours */
    private function resolveScheduledUtc(array $schedule, string $date, string $timezone, array $workHours): string
    {
        $localTime = trim((string)($schedule['time'] ?? ''));
        if ($localTime === '' && (string)($schedule['mode'] ?? '') === 'workday_before_start') {
            $minutesBefore = max(0, (int)($schedule['minutes_before'] ?? 30));
            $base = $workHours['start'] . ':00';
            try {
                $dt = new \DateTimeImmutable($date . ' ' . $base, new \DateTimeZone($timezone));
                $dt = $dt->modify('-' . $minutesBefore . ' minutes');
                return $dt->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s');
            } catch (\Throwable) {
                return gmdate('Y-m-d H:i:s');
            }
        }
        if ($localTime === '' && (string)($schedule['mode'] ?? '') === 'workday_end') {
            $minutesAfter = max(0, (int)($schedule['minutes_after'] ?? 15));
            $base = $workHours['end'] . ':00';
            try {
                $dt = new \DateTimeImmutable($date . ' ' . $base, new \DateTimeZone($timezone));
                $dt = $dt->modify('+' . $minutesAfter . ' minutes');
                return $dt->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s');
            } catch (\Throwable) {
                return gmdate('Y-m-d H:i:s');
            }
        }

        if ($localTime === '' && (string)($schedule['mode'] ?? '') === 'hourly') {
            $minute = max(0, min(59, (int)($schedule['minute'] ?? 0)));
            $localTime = sprintf('%02d:%02d', (int)gmdate('H'), $minute);
        }
        if ($localTime === '') {
            $localTime = $workHours['start'];
        }
        if (preg_match('/^(2[0-3]|[01][0-9]):[0-5][0-9]$/', $localTime) !== 1) {
            $localTime = '09:00';
        }

        try {
            $dt = new \DateTimeImmutable($date . ' ' . $localTime . ':00', new \DateTimeZone($timezone));
            return $dt->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s');
        } catch (\Throwable) {
            return gmdate('Y-m-d H:i:s');
        }
    }

    /** @param array<string,mixed> $meta */
    private function logCronUsage(string $actionType, string $intentCode, int $actorUserId, string $status, ?string $errorCode, array $meta): void
    {
        $this->runtime->createUsageLog([
            'user_id' => $actorUserId > 0 ? $actorUserId : null,
            'provider_public_id' => null,
            'action_type' => trim($actionType) !== '' ? trim($actionType) : 'cron_job',
            'intent_code' => trim($intentCode) !== '' ? trim($intentCode) : null,
            'status' => trim($status) !== '' ? trim($status) : 'ok',
            'error_code' => $errorCode !== null && trim($errorCode) !== '' ? trim($errorCode) : null,
            'request_tokens' => null,
            'response_tokens' => null,
            'total_tokens' => null,
            'latency_ms' => null,
            'is_sensitive_context' => 0,
            'request_meta' => json_encode($meta, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'created_at' => gmdate('Y-m-d H:i:s'),
        ]);

        try {
            $this->runtime->cleanupByRetention($this->retention->getPolicies());
        } catch (\Throwable) {
            // Cleanup is best-effort and must not break cron diagnostics.
        }
    }

    /** @param array<string,mixed> $decoded @return array<string,mixed> */
    private function extractSafeMeta(array $decoded): array
    {
        $safe = [];
        foreach ([
            'mode',
            'scope_type',
            'scope_public_id',
            'suggestion_public_id',
            'provider_public_id',
            'intent_code',
            'intent_setting_public_id',
            'prompt_public_id',
            'prompt_version',
            'model',
            'feature_flag',
            'job_code',
        ] as $key) {
            if (array_key_exists($key, $decoded)) {
                $safe[$key] = $decoded[$key];
            }
        }
        return $safe;
    }

    /**
     * @param mixed $value
     * @return mixed
     */
    private function maskSecrets(mixed $value): mixed
    {
        if (is_string($value)) {
            $trimmed = trim($value);
            if ($trimmed === '') {
                return $value;
            }
            $hasSecretLikePayload = (bool)preg_match('/(bearer\s+[A-Za-z0-9\.\-_~\+\/]+=*)|((?:api[_ -]?key|token|secret|password|password_hash|auth_token_hash|backup codes?|webhook secret)\s*[:=]\s*[^\s,;]+)/iu', $trimmed);
            $hasSensitiveHeaders = (bool)preg_match('/\b(?:authorization|cookie)\b\s*[:=]/iu', $trimmed);
            $hasBase64Blob = (bool)preg_match('/^[A-Za-z0-9+\/]{120,}={0,2}$/', $trimmed);
            if ($hasSecretLikePayload || $hasSensitiveHeaders || $hasBase64Blob) {
                return '***';
            }

            return $value;
        }

        if (!is_array($value)) {
            return $value;
        }

        $result = [];
        foreach ($value as $key => $item) {
            $name = is_string($key) ? strtolower($key) : '';
            if ($name !== '' && (
                str_contains($name, 'token')
                || str_contains($name, 'secret')
                || str_contains($name, 'password')
                || str_contains($name, 'authorization')
                || str_contains($name, 'api_key')
                || str_contains($name, 'cookie')
                || str_contains($name, 'auth_token_hash')
                || str_contains($name, 'backup_code')
                || str_contains($name, 'webhook')
                || str_contains($name, 'prompt')
                || str_contains($name, 'context')
            )) {
                $result[$key] = '***';
                continue;
            }
            $result[$key] = $this->maskSecrets($item);
        }

        return $result;
    }
}
