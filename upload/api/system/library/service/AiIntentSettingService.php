<?php
declare(strict_types=1);

namespace Api\System\Library\Service;

use Api\Model\Ai\AiIntentSettingRepository;
use Api\Model\Ai\AiJsonSchemaRepository;
use Api\Model\Ai\AiPromptTemplateRepository;
use Api\Model\Ai\AiProviderRepository;
use Api\System\Library\Config;
use Api\System\Library\Language\LanguageManager;
use Api\System\Library\Language\TranslatableTrait;
use Api\System\Library\Logger\JsonLogger;

final class AiIntentSettingService
{
    use TranslatableTrait;

    public function __construct(
        private readonly AiIntentSettingRepository $repo,
        private readonly AiJsonSchemaRepository $schemas,
        private readonly AiPromptTemplateRepository $prompts,
        private readonly AiProviderRepository $providers,
        private readonly SettingService $settings,
        private readonly JsonLogger $logger,
        private readonly Config $config,
        ?LanguageManager $lang = null
    ) {
        $this->lang = $lang ?? new LanguageManager(__DIR__ . '/../../language');
    }

    /** Once per service instance (== once per request): ensureBaseline() is idempotent. */
    private bool $baselineEnsured = false;

    public function list(array $filters): array
    {
        $this->ensureBaseline();
        $rows = $this->repo->list($filters);

        return [
            'items' => array_map(fn(array $row): array => $this->normalize($row), $rows),
            'meta' => [
                'total' => count($rows),
            ],
        ];
    }

    public function update(string $intentCode, array $input, array $actor): array
    {
        $this->ensureBaseline();
        $intentCode = trim($intentCode);
        if ($intentCode === '') {
            return ['ok' => false, 'code' => 'AI_INTENT_REQUIRED'];
        }
        if (!in_array($intentCode, $this->allowedIntents(), true)) {
            return ['ok' => false, 'code' => 'AI_INTENT_NOT_ALLOWED'];
        }

        $current = $this->repo->findByIntentCode($intentCode);
        if (!$current) {
            return ['ok' => false, 'code' => 'AI_INTENT_NOT_FOUND'];
        }

        $set = [];
        if (array_key_exists('provider_public_id', $input)) {
            $providerPublicId = trim((string)$input['provider_public_id']);
            if ($providerPublicId === '') {
                $set['provider_id'] = null;
            } else {
                $provider = $this->providers->findByPublicId($providerPublicId);
                if (!$provider) {
                    return ['ok' => false, 'code' => 'AI_PROVIDER_NOT_FOUND'];
                }
                $set['provider_id'] = (int)($provider['id'] ?? 0) ?: null;
            }
        }

        foreach (['model', 'feature_flag', 'required_permission', 'temperature'] as $field) {
            if (array_key_exists($field, $input)) {
                $set[$field] = trim((string)$input[$field]);
            }
        }

        if (array_key_exists('max_tokens', $input)) {
            $set['max_tokens'] = max(1, (int)$input['max_tokens']);
        }
        if (array_key_exists('is_enabled', $input)) {
            $set['is_enabled'] = $this->toBool($input['is_enabled']) ? 1 : 0;
        }
        if (array_key_exists('allow_sensitive_context', $input)) {
            $set['allow_sensitive_context'] = $this->toBool($input['allow_sensitive_context']) ? 1 : 0;
        }
        if (array_key_exists('intent_payload', $input) && is_array($input['intent_payload'])) {
            $set['intent_payload'] = $this->encodeJson($input['intent_payload']);
        }

        if ($set === []) {
            return ['ok' => false, 'code' => 'AI_INTENT_NO_CHANGES'];
        }

        $actorId = (int)($actor['id'] ?? 0);
        $set['updated_at'] = gmdate('Y-m-d H:i:s');
        $set['updated_by_user_id'] = $actorId > 0 ? $actorId : null;
        $ok = $this->repo->updateByIntentCode($intentCode, $set);
        if (!$ok) {
            return ['ok' => false, 'code' => 'AI_INTENT_UPDATE_FAILED'];
        }

        $updated = $this->repo->findByIntentCode($intentCode);
        $this->logger->audit([
            'action' => 'ai_intent_settings_updated',
            'actor_public_id' => $actor['public_id'] ?? null,
            'entity_type' => 'ai_intent_settings',
            'entity_public_id' => (string)($updated['public_id'] ?? ''),
            'intent_code' => $intentCode,
            'changes' => array_keys($set),
        ]);

        return ['ok' => true, 'item' => $updated ? $this->normalize($updated) : null];
    }

