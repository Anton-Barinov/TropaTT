<?php
declare(strict_types=1);

namespace Api\System\Library\Service;

use Api\Model\Ai\AiRuntimeRepository;

final class AiRateLimitService
{
    public function __construct(
        private readonly AiRuntimeRepository $runtime,
        private readonly SettingService $settings
    ) {
    }

    /**
     * @param array<string,mixed> $actor
     * @return array{ok:bool,code?:string,retry_after?:int}
     */
    public function assertWithinLimits(string $actionType, array $actor): array
    {
        $userId = (int)($actor['id'] ?? 0);
        if ($userId <= 0) {
            return ['ok' => true];
        }

        $perMinute = $this->limitInt('max_requests_per_minute', 60, 1, 10000);
        $perDay = $this->limitInt('max_requests_per_day', 2000, 1, 1000000);

        $minuteWindow = gmdate('Y-m-d H:i:s', time() - 60);
        $minuteAggregate = $this->runtime->usageAggregateSince($minuteWindow, $userId, $actionType);
        if ((int)($minuteAggregate['request_count'] ?? 0) >= $perMinute) {
            return ['ok' => false, 'code' => 'AI_RATE_LIMITED', 'retry_after' => 60];
        }

        $dayWindow = gmdate('Y-m-d H:i:s', time() - 86400);
        $dayAggregate = $this->runtime->usageAggregateSince($dayWindow, $userId, null);
        if ((int)($dayAggregate['request_count'] ?? 0) >= $perDay) {
            return ['ok' => false, 'code' => 'AI_RATE_LIMITED', 'retry_after' => 3600];
        }

        return ['ok' => true];
    }

    private function limitInt(string $name, int $default, int $min, int $max): int
    {
        $item = $this->settings->get('ai_limits', $name);
        $value = (int)($item['value'] ?? $default);
        return max($min, min($max, $value));
    }
}
