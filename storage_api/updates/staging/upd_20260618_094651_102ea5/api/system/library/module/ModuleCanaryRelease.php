<?php
declare(strict_types=1);

namespace Api\System\Library\Module;

final class ModuleCanaryRelease
{
    private int $canaryPercent = 5;

    /** @var array<string, array{version: string, percent: int}> */
    private array $canaryModules = [];

    public function startCanary(string $moduleName, string $newVersion, int $percent = 5): void
    {
        $this->canaryModules[$moduleName] = [
            'version' => $newVersion,
            'percent' => min(100, max(1, $percent)),
        ];
    }

    public function increaseCanary(string $moduleName, int $percent): void
    {
        if (!isset($this->canaryModules[$moduleName])) {
            return;
        }
        $this->canaryModules[$moduleName]['percent'] = min(100, $this->canaryModules[$moduleName]['percent'] + $percent);
    }

    public function fullRollout(string $moduleName): void
    {
        if (!isset($this->canaryModules[$moduleName])) {
            return;
        }
        $this->canaryModules[$moduleName]['percent'] = 100;
    }

    public function rollbackCanary(string $moduleName): void
    {
        unset($this->canaryModules[$moduleName]);
    }

    public function isInCanary(string $moduleName, int $userId): bool
    {
        $canary = $this->canaryModules[$moduleName] ?? null;
        if ($canary === null) {
            return false;
        }

        $bucket = $userId % 100;
        return $bucket < $canary['percent'];
    }

    public function getCanaryPercent(string $moduleName): int
    {
        return $this->canaryModules[$moduleName]['percent'] ?? 0;
    }
}
