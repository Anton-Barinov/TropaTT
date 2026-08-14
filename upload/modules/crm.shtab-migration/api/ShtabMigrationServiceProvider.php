<?php
declare(strict_types=1);

namespace Module\Crm\ShtabMigration;

use Api\System\Library\Container;
use Api\System\Library\Module\AbstractModuleServiceProvider;
use Api\System\Library\Module\ScheduledTask;
use Module\Crm\ShtabMigration\Cron\ShtabWorkerHandler;

final class ShtabMigrationServiceProvider extends AbstractModuleServiceProvider
{
    public function register(Container $container): void {}
    public function boot(Container $container): void {}

    public function getPermissions(): array
    {
        return ['module.shtab-migration.view','module.shtab-migration.manage','module.shtab-migration.run','module.shtab-migration.delete','module.shtab-migration.report_view'];
    }

    public function getMenuItems(): array
    {
        return [['route'=>'module-shtab-migration','label'=>'Миграция из Shtab.app','icon'=>'<i class="fa-solid fa-file-import"></i>','permission'=>'module.shtab-migration.view','parent'=>null]];
    }

    public function getScheduledTasks(): array
    {
        return [new ScheduledTask(name:'process_shtab_migration_jobs',description:'Process queued Shtab export migration jobs',schedule:'*/5 * * * *',handler:[ShtabWorkerHandler::class,'run'],enabled:true,timeout:300,overlapAllowed:false,notifyOnError:true)];
    }

    public function getConfig(): array
    {
        return ['max_upload_size_mb'=>20,'default_batch_size'=>100,'max_rows_per_job'=>100000,'supported_extensions'=>['csv','txt','xlsx']];
    }
}
