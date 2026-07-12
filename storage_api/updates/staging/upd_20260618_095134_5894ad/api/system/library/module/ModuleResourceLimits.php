<?php
declare(strict_types=1);

namespace Api\System\Library\Module;

final class ModuleResourceLimits
{
    private int $maxExecutionTime = 30;
    private int $maxMemoryUsage = 256;
    private int $maxHooksPerModule = 100;
    private int $maxRoutesPerModule = 50;
    private int $maxTablesPerModule = 20;
    private int $maxFileSize = 10485760;

    /** @var array<string, array{memory: int, time: float}> */
    private array $tracking = [];

    public function startTracking(string $moduleName): void
    {
        $this->tracking[$moduleName] = [
            'memory' => memory_get_usage(),
            'time' => microtime(true),
        ];
    }

    public function checkMemory(string $moduleName): void
    {
        if (!isset($this->tracking[$moduleName])) {
            return;
        }

        $used = memory_get_usage() - $this->tracking[$moduleName]['memory'];
        $maxBytes = $this->maxMemoryUsage * 1024 * 1024;

        if ($used > $maxBytes) {
            throw new \RuntimeException("Module '{$moduleName}' exceeded memory limit");
        }
    }

    public function checkTime(string $moduleName): void
    {
        if (!isset($this->tracking[$moduleName])) {
            return;
        }

        $elapsed = microtime(true) - $this->tracking[$moduleName]['time'];
        if ($elapsed > $this->maxExecutionTime) {
            throw new \RuntimeException("Module '{$moduleName}' exceeded execution time limit");
        }
    }

    public function stopTracking(string $moduleName): void
    {
        unset($this->tracking[$moduleName]);
    }

    public function getMaxHooksPerModule(): int
    {
        return $this->maxHooksPerModule;
    }

    public function getMaxRoutesPerModule(): int
    {
        return $this->maxRoutesPerModule;
    }

    public function getMaxTablesPerModule(): int
    {
        return $this->maxTablesPerModule;
    }

    public function getMaxFileSize(): int
    {
        return $this->maxFileSize;
    }
}
