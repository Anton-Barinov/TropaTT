<?php
declare(strict_types=1);

namespace Module\Crm\NotionMigration;

use Api\System\Library\Container;
use Api\System\Library\Module\AbstractModuleServiceProvider;
use Api\System\Library\Module\ScheduledTask;
use Module\Crm\NotionMigration\Cron\NotionWorkerHandler;

final class NotionMigrationServiceProvider extends AbstractModuleServiceProvider
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
            'module.notion-migration.view',
            'module.notion-migration.manage',
            'module.notion-migration.run',
            'module.notion-migration.secret_manage',
            'module.notion-migration.delete',
        ];
    }

    public function getMenuItems(): array
    {
        return [
            [
                'route' => 'module-notion-migration',
                'label' => 'Миграция из Notion',
                'icon' => '<i class="fa-solid fa-cloud-arrow-down"></i>',
                'permission' => 'module.notion-migration.view',
                'parent' => null,
            ],
        ];
    }

    public function getScheduledTasks(): array
    {
        return [
            new ScheduledTask(
                name: 'process_notion_imports',
                description: 'Process queued Notion import jobs',
                schedule: '*/5 * * * *',
                handler: [NotionWorkerHandler::class, 'run'],
                enabled: true,
                timeout: 300,
                overlapAllowed: false,
                notifyOnError: true,
            ),
        ];
    }

    public function getConfig(): array
    {
        return [
            'request_timeout_seconds' => 30,
            'max_retries' => 4,
            'default_batch_size' => 100,
            'max_pages_per_job' => 0,
            'max_depth' => 20,
            'include_comments_by_default' => true,
            'publish_by_default' => false,
        ];
    }
}
