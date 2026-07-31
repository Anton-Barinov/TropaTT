<?php
declare(strict_types=1);

namespace Api\System\Library\Service;

use Api\System\Library\Logger\JsonLogger;

class AiUserPreferenceService
{
    public function __construct(
        private readonly SettingService $settings,
        private readonly JsonLogger $logger
    ) {
    }

    /** @param array<string,mixed> $actor
     *  @return array<string,mixed>
     */
    public function getPreferences(array $actor): array
    {
        $scope = $this->scope($actor);
        $preferences = [];
        foreach ($this->defaults() as $key => $defaultValue) {
            $row = $this->settings->get($scope, $key);
            $preferences[$key] = $row['value'] ?? $defaultValue;
        }

        return $preferences;
    }

    /**
     * @param array<string,mixed> $actor
     * @param array<string,mixed> $input
     * @return array{ok:bool,code?:string,preferences?:array<string,mixed>}
     */
    public function updatePreferences(array $actor, array $input): array
    {
        $allowedKeys = array_keys($this->defaults());
        $source = isset($input['preferences']) && is_array($input['preferences']) ? (array)$input['preferences'] : $input;

        if (array_key_exists('personal_recommendations_enabled', $source)) {
            $next = (bool)$source['personal_recommendations_enabled'];
            if ($next === false && !$this->isPersonalRecommendationsOptOutAllowed()) {
                return ['ok' => false, 'code' => 'AI_PREFERENCES_OPT_OUT_FORBIDDEN'];
            }
        }

        $changed = false;
        $scope = $this->scope($actor);
        foreach ($allowedKeys as $key) {
            if (!array_key_exists($key, $source)) {
                continue;
            }

            $value = $this->normalizeValue($key, $source[$key]);
            $this->settings->set($scope, $key, $value);
            $changed = true;
        }

        if (!$changed) {
            return ['ok' => false, 'code' => 'AI_PREFERENCES_NO_CHANGES'];
        }

        $this->logger->audit([
            'action' => 'ai_preferences_updated',
            'actor_public_id' => $actor['public_id'] ?? null,
            'entity_type' => 'user',
            'entity_public_id' => $actor['public_id'] ?? null,
        ]);

        return [
            'ok' => true,
            'preferences' => $this->getPreferences($actor),
        ];
    }

    /** @return array<string,mixed> */
    private function defaults(): array
    {
        return [
            'personal_recommendations_enabled' => true,
            'daily_plan_enabled' => true,
            'preferred_response_length' => 'short',
            'work_hours_start' => '09:00',
            'work_hours_end' => '18:00',
            'focus_block_minutes' => 90,
        ];
    }

    /** @param mixed $value */
    private function normalizeValue(string $key, mixed $value): mixed
    {
        return match ($key) {
            'personal_recommendations_enabled', 'daily_plan_enabled' => (bool)$value,
            'preferred_response_length' => $this->normalizeResponseLength($value),
            'work_hours_start' => $this->normalizeTime($value, '09:00'),
            'work_hours_end' => $this->normalizeTime($value, '18:00'),
            'focus_block_minutes' => max(15, min(480, (int)$value)),
            default => $value,
        };
    }

    /** @param mixed $value */
    private function normalizeResponseLength(mixed $value): string
    {
        $normalized = mb_strtolower(trim((string)$value));
        if (in_array($normalized, ['short', 'medium', 'long'], true)) {
            return $normalized;
        }

        return 'short';
    }

    /** @param mixed $value */
    private function normalizeTime(mixed $value, string $fallback): string
    {
        $raw = trim((string)$value);
        if (preg_match('/^(2[0-3]|[01][0-9]):[0-5][0-9]$/', $raw) === 1) {
            return $raw;
        }

        return $fallback;
    }

    /** @param array<string,mixed> $actor */
    private function scope(array $actor): string
    {
        return 'ai_user:' . (string)($actor['public_id'] ?? '');
    }

    private function isPersonalRecommendationsOptOutAllowed(): bool
    {
        $row = $this->settings->get('ai_settings', 'allow_personal_recommendations_opt_out');
        if (!array_key_exists('value', $row)) {
            return true;
        }

        return (bool)$row['value'];
    }
}
