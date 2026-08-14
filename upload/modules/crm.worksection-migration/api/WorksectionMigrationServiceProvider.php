<?php
declare(strict_types=1);

namespace Module\Crm\WorksectionMigration;

use Api\System\Library\Container;
use Api\System\Library\Module\AbstractModuleServiceProvider;
use Api\System\Library\Module\ScheduledTask;
use Module\Crm\WorksectionMigration\Cron\WorksectionWorkerHandler;
use Module\Crm\WorksectionMigration\Repository\WorksectionMigrationRepository;

final class WorksectionMigrationServiceProvider extends AbstractModuleServiceProvider
{
    public function register(Container $container): void
    {
        $container->factory('module.worksection_migration.repository', fn(Container $c): WorksectionMigrationRepository => new WorksectionMigrationRepository($c->get('db.pdo')));
    }

    public function boot(Container $container): void
    {
    }

    public function getPermissions(): array
    {
        return [
            'module.worksection-migration.view',
            'module.worksection-migration.manage',
            'module.worksection-migration.run',
            'module.worksection-migration.secret_manage',
            'module.worksection-migration.delete',
            'module.worksection-migration.report_view',
        ];
    }

    public function getMenuItems(): array
    {
        return [[
            'route' => 'module-worksection-migration',
            'label' => 'Миграция из Worksection',
            'icon' => '<i class="fa-solid fa-arrows-rotate"></i>',
            'permission' => 'module.worksection-migration.view',
            'parent' => null,
        ]];
    }

    public function getScheduledTasks(): array
    {
        return [new ScheduledTask(
            name: 'process_worksection_migration_jobs',
            description: 'Process queued Worksection migration jobs',
            schedule: '*/5 * * * *',
            handler: [WorksectionWorkerHandler::class, 'run'],
            enabled: true,
            timeout: 300,
            overlapAllowed: false,
            notifyOnError: true,
        )];
    }

    public function getAssets(): array
    {
        return ['js' => ['web/assets/js/worksection-migration.js'], 'css' => ['web/assets/css/worksection-migration.css']];
    }

    public function getConfig(): array
    {
        return [
            'request_timeout_seconds' => 60,
            'attachment_download_timeout_seconds' => 120,
            'max_retries' => 4,
            'default_batch_size' => 100,
            'max_attachment_size_mb' => 20,
            'include_archived_by_default' => false,
            'include_completed_by_default' => true,
            'include_comments_by_default' => true,
            'include_attachments_by_default' => false,
            'include_time_records_by_default' => true,
            'max_tasks_per_job' => 0,
        ];
    }
}
