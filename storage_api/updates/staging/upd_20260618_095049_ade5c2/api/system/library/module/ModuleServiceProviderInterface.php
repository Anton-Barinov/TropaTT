<?php
declare(strict_types=1);

namespace Api\System\Library\Module;

use Api\System\Library\Container;

interface ModuleServiceProviderInterface
{
    public function register(Container $container): void;

    public function boot(Container $container): void;

    /** @return array<string, array<int, array{handler: string, priority: int}>> */
    public function getHooks(): array;

    /** @return array<int, string> */
    public function getPermissions(): array;

    /** @return array<int, array{route: string, label: string, icon: string, permission: string|null, parent: string|null}> */
    public function getMenuItems(): array;

    /** @return array<string, mixed> */
    public function getConfig(): array;

    /** @return array<string, mixed> */
    public function getAssets(): array;

    /** @return array<int, \Api\System\Library\Module\ScheduledTask> */
    public function getScheduledTasks(): array;
}
