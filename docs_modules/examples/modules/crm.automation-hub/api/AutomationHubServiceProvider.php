<?php
declare(strict_types=1);

namespace Module\Crm\AutomationHub;

use Api\System\Library\Container;

use Api\System\Library\Module\AbstractModuleServiceProvider;

final class AutomationHubServiceProvider extends AbstractModuleServiceProvider
{
    public function register(Container $container): void
    {
        // Service registration
    }

    public function boot(Container $container): void
    {
        // Boot hooks
    }

    public function getMenuItems(): array
    {
        return [
            [
                'route' => 'module-automation-hub',
                'label' => 'Автоматизация (n8n/Make)',
                'icon' => '<i class="fa-solid fa-bolt"></i>',
                'permission' => 'settings.edit',
                'parent' => null,
            ],
        ];
    }
}