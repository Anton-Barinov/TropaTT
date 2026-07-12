<?php
declare(strict_types=1);

namespace Api\System\Library\Module;

final class ModuleProfiler
{
    /** @var array<string, array{start: float, operations: array<string, array{start: float}>>} */
    private array $profiles = [];

    /** @var array<string, array<string, float>> */
    private array $timings = [];

    public function start(string $moduleName, string $operation): void
    {
        if (!isset($this->profiles[$moduleName])) {
            $this->profiles[$moduleName] = ['start' => microtime(true), 'operations' => []];
        }
        $this->profiles[$moduleName]['operations'][$operation] = ['start' => microtime(true)];
    }

    public function stop(string $moduleName, string $operation): float
    {
        if (!isset($this->profiles[$moduleName]['operations'][$operation])) {
            return 0.0;
        }

        $elapsed = microtime(true) - $this->profiles[$moduleName]['operations'][$operation]['start'];
        $this->timings[$moduleName][$operation] = $elapsed;

        if ($elapsed > 0.5) {
            error_log(sprintf(
                '[ModuleProfiler] %s::%s took %.2fs (threshold=0.5s)',
                $moduleName,
                $operation,
                $elapsed
            ));
        }

        return $elapsed;
    }

    /** @return array<string, array<string, float>> */
    public function getTimings(): array
    {
        return $this->timings;
    }

    /** @return array<string, float>|null */
    public function getModuleTimings(string $moduleName): ?array
    {
        return $this->timings[$moduleName] ?? null;
    }

    public function getTotalTime(string $moduleName): float
    {
        if (!isset($this->profiles[$moduleName]['start'])) {
            return 0.0;
        }
        return microtime(true) - $this->profiles[$moduleName]['start'];
    }
}
