<?php
declare(strict_types=1);

namespace Module\Crm\Raycast;

use Api\System\Library\Container;
use Api\System\Library\Module\AbstractModuleServiceProvider;

final class RaycastServiceProvider extends AbstractModuleServiceProvider
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
            'module.raycast.view',
        ];
    }

    public function getMenuItems(): array
    {
        return [
            [
                'route' => 'module-raycast',
                'label' => 'Raycast (MCP)',
                'icon' => '<i class="fa-solid fa-terminal"></i>',
                'permission' => 'module.raycast.view',
                'parent' => null,
            ],
        ];
    }

    public function getConfig(): array
    {
        return [
            'mcp_route' => 'api/v1/mcp',
            'server_name' => 'TropaTT',
        ];
    }
}
