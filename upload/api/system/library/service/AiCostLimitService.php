<?php
declare(strict_types=1);

namespace Api\System\Library\Service;

use Api\Model\Ai\AiRuntimeRepository;

final class AiCostLimitService
{
    public function __construct(
        private readonly AiRuntimeRepository $runtime,
        private readonly SettingService $settings
    ) {
    }

    /**
     * @param array<string,mixed> $actor
     * @return array{ok:bool,code?:string}
     */
    public function assertWithinLimits(string $actionType, array $actor): array
    {
        $userId = (int)($actor['id'] ?? 0);
        if ($userId <= 0) {
            return ['ok' => true];
        }
        if ($this->isAiAdminActor($actor)) {
            return ['ok' => true];
        }

        $maxTokensPerDay = $this->limitInt('max_tokens_per_day', 100000, 100, 100000000);
        $maxCostPerDayUsd = $this->limitFloat('max_cost_per_day_usd', 20.0, 0.01, 100000.0);
        $costPer1kTokensUsd = $this->limitFloat('cost_per_1k_tokens_usd', 0.02, 0.0001, 1000.0);

        $dayWindow = gmdate('Y-m-d H:i:s', time() - 86400);
        $aggregate = $this->runtime->usageAggregateSince($dayWindow, $userId, null);
        $tokens = (int)($aggregate['total_tokens'] ?? 0);
        if ($tokens >= $maxTokensPerDay) {
            return ['ok' => false, 'code' => 'AI_COST_LIMIT_EXCEEDED'];
        }

        $estimatedCost = ($tokens / 1000.0) * $costPer1kTokensUsd;
        if ($estimatedCost >= $maxCostPerDayUsd) {
            return ['ok' => false, 'code' => 'AI_COST_LIMIT_EXCEEDED'];
        }

        if ($actionType === '') {
            return ['ok' => true];
        }

        return ['ok' => true];
    }

    /**
     * @param array<string,mixed> $actor
     */
    private function isAiAdminActor(array $actor): bool
    {
        if ((bool)($actor['is_root'] ?? false)) {
            return true;
        }

        $direct = $actor['permissions'] ?? null;
        if (is_array($direct)) {
            foreach ($direct as $permission) {
                if (trim((string)$permission) === 'ai.admin') {
                    return true;
                }
            }
        }

        $scopes = $actor['permission_codes'] ?? ($actor['permission_list'] ?? null);
        if (is_array($scopes)) {
            foreach ($scopes as $permission) {
                if (trim((string)$permission) === 'ai.admin') {
                    return true;
                }
            }
        }

        return false;
    }

    private function limitInt(string $name, int $default, int $min, int $max): int
    {
        $item = $this->settings->get('ai_limits', $name);
        $value = (int)($item['value'] ?? $default);
        return max($min, min($max, $value));
    }

    private function limitFloat(string $name, float $default, float $min, float $max): float
    {
        $item = $this->settings->get('ai_limits', $name);
        $raw = $item['value'] ?? $default;
        $value = is_numeric($raw) ? (float)$raw : $default;
        if ($value < $min) {
            return $min;
        }
        if ($value > $max) {
            return $max;
        }

        return $value;
    }
}
