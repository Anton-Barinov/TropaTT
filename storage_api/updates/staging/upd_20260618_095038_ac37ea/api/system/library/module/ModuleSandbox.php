<?php
declare(strict_types=1);

namespace Api\System\Library\Module;

final class ModuleSandbox
{
    /** @var array<int, string> */
    private array $allowedDirectories;

    /** @var array<int, string> */
    private array $allowedServices;

    public function __construct(string $moduleName, string $projectRoot)
    {
        $moduleDir = $projectRoot . '/modules/' . $moduleName;
        $this->allowedDirectories = [
            $moduleDir,
            $projectRoot . '/system/storage/modules/' . $moduleName,
        ];

        $this->allowedServices = [
            'hook.manager',
            'plugin.manager',
            'module.config',
            'module.logger',
            'module.error_handler',
        ];
    }

    public function checkServiceAccess(string $serviceId): bool
    {
        if (in_array($serviceId, $this->allowedServices, true)) {
            return true;
        }

        if (str_starts_with($serviceId, 'module.')) {
            return true;
        }

        error_log("[ModuleSandbox] Service access denied: {$serviceId}");
        return false;
    }

    public function checkFileAccess(string $path): bool
    {
        $realPath = realpath($path);
        if ($realPath === false) {
            return false;
        }

        foreach ($this->allowedDirectories as $allowed) {
            $realAllowed = realpath($allowed);
            if ($realAllowed !== false && str_starts_with($realPath, $realAllowed)) {
                return true;
            }
        }

        return false;
    }

    public function checkExecution(string $moduleName): bool
    {
        return true;
    }
}
