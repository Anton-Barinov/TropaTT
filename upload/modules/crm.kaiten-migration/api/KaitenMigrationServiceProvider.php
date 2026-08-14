<?php
declare(strict_types=1);

namespace Module\Crm\KaitenMigration;

use Api\System\Library\Container;
use Api\System\Library\Module\AbstractModuleServiceProvider;
use Api\System\Library\Module\ScheduledTask;
use Module\Crm\KaitenMigration\Cron\KaitenWorkerHandler;
use Module\Crm\KaitenMigration\Repository\KaitenMigrationRepository;

final class KaitenMigrationServiceProvider extends AbstractModuleServiceProvider
{
    public function register(Container $container): void
    {
        $container->factory('module.kaiten_migration.repository', fn(Container $c): KaitenMigrationRepository => new KaitenMigrationRepository($c->get('db.pdo')));
    }

    public function boot(Container $container): void {}

    public function getPermissions(): array
    {
        return ['module.kaiten-migration.view','module.kaiten-migration.manage','module.kaiten-migration.run','module.kaiten-migration.secret_manage','module.kaiten-migration.delete','module.kaiten-migration.report_view'];
    }

    public function getMenuItems(): array
    {
        return [['route'=>'module-kaiten-migration','label'=>'Миграция из Kaiten','icon'=>'<i class="fa-solid fa-table-columns"></i>','permission'=>'module.kaiten-migration.view','parent'=>null]];
    }

    public function getScheduledTasks(): array
    {
        return [new ScheduledTask(name:'process_kaiten_migration_jobs',description:'Process queued Kaiten migration jobs',schedule:'*/5 * * * *',handler:[KaitenWorkerHandler::class,'run'],enabled:true,timeout:300,overlapAllowed:false,notifyOnError:true)];
    }

    public function getAssets(): array
    {
        return ['js'=>['web/assets/js/kaiten-migration.js'],'css'=>['web/assets/css/kaiten-migration.css']];
    }

    public function getConfig(): array
    {
        return ['request_timeout_seconds'=>60,'max_retries'=>4,'default_page_size'=>100,'max_collection_items'=>10000,'max_attachment_size_mb'=>20,'include_archived_by_default'=>false,'include_comments_by_default'=>true,'include_attachments_by_default'=>false,'include_history_by_default'=>false];
    }
}
