<?php
declare(strict_types=1);

namespace Api\System\Library\Service;

use Api\Model\Ai\AiProviderRepository;

final class AiSettingsService
{
    public function __construct(
        private readonly SettingService $settings,
        private readonly FeatureFlagService $featureFlags,
        private readonly AiProviderRepository $providers
    ) {
    }

    /** @return array<string,mixed> */
    public function getSettings(): array
    {
        $result = [];
        foreach ($this->defaultSettings() as $key => $defaultValue) {
            $row = $this->settings->get('ai_settings', $key);
            $result[$key] = $row['value'] ?? $defaultValue;
        }

        $provider = $this->providers->findDefaultActive() ?? $this->providers->findAnyActive();
        $providerConfigured = false;
        $providerPublicId = '';
        if (is_array($provider)) {
            $providerPublicId = (string)($provider['public_id'] ?? '');
            $providerConfigured = $providerPublicId !== '' && $this->providers->hasSecret((int)($provider['id'] ?? 0));
        }

        return [
            'settings' => $result,
            'feature_flags' => [
                'ai.enabled' => $this->featureFlags->isEnabled('ai.enabled', false),
            ],
            'provider' => [
                'is_configured' => $providerConfigured,
                'default_provider_public_id' => $providerPublicId,
            ],
        ];
    }

    /**
     * @param array<string,mixed> $input
     * @return array{ok:bool,code?:string,data?:array<string,mixed>}
     */
    public function updateSettings(array $input): array
    {
        $allowed = array_keys($this->defaultSettings());
        $changed = false;
        foreach ($allowed as $key) {
            if (!array_key_exists($key, $input)) {
                continue;
            }
            $changed = true;
            $value = $this->normalizeValue($key, $input[$key]);
            $this->settings->set('ai_settings', $key, $value);
        }

        if (!$changed) {
            return ['ok' => false, 'code' => 'AI_SETTINGS_NO_CHANGES'];
        }

        return ['ok' => true, 'data' => $this->getSettings()];
    }

    /** @return array<string,mixed> */
    private function defaultSettings(): array
    {
        return [
            'default_provider_public_id' => '',
            'default_model' => '',
            'runtime_mode' => 'staged',
            'max_input_chars' => 4000,
            'request_timeout_ms' => 30000,
            'strict_json_mode' => true,
            'audit_redaction_enabled' => true,
            'allow_personal_recommendations_opt_out' => true,
        ];
    }

    /** @param mixed $value */
    private function normalizeValue(string $key, mixed $value): mixed
    {
        return match ($key) {
            'max_input_chars' => max(100, min(200000, (int)$value)),
            'request_timeout_ms' => max(1000, min(120000, (int)$value)),
            'strict_json_mode', 'audit_redaction_enabled', 'allow_personal_recommendations_opt_out' => (bool)$value,
            'runtime_mode' => $this->normalizeRuntimeMode($value),
            default => trim((string)$value),
        };
    }

    private function normalizeRuntimeMode(mixed $value): string
    {
        $mode = strtolower(trim((string)$value));
        return in_array($mode, ['mock', 'staged', 'real'], true) ? $mode : 'staged';
    }
}
