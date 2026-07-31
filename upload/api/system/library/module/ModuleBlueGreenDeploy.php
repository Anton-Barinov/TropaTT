<?php
declare(strict_types=1);

namespace Api\System\Library\Module;

final class ModuleBlueGreenDeploy
{
    /** @var array<string, array{active: string, green: string|null, blue: string|null}> */
    private array $deployments = [];

    public function prepareGreen(string $moduleName, string $moduleDir, string $newVersion): string
    {
        $greenDir = $moduleDir . '_green_v' . $newVersion;
        if (is_dir($greenDir)) {
            $this->cleanDir($greenDir);
        }

        $this->copyDir($moduleDir, $greenDir);

        $this->deployments[$moduleName] = [
            'active' => 'blue',
            'green' => $greenDir,
            'blue' => $moduleDir,
        ];

        return $greenDir;
    }

    public function switchTraffic(string $moduleName): void
    {
        $dep = $this->deployments[$moduleName] ?? null;
        if ($dep === null || $dep['green'] === null) {
            throw new \RuntimeException("No green deployment prepared for {$moduleName}");
        }

        $this->deployments[$moduleName]['active'] = 'green';
    }

    public function rollback(string $moduleName): void
    {
        $dep = $this->deployments[$moduleName] ?? null;
        if ($dep === null) {
            return;
        }

        $this->deployments[$moduleName]['active'] = 'blue';

        if ($dep['green'] !== null && is_dir($dep['green'])) {
            $this->cleanDir($dep['green']);
        }
        $this->deployments[$moduleName]['green'] = null;
    }

    public function cleanup(string $moduleName): void
    {
        $dep = $this->deployments[$moduleName] ?? null;
        if ($dep === null || $dep['active'] !== 'green') {
            return;
        }

        if ($dep['blue'] !== null && is_dir($dep['blue'])) {
            $this->cleanDir($dep['blue']);
        }

        $this->deployments[$moduleName]['blue'] = null;
    }

    private function copyDir(string $source, string $dest): void
    {
        @mkdir($dest, 0755, true);
        $items = scandir($source);
        if ($items === false) {
            return;
        }
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $src = $source . '/' . $item;
            $dst = $dest . '/' . $item;
            if (is_dir($src)) {
                $this->copyDir($src, $dst);
            } else {
                copy($src, $dst);
            }
        }
    }

    private function cleanDir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        $items = scandir($dir);
        if ($items === false) {
            return;
        }
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $path = $dir . '/' . $item;
            if (is_dir($path)) {
                $this->cleanDir($path);
            } else {
                @unlink($path);
            }
        }
        @rmdir($dir);
    }
}
