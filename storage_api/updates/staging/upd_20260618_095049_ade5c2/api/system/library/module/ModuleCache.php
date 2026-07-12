<?php
declare(strict_types=1);

namespace Api\System\Library\Module;

final class ModuleCache
{
    private string $cacheDir;

    public function __construct(string $storageBase)
    {
        $this->cacheDir = rtrim($storageBase, '/') . '/cache/modules';
        if (!is_dir($this->cacheDir)) {
            @mkdir($this->cacheDir, 0755, true);
        }
    }

    /**
     * @return array<string, Manifest>|null
     */
    public function getDiscoveredModules(): ?array
    {
        return $this->getCached('discovered_modules');
    }

    /** @param array<string, Manifest> $modules */
    public function setDiscoveredModules(array $modules): void
    {
        $serialized = [];
        foreach ($modules as $name => $manifest) {
            $serialized[$name] = $this->manifestToArray($manifest);
        }
        $this->setCached('discovered_modules', $serialized);
    }

    public function invalidateModule(string $moduleName): void
    {
        $file = $this->cacheDir . '/module_' . md5($moduleName) . '.cache';
        if (is_file($file)) {
            unlink($file);
        }
        $this->invalidateAll();
    }

    public function invalidateAll(): void
    {
        $file = $this->cacheDir . '/discovered_modules.cache';
        if (is_file($file)) {
            unlink($file);
        }
    }

    public function getLastDiscoveryTime(): ?int
    {
        $file = $this->cacheDir . '/discovery_time.cache';
        if (!is_file($file)) {
            return null;
        }
        $content = file_get_contents($file);
        if ($content === false || $content === '') {
            return null;
        }
        return (int)$content;
    }

    public function setLastDiscoveryTime(int $timestamp): void
    {
        file_put_contents($this->cacheDir . '/discovery_time.cache', (string)$timestamp);
    }

    /**
     * @param string $moduleName
     * @param string $type web|api
     * @return array<int, array<string, mixed>>|null
     */
    public function getModuleRoutes(string $moduleName, string $type): ?array
    {
        return $this->getCached('route_' . $moduleName . '_' . $type);
    }

    /** @param array<int, array<string, mixed>> $routes */
    public function setModuleRoutes(string $moduleName, string $type, array $routes): void
    {
        $this->setCached('route_' . $moduleName . '_' . $type, $routes);
    }

    /**
     * @return mixed
     */
    private function getCached(string $key): mixed
    {
        $file = $this->cacheDir . '/' . $key . '.cache';
        if (!is_file($file)) {
            return null;
        }

        if (time() - filemtime($file) > 3600) {
            unlink($file);
            return null;
        }

        $content = file_get_contents($file);
        if ($content === false || $content === '') {
            return null;
        }

        $data = json_decode($content, true);
        if (!is_array($data)) {
            return null;
        }

        return $data;
    }

    /** @param mixed $data */
    private function setCached(string $key, mixed $data): void
    {
        $file = $this->cacheDir . '/' . $key . '.cache';
        file_put_contents($file, json_encode($data, JSON_UNESCAPED_UNICODE));
    }

    /** @return array<string, mixed> */
    private function manifestToArray(Manifest $manifest): array
    {
        return [
            'name' => $manifest->name,
            'version' => $manifest->version,
            'vendor' => $manifest->vendor,
            'title' => $manifest->title,
            'description' => $manifest->description,
            'core_version' => $manifest->coreVersion,
            'dependencies' => $manifest->dependencies,
            'require_permissions' => $manifest->requirePermissions,
            'api_routes' => $manifest->apiRoutes,
            'web_routes' => $manifest->webRoutes,
            'migrations' => $manifest->migrations,
            'hooks' => $manifest->hooks,
            'menu_items' => $manifest->menuItems,
            'service_provider' => $manifest->serviceProvider,
            'config_defaults' => $manifest->configDefaults,
            'assets' => $manifest->assets,
        ];
    }
}
