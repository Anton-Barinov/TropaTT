<?php
declare(strict_types=1);

namespace Api\System\Library\Module;

final class RepositoryVersioning
{
    /** @var array<string, array<int, string>> */
    private array $versions = [];

    /** @return array<int, string> */
    public function getVersions(string $moduleName): array
    {
        $versions = $this->versions[$moduleName] ?? [];
        usort($versions, 'version_compare');
        return $versions;
    }

    public function getLatestStable(string $moduleName): string
    {
        $versions = $this->getVersions($moduleName);
        $stable = array_filter($versions, fn($v) => !str_contains($v, '-'));
        return end($stable) ?: '0.0.0';
    }

    public function getLatest(string $moduleName, bool $includePreRelease = false): string
    {
        $versions = $this->getVersions($moduleName);
        if (!$includePreRelease) {
            $versions = array_filter($versions, fn($v) => !str_contains($v, '-'));
        }
        return end($versions) ?: '0.0.0';
    }

    public function hasVersion(string $moduleName, string $version): bool
    {
        return in_array($version, $this->versions[$moduleName] ?? [], true);
    }

    public function addVersion(string $moduleName, string $version): void
    {
        if (!in_array($version, $this->versions[$moduleName] ?? [], true)) {
            $this->versions[$moduleName][] = $version;
        }
    }
}
