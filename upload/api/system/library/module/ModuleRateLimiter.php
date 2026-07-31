<?php
declare(strict_types=1);

namespace Api\System\Library\Module;

final class ModuleRateLimiter
{
    /** @var array<string, array<string, array{count: int, reset: int}>> */
    private array $buckets = [];

    /** @var array<string, int> */
    private array $defaultLimits = [
        'api' => 60,
        'webhook' => 10,
    ];

    public function check(string $moduleName, string $type = 'api'): bool
    {
        $limit = $this->defaultLimits[$type] ?? 60;
        $now = time();

        if (!isset($this->buckets[$moduleName][$type])) {
            $this->buckets[$moduleName][$type] = ['count' => 0, 'reset' => $now + 60];
        }

        $bucket = &$this->buckets[$moduleName][$type];

        if ($now >= $bucket['reset']) {
            $bucket['count'] = 0;
            $bucket['reset'] = $now + 60;
        }

        if ($bucket['count'] >= $limit) {
            return false;
        }

        $bucket['count']++;
        return true;
    }

    public function hit(string $moduleName, string $type = 'api'): void
    {
        $now = time();
        if (!isset($this->buckets[$moduleName][$type])) {
            $this->buckets[$moduleName][$type] = ['count' => 1, 'reset' => $now + 60];
        } else {
            $this->buckets[$moduleName][$type]['count']++;
        }
    }

    public function getUsage(string $moduleName, string $type = 'api'): int
    {
        return $this->buckets[$moduleName][$type]['count'] ?? 0;
    }

    public function getRemaining(string $moduleName, string $type = 'api'): int
    {
        $limit = $this->defaultLimits[$type] ?? 60;
        $used = $this->getUsage($moduleName, $type);
        return max(0, $limit - $used);
    }

    public function getResetTime(string $moduleName, string $type = 'api'): int
    {
        return $this->buckets[$moduleName][$type]['reset'] ?? time() + 60;
    }
}
