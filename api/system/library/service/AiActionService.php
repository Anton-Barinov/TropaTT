<?php
declare(strict_types=1);

namespace Api\System\Library\Service;

use Api\Model\Ai\AiProviderRepository;
use Api\Model\Ai\AiRuntimeRepository;
use Api\Model\Ai\AiIntentSettingRepository;
use Api\System\Library\Language\LanguageManager;
use Api\System\Library\Language\TranslatableTrait;
use Api\System\Library\Logger\JsonLogger;

final class AiActionService
{
    use TranslatableTrait;

    public function __construct(
        private readonly AiProviderRepository $providers,
        private readonly AiRuntimeRepository $runtime,
        private readonly AiIntentSettingRepository $intentSettings,
        private readonly AiActionTypeService $actionTypes,
        private readonly AiProviderService $aiProviderService,
        private readonly FeatureFlagService $featureFlags,
        private readonly AiRateLimitService $rateLimit,
        private readonly AiCostLimitService $costLimit,
        private readonly JsonLogger $logger,
        ?LanguageManager $lang = null
    ) {
        $this->lang = $lang ?? new LanguageManager(__DIR__ . '/../../language');
    }

    /** @return list<string> */
    public function actionAllowlist(): array
    {
        return $this->actionTypes->allowlist();
    }

