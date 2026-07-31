<?php
declare(strict_types=1);

namespace Api\System\Library\Service;

use Api\System\Library\Config;
use Api\System\Library\Logger\JsonLogger;

final class AiRetentionPolicyService
{
    private const SCOPE = 'ai_retention';

    /** @param array<string,int> $defaults */
    public function __construct(
        private readonly SettingService $settings,
        private readonly Config $config,
        private readonly JsonLogger $logger
    ) {
    }

    public function getPolicies(): array
    {
        $defaults = $this->defaults();
        $resolved = [];
        foreach ($defaults as $name => $value) {
            $item = $this->settings->get(self::SCOPE, $name);
            $resolved[$name] = isset($item['value']) ? max(1, (int)$item['value']) : $value;
        }

        return $resolved;
    }

    /** @param array<string,mixed>|null $actor */
    public function updatePolicy(string $code, int $days, ?array $actor = null): array
    {
        $defaults = $this->defaults();
        if (!array_key_exists($code, $defaults)) {
            return ['ok' => false, 'code' => 'AI_RETENTION_POLICY_NOT_FOUND'];
        }

        if ($days < 1 || $days > 3650) {
            return ['ok' => false, 'code' => 'AI_RETENTION_POLICY_INVALID_VALUE'];
        }

        $before = $this->settings->get(self::SCOPE, $code);
        $beforeDays = isset($before['value']) ? max(1, (int)$before['value']) : max(1, (int)$defaults[$code]);

        $item = $this->settings->set(self::SCOPE, $code, $days);
        $resolvedDays = max(1, (int)($item['value'] ?? $days));

        $this->logger->audit([
            'action' => 'ai_retention_policy_updated',
            'actor_public_id' => is_array($actor) ? (string)($actor['public_id'] ?? '') : '',
            'entity_type' => 'ai_retention_policy',
            'entity_public_id' => $code,
            'policy_code' => $code,
            'before_days' => $beforeDays,
            'after_days' => $resolvedDays,
        ]);

        return ['ok' => true, 'policy' => [
            'code' => $code,
            'days' => $resolvedDays,
        ]];
    }

    /** @return array<string,int> */
    private function defaults(): array
    {
        $raw = (array)$this->config->get('ai.retention', []);
        $defaults = [
            'suggestions_ttl_days' => max(1, (int)($raw['suggestions_ttl_days'] ?? 30)),
            'jobs_ttl_days' => max(1, (int)($raw['jobs_ttl_days'] ?? 30)),
            'usage_logs_ttl_days' => max(1, (int)($raw['usage_logs_ttl_days'] ?? 90)),
            'prompts_ttl_days' => max(1, (int)($raw['prompts_ttl_days'] ?? 30)),
        ];

        return $defaults;
    }
}
