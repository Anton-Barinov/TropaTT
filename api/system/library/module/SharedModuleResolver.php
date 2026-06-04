<?php
declare(strict_types=1);

namespace Api\System\Library\Module;

final class SharedModuleResolver
{
    /** @var array<int, string> */
    private array $sharedModules = [];

    public function __construct(array $initial = [])
    {
        $this->sharedModules = $initial;
    }

    public function isShared(string $moduleName): bool
    {
        return in_array($moduleName, $this->sharedModules, true);
    }

    /** @return array<int, string> */
    public function getSharedModules(): array
    {
        return $this->sharedModules;
    }

    public function registerShared(string $moduleName): void
    {
        if (!in_array($moduleName, $this->sharedModules, true)) {
            $this->sharedModules[] = $moduleName;
        }
    }

    public function unregisterShared(string $moduleName): void
    {
        $this->sharedModules = array_values(array_filter(
            $this->sharedModules,
            fn($m) => $m !== $moduleName
        ));
    }
}
