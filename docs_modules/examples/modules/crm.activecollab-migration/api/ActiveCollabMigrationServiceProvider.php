<?php
declare(strict_types=1);

namespace Module\Crm\ActiveCollabMigration;

use Api\System\Library\Container;
use Api\System\Library\Module\AbstractModuleServiceProvider;
use Api\System\Library\Module\ScheduledTask;
use Module\Crm\ActiveCollabMigration\Cron\ActiveCollabWorkerHandler;
use Module\Crm\ActiveCollabMigration\Repository\ActiveCollabMigrationRepository;

final class ActiveCollabMigrationServiceProvider extends AbstractModuleServiceProvider
{
    public function register(Container $container): void
    {
        $container->factory('module.activecollab_migration.repository', fn(Container $c): ActiveCollabMigrationRepository => new ActiveCollabMigrationRepository($c->get('db.pdo')));
    }

    public function boot(Container $container): void
    {
    }

    public function getPermissions(): array
    {
        return [
            'module.activecollab-migration.view',
            'module.activecollab-migration.manage',
            'module.activecollab-migration.run',
            'module.activecollab-migration.secret_manage',
            'module.activecollab-migration.delete',
            'module.activecollab-migration.report_view',
        ];
    }

    public function getMenuItems(): array
    {
        return [[
            'route' => 'module-activecollab-migration',
            'label' => 'Миграция из ActiveCollab',
            'icon' => '<i class="fa-solid fa-arrows-rotate"></i>',
            'permission' => 'module.activecollab-migration.view',
            'parent' => null,
        ]];
    }

    public function getScheduledTasks(): array
    {
        return [new ScheduledTask(
            name: 'process_activecollab_migration_jobs',
            description: 'Process queued ActiveCollab migration jobs',
            schedule: '*/5 * * * *',
            handler: [ActiveCollabWorkerHandler::class, 'run'],
            enabled: true,
            timeout: 300,
            overlapAllowed: false,
            notifyOnError: true,
        )];
    }

    public function getAssets(): array
    {
        return ['js' => ['web/assets/js/activecollab-migration.js'], 'css' => ['web/assets/css/activecollab-migration.css']];
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
