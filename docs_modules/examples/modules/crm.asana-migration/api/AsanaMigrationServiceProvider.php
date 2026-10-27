<?php
declare(strict_types=1);

namespace Module\Crm\AsanaMigration;

use Api\System\Library\Container;
use Api\System\Library\Module\AbstractModuleServiceProvider;
use Api\System\Library\Module\ScheduledTask;
use Module\Crm\AsanaMigration\Cron\AsanaWorkerHandler;
use Module\Crm\AsanaMigration\Repository\AsanaMigrationRepository;

final class AsanaMigrationServiceProvider extends AbstractModuleServiceProvider
{
    public function register(Container $container): void
    {
        $container->factory('module.asana_migration.repository', fn(Container $c): AsanaMigrationRepository => new AsanaMigrationRepository($c->get('db.pdo')));
    }

    public function boot(Container $container): void
    {
    }

    public function getPermissions(): array
    {
        return [
            'module.asana-migration.view',
            'module.asana-migration.manage',
            'module.asana-migration.run',
            'module.asana-migration.secret_manage',
            'module.asana-migration.delete',
            'module.asana-migration.report_view',
        ];
    }

    public function getMenuItems(): array
    {
        return [[
            'route' => 'module-asana-migration',
            'label' => 'Миграция из Asana',
            'icon' => '<i class="fa-solid fa-arrows-rotate"></i>',
            'permission' => 'module.asana-migration.view',
            'parent' => null,
        ]];
    }

    public function getScheduledTasks(): array
    {
        return [new ScheduledTask(
            name: 'process_asana_migration_jobs',
            description: 'Process queued Asana migration jobs',
            schedule: '*/5 * * * *',
            handler: [AsanaWorkerHandler::class, 'run'],
            enabled: true,
            timeout: 300,
            overlapAllowed: false,
            notifyOnError: true,
        )];
    }

    public function getAssets(): array
    {
        return ['js' => ['web/assets/js/asana-migration.js'], 'css' => ['web/assets/css/asana-migration.css']];
    }

    public function getConfig(): array
    {
        return [
            'request_timeout_seconds' => 60,
            'attachment_download_timeout_seconds' => 120,
            'max_retries' => 4,
            'default_batch_size' => 50,
            'max_attachment_size_mb' => 20,
            'include_archived_by_default' => false,
            'default_section_mode' => 'project_module',
            'max_tasks_per_job' => 0,
            'webhooks_enabled_by_default' => false,
        ];
    }
}
