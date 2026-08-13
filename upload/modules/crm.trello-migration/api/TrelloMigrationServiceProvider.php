<?php
declare(strict_types=1);

namespace Module\Crm\TrelloMigration;

use Api\System\Library\Container;
use Api\System\Library\Module\AbstractModuleServiceProvider;
use Api\System\Library\Module\ScheduledTask;
use Module\Crm\TrelloMigration\Cron\TrelloWorkerHandler;

final class TrelloMigrationServiceProvider extends AbstractModuleServiceProvider
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
            'module.trello-migration.view',
            'module.trello-migration.manage',
            'module.trello-migration.run',
            'module.trello-migration.secret_manage',
            'module.trello-migration.delete',
            'module.trello-migration.report_view',
        ];
    }

    public function getMenuItems(): array
    {
        return [[
            'route' => 'module-trello-migration',
            'label' => 'Миграция из Trello',
            'icon' => '<i class="fa-brands fa-trello"></i>',
            'permission' => 'module.trello-migration.view',
            'parent' => null,
        ]];
    }

    public function getScheduledTasks(): array
    {
        return [new ScheduledTask(
            name: 'process_trello_migration_jobs',
            description: 'Process queued Trello migration and sync jobs',
            schedule: '*/5 * * * *',
            handler: [TrelloWorkerHandler::class, 'run'],
            enabled: true,
            timeout: 300,
            overlapAllowed: false,
            notifyOnError: true,
        )];
    }

    public function getConfig(): array
    {
        return [
            'request_timeout_seconds' => 60,
            'attachment_download_timeout_seconds' => 120,
            'max_retries' => 4,
            'default_batch_size' => 50,
            'max_attachment_size_mb' => 20,
            'poll_interval_minutes' => 15,
            'webhook_enabled_by_default' => false,
            'include_archived_by_default' => true,
            'default_list_mode' => 'status',
            'max_cards_per_job' => 0,
        ];
    }
}
