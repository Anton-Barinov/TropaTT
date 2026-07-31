<?php
declare(strict_types=1);

namespace Api\System\Library\Module;

final class ModuleSlaManager
{
    /** @var array<string, array{response_time: int, resolution_time: int, uptime: float}> */
    private array $slaLevels = [
        'mission_critical' => ['response_time' => 15, 'resolution_time' => 60, 'uptime' => 0.9999],
        'business_critical' => ['response_time' => 60, 'resolution_time' => 240, 'uptime' => 0.999],
        'standard' => ['response_time' => 480, 'resolution_time' => 2880, 'uptime' => 0.99],
    ];

    /** @var array<string, string> */
    private array $assignedLevels = [];

    public function assignLevel(string $moduleName, string $level): void
    {
        if (!isset($this->slaLevels[$level])) {
            throw new \InvalidArgumentException("Unknown SLA level: {$level}");
        }
        $this->assignedLevels[$moduleName] = $level;
    }

    public function getLevel(string $moduleName): string
    {
        return $this->assignedLevels[$moduleName] ?? 'standard';
    }

    /** @return array{response_time: int, resolution_time: int, uptime: float} */
    public function getSla(string $moduleName): array
    {
        $level = $this->getLevel($moduleName);
        return $this->slaLevels[$level];
    }

    /** @return array<string, array{response_time: int, resolution_time: int, uptime: float}> */
    public function getLevels(): array
    {
        return $this->slaLevels;
    }
}
