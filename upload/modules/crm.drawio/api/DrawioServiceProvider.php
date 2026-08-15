<?php
declare(strict_types=1);

namespace Module\Crm\Drawio;

use Api\System\Library\Container;
use Api\System\Library\Module\AbstractModuleServiceProvider;

final class DrawioServiceProvider extends AbstractModuleServiceProvider
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
            'module.drawio.view',
            'module.drawio.manage',
        ];
    }

    public function getMenuItems(): array
    {
        return [
            [
                'route' => 'module-drawio',
                'label' => 'Диаграммы draw.io',
                'icon' => '<i class="fa-solid fa-diagram-project"></i>',
                'permission' => 'module.drawio.view',
                'parent' => null,
            ],
        ];
    }

    public function getConfig(): array
    {
        return [
            'editor_url' => 'https://embed.diagrams.net/?embed=1&ui=atlas&spin=1&modified=unsavedChanges&proto=json',
            'max_xml_bytes' => 2000000,
        ];
    }
}
