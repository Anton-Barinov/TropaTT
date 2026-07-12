<?php
declare(strict_types=1);

namespace Api\System\Library\Module;

final class ModuleWatchDog
{
    /** @var array<string, array<string, int>> */
    private array $fileTimes = [];

    /** @var array<int, string> */
    private array $watchPatterns = [];

    public function __construct(string $moduleName, string $moduleDir)
    {
        $this->watchPatterns = [
            $moduleDir . '/**/*.php',
            $moduleDir . '/**/*.json',
            $moduleDir . '/**/*.js',
            $moduleDir . '/**/*.css',
        ];
    }

    /**
     * Scan files and detect changes.
     * @return array<int, string> Changed file paths
     */
    public function scan(): array
    {
        $changed = [];

        foreach ($this->watchPatterns as $pattern) {
            $files = glob($pattern);
            if ($files === false) {
                continue;
            }

            foreach ($files as $file) {
                $mtime = (int)filemtime($file);
                $prevTime = $this->fileTimes[$file] ?? 0;

                if ($mtime > $prevTime) {
                    $changed[] = $file;
                    $this->fileTimes[$file] = $mtime;
                }
            }
        }

        return $changed;
    }

    public function onFileChange(callable $callback): void
    {
        $changed = $this->scan();
        foreach ($changed as $file) {
            $callback($file);
        }
    }

    public function invalidateCache(string $moduleName, ModuleCache $cache): void
    {
        $cache->invalidateModule($moduleName);
    }

    public function autoReload(string $moduleName): void
    {
        error_log("[ModuleWatchDog] Auto-reload triggered for: {$moduleName}");
    }
}
