<?php
declare(strict_types=1);

namespace Module\Crm\LinearMigration;

use Api\System\Library\Container;
use Api\System\Library\Module\AbstractModuleServiceProvider;

final class LinearServiceProvider extends AbstractModuleServiceProvider
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
            'module.linear-migration.view',
            'module.linear-migration.manage',
            'module.linear-migration.run',
            'module.linear-migration.secret_manage',
            'module.linear-migration.delete',
        ];
    }

    public function getMenuItems(): array
    {
        return [
            [
                'route' => 'module-linear-migration',
                'label' => 'Миграция из Linear',
                'icon' => '<i class="fa-solid fa-arrow-right-arrow-left"></i>',
                'permission' => 'module.linear-migration.view',
                'parent' => null,
            ],
        ];
    }

    public function getConfig(): array
    {
        return [
            'request_timeout_seconds' => 30,
            'max_retries' => 3,
            'batch_size' => 50,
            'max_issues_per_job' => 0,
            'include_comments_by_default' => true,
        ];
    }
}
