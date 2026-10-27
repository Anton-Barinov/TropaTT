<?php
declare(strict_types=1);

namespace Module\Crm\Bitrix24Migration;

use Api\System\Library\Container;
use Api\System\Library\Module\AbstractModuleServiceProvider;
use Api\System\Library\Module\ScheduledTask;
use Module\Crm\Bitrix24Migration\Cron\Bitrix24WorkerHandler;

final class Bitrix24MigrationServiceProvider extends AbstractModuleServiceProvider
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
            'module.bitrix24-migration.view',
            'module.bitrix24-migration.manage',
            'module.bitrix24-migration.run',
            'module.bitrix24-migration.secret_manage',
            'module.bitrix24-migration.delete',
            'module.bitrix24-migration.report_view',
        ];
    }

    public function getMenuItems(): array
    {
        return [[
            'route' => 'module-bitrix24-migration',
            'label' => 'Миграция из Битрикс24',
            'icon' => '<i class="fa-solid fa-building-circle-arrow-right"></i>',
            'permission' => 'module.bitrix24-migration.view',
            'parent' => null,
        ]];
    }

    public function getScheduledTasks(): array
    {
        return [new ScheduledTask(
            name: 'process_bitrix24_migration_jobs',
            description: 'Process queued Bitrix24 migration jobs',
            schedule: '*/5 * * * *',
            handler: [Bitrix24WorkerHandler::class, 'run'],
            enabled: true,
            timeout: 300,
            overlapAllowed: false,
            notifyOnError: true,
        )];
    }

    public function getAssets(): array
    {
        return ['js' => ['web/assets/js/bitrix24-migration.js'], 'css' => ['web/assets/css/bitrix24-migration.css']];
    }

    public function getConfig(): array
    {
        return [
            'request_timeout_seconds' => 60,
            'max_retries' => 4,
            'default_batch_size' => 50,
            'max_items_per_entity' => 10000,
            'max_attachment_size_mb' => 20,
            'include_archived_by_default' => false,
            'download_files_by_default' => false,
            'require_root_for_write' => true,
        ];
    }
}
