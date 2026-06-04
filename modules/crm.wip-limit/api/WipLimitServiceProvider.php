<?php
declare(strict_types=1);

namespace Module\Crm\WipLimit;

use Api\System\Library\Container;
use Api\System\Library\Module\AbstractModuleServiceProvider;

final class WipLimitServiceProvider extends AbstractModuleServiceProvider
{
    public function register(Container $container): void
    {
    }

    public function boot(Container $container): void
    {
    }

    public function getPermissions(): array
    {
        return [
            'module.wip-limit.view',
            'module.wip-limit.manage',
        ];
    }

    public function getMenuItems(): array
    {
        return [
            [
                'route' => 'module-wip-limit',
                'label' => 'WIP-лимиты',
                'icon' => '<i class="fa-solid fa-gauge-high"></i>',
                'permission' => 'module.wip-limit.view',
                'parent' => null,
            ],
        ];
    }

    public function getConfig(): array
    {
        return [
            'default_limit' => 5,
            'enforce_on_status' => ['in_progress', 'review'],
            'notify_on_exceed' => true,
        ];
    }

    public function getHooks(): array
    {
        return [];
    }
}
