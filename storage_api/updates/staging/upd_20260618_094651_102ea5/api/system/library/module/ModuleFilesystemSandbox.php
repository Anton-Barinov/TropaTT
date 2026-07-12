<?php
declare(strict_types=1);

namespace Api\System\Library\Module;

final class ModuleFilesystemSandbox
{
    /** @var array<int, string> */
    private array $allowedDirs;

    /** @var array<int, string> */
    private array $forbiddenDirs = [];

    public function __construct(string $moduleName, string $projectRoot)
    {
        $moduleDir = $projectRoot . '/modules/' . $moduleName;
        $this->allowedDirs = [
            $moduleDir,
            $projectRoot . '/system/storage/modules/' . $moduleName,
        ];
    }

    /**
     * Check if a file path is within allowed directories.
     */
    public function canRead(string $path): bool
    {
        return $this->isAllowedPath($path);
    }

    public function canWrite(string $path): bool
    {
        return $this->isAllowedPath($path);
    }

    public function canExecute(string $path): bool
    {
        return $this->isAllowedPath($path);
    }

    private function isAllowedPath(string $path): bool
    {
        $realPath = realpath($path);
        if ($realPath === false) {
            return false;
        }

        foreach ($this->allowedDirs as $allowed) {
            $realAllowed = realpath($allowed);
            if ($realAllowed !== false && str_starts_with($realPath, $realAllowed)) {
                return true;
            }
        }

        error_log("[ModuleSandbox] Path access denied: {$path}");
        return false;
    }
}
