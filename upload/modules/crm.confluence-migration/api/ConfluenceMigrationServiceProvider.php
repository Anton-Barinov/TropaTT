<?php
declare(strict_types=1);

namespace Module\Crm\ConfluenceMigration;

use Api\System\Library\Container;
use Api\System\Library\Module\AbstractModuleServiceProvider;
use Api\System\Library\Module\ScheduledTask;
use Module\Crm\ConfluenceMigration\Cron\ConfluenceWorkerHandler;

final class ConfluenceMigrationServiceProvider extends AbstractModuleServiceProvider
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
            'module.confluence-migration.view',
            'module.confluence-migration.manage',
            'module.confluence-migration.run',
            'module.confluence-migration.secret_manage',
            'module.confluence-migration.delete',
        ];
    }

    public function getMenuItems(): array
    {
        return [
            [
                'route' => 'module-confluence-migration',
                'label' => 'Миграция из Confluence',
                'icon' => '<i class="fa-solid fa-cloud-arrow-down"></i>',
                'permission' => 'module.confluence-migration.view',
                'parent' => null,
            ],
        ];
    }

    public function getScheduledTasks(): array
    {
        return [
            new ScheduledTask(
                name: 'process_confluence_imports',
                description: 'Process queued Confluence import jobs',
                schedule: '*/5 * * * *',
                handler: [ConfluenceWorkerHandler::class, 'run'],
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
            'max_attachment_size_mb' => 50,
            'allowed_confluence_hosts' => ['*.atlassian.net'],
            'custom_domain_allowlist' => [],
            'default_batch_size' => 50,
            'request_timeout_seconds' => 30,
            'max_retries' => 3,
            'dry_run_sample_limit' => 100,
        ];
    }
}