    private function ensureBaseline(): void
    {
        if ($this->baselineEnsured) {
            return;
        }
        $this->baselineEnsured = true;

        $now = gmdate('Y-m-d H:i:s');
        foreach ($this->allowedIntents() as $intent) {
            $existing = $this->repo->findByIntentCode($intent);
            if ($existing) {
                $compatSet = [];
                $featureFlag = trim((string)($existing['feature_flag'] ?? ''));
                if ($featureFlag === '') {
                    $compatSet['feature_flag'] = $this->defaultFeatureFlagByIntent($intent);
                }
                if (in_array($intent, ['admin_log_review', 'webhook_health_review', 'workflow_rule_audit'], true)) {
                    $featureFlag = trim((string)($compatSet['feature_flag'] ?? $existing['feature_flag'] ?? ''));
                    if ($featureFlag !== 'ai.enabled') {
                        $compatSet['feature_flag'] = 'ai.enabled';
                    }
                    $requiredPermission = trim((string)($existing['required_permission'] ?? ''));
                    if ($requiredPermission === '') {
                        $compatSet['required_permission'] = 'ai.admin';
                    }
                }
                if ($compatSet !== []) {
                    $compatSet['updated_at'] = $now;
                    $this->repo->updateByIntentCode($intent, $compatSet);
                }

                $this->ensureDefaultActiveSchema($intent, $now);
                $this->ensureDefaultActivePrompt($intent, $now);
                continue;
            }

            try {
                $this->repo->create([
                    'intent_code' => $intent,
                    'provider_id' => null,
                    'model' => '',
                    'feature_flag' => $this->defaultFeatureFlagByIntent($intent),
                    'required_permission' => $this->defaultRequiredPermissionByIntent($intent),
                    'allow_sensitive_context' => 0,
                    'max_tokens' => 2000,
                    'temperature' => '0.2',
                    'is_enabled' => 1,
                    'intent_payload' => '{}',
                    'created_by_user_id' => null,
                    'updated_by_user_id' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            } catch (\Throwable $e) {
                // Duplicate intent_code under concurrency: another request
                // already created this baseline row (unique index holds).
                // Any other error is a real failure and must propagate.
                $code = (string)$e->getCode();
                if (!in_array($code, ['23000', '23505', '1062'], true)) {
                    throw $e;
                }
                error_log('[AiIntentSettingService::ensureBaseline] create raced for intent "' . $intent . '": ' . $e->getMessage());
            }

            $this->ensureDefaultActiveSchema($intent, $now);
            $this->ensureDefaultActivePrompt($intent, $now);
        }
    }

    private function ensureDefaultActiveSchema(string $intentCode, string $now): void
    {
        $active = $this->schemas->findActiveForIntent($intentCode);
        if ($active) {
            return;
        }

        $schema = json_encode(['type' => 'object'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (!is_string($schema)) {
            $schema = '{}';
        }

        $this->schemas->create([
            'intent_code' => $intentCode,
            'schema_version' => 'baseline-v1',
            'schema_json' => $schema,
            'is_active' => 1,
            'created_by_user_id' => null,
            'updated_by_user_id' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    private function ensureDefaultActivePrompt(string $intentCode, string $now): void
    {
        $active = $this->prompts->findActiveForIntentLocale($intentCode, 'ru-ru');
        if ($active) {
            return;
        }

        $this->prompts->create([
            'intent_code' => $intentCode,
            'locale' => 'ru-ru',
            'version' => 1,
            'template_text' => str_replace('{intent}', $intentCode, $this->t('ai/messages.default_prompt_template', '{intent}')),
            'is_active' => 1,
            'created_by_user_id' => null,
            'updated_by_user_id' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    /** @return list<string> */
    private function allowedIntents(): array
    {
        $setting = $this->settings->get('ai_actions', 'allowlist');
        $fromSettings = is_array($setting['value'] ?? null) ? (array)$setting['value'] : [];
        $fromConfig = (array)$this->config->get('ai.actions.allowlist', []);
        $fromIntentSettings = (array)$this->config->get('ai.intent_settings.allowlist', []);
        $list = array_merge($fromConfig, $fromSettings, $fromIntentSettings);

        $normalized = [];
        foreach ($list as $item) {
            $value = trim((string)$item);
            if ($value !== '') {
                $normalized[] = $value;
            }
        }

        return array_values(array_unique($normalized));
    }

    private function defaultFeatureFlagByIntent(string $intent): string
    {
        if ($intent === 'daily_work_plan') {
            return 'ai.cron.daily_work_plan';
        }
        if ($intent === 'security_log_review') {
            return 'ai.cron.security_log_review';
        }
        if ($intent === 'semantic_search') {
            return 'ai.search';
        }

        $prefix = '';
        if (str_starts_with($intent, 'task_')) {
            $prefix = 'ai.task';
        } elseif (str_starts_with($intent, 'project_')) {
            $prefix = 'ai.project';
        } elseif (str_starts_with($intent, 'calendar_')) {
            $prefix = 'ai.calendar';
        }

        return $prefix !== '' ? $prefix : 'ai.enabled';
    }

    private function defaultRequiredPermissionByIntent(string $intent): string
    {
        if ($intent === 'security_log_review') {
            return 'ai.view_audit';
        }

        if (str_starts_with($intent, 'admin_') || str_starts_with($intent, 'webhook_') || str_starts_with($intent, 'workflow_')) {
            return 'ai.admin';
        }

        return 'ai.use';
    }

    private function toBool(mixed $value): bool
    {
        return in_array($value, [true, 1, '1', 'true', 'yes', 'on'], true);
    }

    private function encodeJson(array $value): string
    {
        $json = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        return is_string($json) ? $json : '{}';
    }

    private function normalize(array $row): array
    {
        $payloadRaw = (string)($row['intent_payload'] ?? '{}');
        $payload = json_decode($payloadRaw, true);
        if (!is_array($payload)) {
            $payload = [];
        }

        $providerPublicId = null;
        $providerId = (int)($row['provider_id'] ?? 0);
        if ($providerId > 0) {
            $provider = $this->providers->findById($providerId);
            if ($provider) {
                $providerPublicId = (string)($provider['public_id'] ?? '');
            }
        }

        return [
            'public_id' => (string)($row['public_id'] ?? ''),
            'intent_code' => (string)($row['intent_code'] ?? ''),
            'provider_public_id' => $providerPublicId,
            'model' => (string)($row['model'] ?? ''),
            'feature_flag' => (string)($row['feature_flag'] ?? ''),
            'required_permission' => (string)($row['required_permission'] ?? ''),
            'allow_sensitive_context' => (int)($row['allow_sensitive_context'] ?? 0) === 1,
            'max_tokens' => (int)($row['max_tokens'] ?? 0),
            'temperature' => (string)($row['temperature'] ?? ''),
            'is_enabled' => (int)($row['is_enabled'] ?? 0) === 1,
            'intent_payload' => $payload,
            'created_at' => (string)($row['created_at'] ?? ''),
            'updated_at' => (string)($row['updated_at'] ?? ''),
        ];
    }
}