    public function execute(string $actionType, array $input, array $actor): array
    {
        if (!$this->isFeatureEnabledForActor('ai.enabled', $actor, false)) {
            return ['ok' => false, 'code' => 'AI_DISABLED'];
        }

        if (!in_array($actionType, $this->actionAllowlist(), true)) {
            return ['ok' => false, 'code' => 'AI_ACTION_TYPE_NOT_ALLOWED'];
        }

        $rate = $this->rateLimit->assertWithinLimits($actionType, $actor);
        if (!(bool)($rate['ok'] ?? false)) {
            return $this->limitFailure($rate, 'AI_RATE_LIMITED');
        }
        $cost = $this->costLimit->assertWithinLimits($actionType, $actor);
        if (!(bool)($cost['ok'] ?? false)) {
            return $this->limitFailure($cost, 'AI_COST_LIMIT_EXCEEDED');
        }

        $intent = $this->intentSettings->findByIntentCode($actionType);
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
        if (!$provider || !$this->providers->hasSecret((int)($provider['id'] ?? 0))) {
            return ['ok' => false, 'code' => 'AI_PROVIDER_NOT_CONFIGURED'];
        }

        $now = gmdate('Y-m-d H:i:s');
        $resolvedModel = trim((string)($intent['model'] ?? '')) !== ''
            ? trim((string)$intent['model'])
            : (string)($provider['default_model'] ?? '');
        $payload = [
            'action_type' => $actionType,
            'input' => $this->sanitizeInput($input),
            'provider_public_id' => (string)($provider['public_id'] ?? ''),
            'model' => $resolvedModel,
        ];

        $systemPrompt = $payload['input']['__sys'] ?? $payload['input']['system_prompt'] ?? 'You are CRM AI assistant. Return short actionable suggestion in Russian.';
        $userPromptRaw = $payload['input']['__usr'] ?? $payload['input']['user_prompt'] ?? ('Action type: ' . $actionType . '. Input: ' . json_encode($payload['input'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        $promptPayload = [
            'intent_code' => $actionType,
            // Keep trusted application instructions in the provider's system
            // role; user content must remain a lower-priority message.
            'system_prompt' => (string)$systemPrompt,
            'user_prompt' => (string)$userPromptRaw,
            'context' => ['scope_type' => trim((string)($input['scope_type'] ?? '')), 'scope_public_id' => trim((string)($input['scope_public_id'] ?? ''))],
            'model' => $resolvedModel,
        ];
        $maxTokens = (int)($intent['max_tokens'] ?? $input['max_tokens'] ?? 0);
        if ($maxTokens > 0) {
            $promptPayload['max_tokens'] = $maxTokens;
        }

        $jobPublicId = $this->runtime->claimInteractiveSlot([
            'job_type' => 'interactive',
            'action_type' => $actionType,
            'intent_code' => $actionType,
            'status' => 'running',
            'requested_by_user_id' => (int)($actor['id'] ?? 0) ?: null,
            'scope_type' => trim((string)($input['scope_type'] ?? '')),
            'scope_public_id' => trim((string)($input['scope_public_id'] ?? '')),
            'idempotency_key_hash' => null,
            'payload_json' => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'result_json' => null,
            'error_code' => null,
            'error_message' => null,
            'created_at' => $now,
            'started_at' => $now,
            'finished_at' => null,
            'updated_at' => $now,
        ], $this->rateLimit->interactiveConcurrencyLimit());
        if ($jobPublicId === null) {
            return ['ok' => false, 'code' => 'AI_BUSY', 'retry_after' => 5];
        }

        try {
            $completion = $this->aiProviderService->completeText((string)($provider['public_id'] ?? ''), $promptPayload);
        } catch (\Throwable $e) {
            $this->runtime->updateJobByPublicId($jobPublicId, [
                'status' => 'failed',
                'error_code' => 'AI_PROVIDER_UNAVAILABLE',
                'error_message' => $e->getMessage(),
                'finished_at' => gmdate('Y-m-d H:i:s'),
                'updated_at' => gmdate('Y-m-d H:i:s'),
            ]);
            return ['ok' => false, 'code' => 'AI_PROVIDER_UNAVAILABLE'];
        }
        $completionOk = (bool)($completion['ok'] ?? false) && trim((string)($completion['text'] ?? '')) !== '';
        $rawText = $completionOk ? trim((string)$completion['text']) : '';
        ai_diag_log("[AI_COMPLETION][{$actionType}] ok=".($completion["ok"]?"1":"0")." text_len=".strlen($rawText)." code=".($completion["code"]??"null")." provider=".($provider["provider_code"]??"?"));
        $mode = $completionOk ? 'llm' : 'safe_mock';
        $errorCode = $completionOk ? null : (string)($completion['code'] ?? 'AI_PROVIDER_UNAVAILABLE');
        $summary = $rawText !== '' ? $rawText : $this->t('ai/messages.fallback_error');

        $this->runtime->updateJobByPublicId($jobPublicId, [
            'status' => 'completed',
            'result_json' => json_encode([
                'mode' => $mode,
                'error_code' => $errorCode,
                'http_status' => (int)($completion['http_status'] ?? 0),
                'suggestion' => [
                    'summary' => $summary,
                    'questions' => [],
                    'proposed_actions' => [],
                ],
                'provider_public_id' => (string)($provider['public_id'] ?? ''),
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'error_code' => $errorCode,
            'error_message' => null,
            'finished_at' => $now,
            'updated_at' => $now,
        ]);

        $this->runtime->createUsageLog([
            'user_id' => (int)($actor['id'] ?? 0) ?: null,
            'provider_public_id' => (string)($provider['public_id'] ?? ''),
            'action_type' => $actionType,
            'intent_code' => $actionType,
            'status' => 'completed',
            'error_code' => $errorCode,
            'request_tokens' => (int)($completion['request_tokens'] ?? 0),
            'response_tokens' => (int)($completion['response_tokens'] ?? 0),
            'total_tokens' => (int)($completion['total_tokens'] ?? 0),
            'latency_ms' => (int)($completion['latency_ms'] ?? 0),
            'is_sensitive_context' => 0,
            'request_meta' => json_encode([
                'mode' => $mode,
                'scope_type' => trim((string)($input['scope_type'] ?? '')),
                'scope_public_id' => trim((string)($input['scope_public_id'] ?? '')),
                'intent_setting_public_id' => (string)($intent['public_id'] ?? ''),
                'resolved_model' => $resolvedModel,
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'created_at' => $now,
        ]);

        $this->logger->audit([
            'action' => 'ai_action_executed',
            'actor_public_id' => $actor['public_id'] ?? null,
            'entity_type' => 'ai_job',
            'entity_public_id' => $jobPublicId,
            'action_type' => $actionType,
            'intent_code' => $actionType,
            'provider_public_id' => (string)($provider['public_id'] ?? ''),
            'provider_code' => (string)($provider['provider_code'] ?? ''),
        ]);

        return [
            'ok' => true,
            'result' => [
                'job_public_id' => $jobPublicId,
                'action_type' => $actionType,
                'provider_public_id' => (string)($provider['public_id'] ?? ''),
                'mode' => $mode,
                'error_code' => $errorCode,
                'http_status' => (int)($completion['http_status'] ?? 0),
                'preview' => [
                    'summary' => $summary,
                    'changes' => [],
                    'requires_confirmation' => true,
                ],
            ],
        ];
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
            '__sys',
            '__usr',
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

    /** @param array<string,mixed> $actor */
    private function hasActorPermission(array $actor, string $permissionCode): bool
    {
        if ($permissionCode === '') {
            return true;
        }
        if ((bool)($actor['is_root'] ?? false)) {
            return true;
        }
        $codes = is_array($actor['permission_codes'] ?? null) ? (array)$actor['permission_codes'] : [];
        if (in_array('*', $codes, true)) {
            return true;
        }

        return in_array($permissionCode, $codes, true);
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

    /** @param array<string,mixed> $actor */
    private function isFeatureEnabledForActor(string $flagCode, array $actor, bool $default): bool
    {
        if ((bool)($actor['is_root'] ?? false)) {
            return true;
        }

        return $this->featureFlags->isEnabled($flagCode, $default);
    }
}
