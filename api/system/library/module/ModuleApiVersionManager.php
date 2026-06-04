<?php
declare(strict_types=1);

namespace Api\System\Library\Module;

final class ModuleApiVersionManager
{
    /** @var array<string, array{version: string, routesFile: string, deprecated: bool, deprecationMessage: string|null}> */
    private array $versions = [];

    public function registerVersion(string $moduleName, string $version, string $routesFile): void
    {
        if (!isset($this->versions[$moduleName])) {
            $this->versions[$moduleName] = [];
        }
        $this->versions[$moduleName][$version] = [
            'version' => $version,
            'routesFile' => $routesFile,
            'deprecated' => false,
            'deprecationMessage' => null,
        ];
    }

    public function getVersionedRoutes(string $moduleName): array
    {
        return $this->versions[$moduleName] ?? [];
    }

    public function getLatestVersion(string $moduleName): ?string
    {
        $versions = array_keys($this->versions[$moduleName] ?? []);
        if ($versions === []) {
            return null;
        }
        usort($versions, 'version_compare');
        return end($versions);
    }

    public function deprecateVersion(string $moduleName, string $version, string $message): void
    {
        if (isset($this->versions[$moduleName][$version])) {
            $this->versions[$moduleName][$version]['deprecated'] = true;
            $this->versions[$moduleName][$version]['deprecationMessage'] = $message;
        }
    }

    public function removeVersion(string $moduleName, string $version): void
    {
        unset($this->versions[$moduleName][$version]);
    }

    public function isDeprecated(string $moduleName, string $version): bool
    {
        return (bool)($this->versions[$moduleName][$version]['deprecated'] ?? false);
    }
}
