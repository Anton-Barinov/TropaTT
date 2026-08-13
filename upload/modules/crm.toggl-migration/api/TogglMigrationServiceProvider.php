<?php
declare(strict_types=1);

namespace Module\Crm\TogglMigration;

use Api\System\Library\Container;
use Api\System\Library\Module\AbstractModuleServiceProvider;
use Api\System\Library\Module\ScheduledTask;
use Module\Crm\TogglMigration\Cron\TogglWorkerHandler;
use Module\Crm\TogglMigration\Repository\TogglMigrationRepository;

final class TogglMigrationServiceProvider extends AbstractModuleServiceProvider
{
    public function register(Container $container): void
    {
        $container->factory('module.toggl_migration.repository', fn(Container $c): TogglMigrationRepository => new TogglMigrationRepository($c->get('db.pdo')));
    }

    public function boot(Container $container): void
    {
    }

    public function getPermissions(): array
    {
        return [
            'module.toggl-migration.view',
            'module.toggl-migration.manage',
            'module.toggl-migration.run',
            'module.toggl-migration.secret_manage',
            'module.toggl-migration.delete',
            'module.toggl-migration.report_view',
        ];
    }

    public function getMenuItems(): array
    {
        return [[
            'route' => 'module-toggl-migration',
            'label' => 'Миграция из Toggl',
            'icon' => '<i class="fa-solid fa-arrows-rotate"></i>',
            'permission' => 'module.toggl-migration.view',
            'parent' => null,
        ]];
    }

    public function getScheduledTasks(): array
    {
        return [new ScheduledTask(
            name: 'process_toggl_migration_jobs',
            description: 'Process queued Toggl migration jobs',
            schedule: '*/5 * * * *',
            handler: [TogglWorkerHandler::class, 'run'],
            enabled: true,
            timeout: 300,
            overlapAllowed: false,
            notifyOnError: true,
        )];
    }

    public function getAssets(): array
    {
        return ['js' => ['web/assets/js/toggl-migration.js'], 'css' => ['web/assets/css/toggl-migration.css']];
    }

    public function getConfig(): array
    {
        return [
            'request_timeout_seconds' => 60,
            'max_retries' => 4,
            'default_batch_size' => 50,
            'include_archived_by_default' => false,
            'max_tasks_per_job' => 0,
            'webhooks_enabled_by_default' => false,
        ];
    }
}
