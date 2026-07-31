<?php
declare(strict_types=1);

namespace Api\System\Library\Module;

use Api\System\Library\Container;
use Api\System\Library\Hook\HookManager;
use RuntimeException;

final class ServiceProviderRegistry
{
    /** @var array<string, ModuleServiceProviderInterface> */
    private array $providers = [];

    /** @var array<string, bool> */
    private array $registered = [];

    /** @var array<string, bool> */
    private array $booted = [];

    /** @var array<string, array<string, mixed>> */
    private array $errors = [];

    public function __construct(
        private readonly Container $container,
        private readonly PluginManager $pluginManager,
        private readonly HookManager $hookManager,
    ) {}

    /**
     * Register all active module service providers.
     */
    public function registerAll(): void
    {
        $active = $this->pluginManager->getActive();

        foreach ($active as $name => $manifest) {
            $spClass = $manifest->serviceProvider;
            if ($spClass === null || $spClass === '') {
                continue;
            }

            try {
                if (!class_exists($spClass)) {
                    $this->errors[$name]['register'] = "Service provider class not found: {$spClass}";
                    continue;
                }

                $provider = new $spClass();
                if (!($provider instanceof ModuleServiceProviderInterface)) {
                    $this->errors[$name]['register'] = "Service provider does not implement ModuleServiceProviderInterface: {$spClass}";
                    continue;
                }

                $this->providers[$name] = $provider;
                $provider->register($this->container);
                $this->registered[$name] = true;
            } catch (\Throwable $e) {
                $this->errors[$name]['register'] = $e->getMessage();
                error_log("[ServiceProviderRegistry] Registration failed for {$name}: " . $e->getMessage());
            }
        }
    }

    /**
     * Boot all registered providers.
     */
    public function bootAll(): void
    {
        foreach ($this->providers as $name => $provider) {
            if (isset($this->booted[$name])) {
                continue;
            }

            try {
                $provider->boot($this->container);
                $this->registerHooks($name, $provider);
                $this->booted[$name] = true;
            } catch (\Throwable $e) {
                $this->errors[$name]['boot'] = $e->getMessage();
                error_log("[ServiceProviderRegistry] Boot failed for {$name}: " . $e->getMessage());
            }
        }
    }

    /**
     * @return array<string, ModuleServiceProviderInterface>
     */
    public function getProviders(): array
    {
        return $this->providers;
    }

    public function getProvider(string $moduleName): ?ModuleServiceProviderInterface
    {
        return $this->providers[$moduleName] ?? null;
    }

    /**
     * @return array<string, array<int, array{handler: string, priority: int}>>
     */
    public function getAllHooks(): array
    {
        $hooks = [];
        foreach ($this->providers as $name => $provider) {
            foreach ($provider->getHooks() as $hookName => $handlers) {
                foreach ($handlers as $handler) {
                    $hooks[$hookName][] = $handler;
                }
            }
        }
        return $hooks;
    }

    /**
     * @return array<int, array{route: string, label: string, icon: string, permission: string|null, parent: string|null}>
     */
    public function getAllMenuItems(): array
    {
        $items = [];
        foreach ($this->providers as $name => $provider) {
            foreach ($provider->getMenuItems() as $item) {
                $items[] = $item;
            }
        }
        return $items;
    }

    /**
     * @return array<string, mixed>
     */
    public function getAllConfigs(): array
    {
        $configs = [];
        foreach ($this->providers as $name => $provider) {
            $configs[$name] = $provider->getConfig();
        }
        return $configs;
    }

    /**
     * @return array<string, mixed>
     */
    public function getAllAssets(): array
    {
        $assets = [];
        foreach ($this->providers as $name => $provider) {
            $assets[$name] = $provider->getAssets();
        }
        return $assets;
    }

    /**
     * @return array<int, ScheduledTask>
     */
    public function getAllScheduledTasks(): array
    {
        $tasks = [];
        foreach ($this->providers as $name => $provider) {
            foreach ($provider->getScheduledTasks() as $task) {
                $tasks[] = $task;
            }
        }
        return $tasks;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    private function registerHooks(string $moduleName, ModuleServiceProviderInterface $provider): void
    {
        foreach ($provider->getHooks() as $hookName => $handlers) {
            foreach ($handlers as $handler) {
                $handlerStr = $handler['handler'] ?? '';
                $priority = (int)($handler['priority'] ?? 10);

                if ($handlerStr === '') {
                    continue;
                }

                if (str_contains($handlerStr, '::')) {
                    [$class, $method] = explode('::', $handlerStr, 2);
                    $callable = [$class, $method];
                } else {
                    $callable = $handlerStr;
                }

                if (is_callable($callable)) {
                    $this->hookManager->register($hookName, $callable, $priority);
                }
            }
        }
    }
}
