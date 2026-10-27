<?php
declare(strict_types=1);

namespace Module\Crm\ClickUpMigration;

use Api\System\Library\Container;
use Api\System\Library\Module\AbstractModuleServiceProvider;
use Api\System\Library\Module\ScheduledTask;
use Module\Crm\ClickUpMigration\Cron\ClickUpWorkerHandler;
use Module\Crm\ClickUpMigration\Repository\ClickUpMigrationRepository;

final class ClickUpMigrationServiceProvider extends AbstractModuleServiceProvider
{
    public function register(Container $container): void
    { $container->factory('module.clickup_migration.repository',fn(Container $c):ClickUpMigrationRepository=>new ClickUpMigrationRepository($c->get('db.pdo'))); }
    public function boot(Container $container): void {}
    public function getPermissions(): array
    { return ['module.clickup-migration.view','module.clickup-migration.manage','module.clickup-migration.run','module.clickup-migration.secret_manage','module.clickup-migration.delete','module.clickup-migration.report_view']; }
    public function getMenuItems(): array
    { return [['route'=>'module-clickup-migration','label'=>'Миграция из ClickUp','icon'=>'<i class="fa-solid fa-list-check"></i>','permission'=>'module.clickup-migration.view','parent'=>null]]; }
    public function getScheduledTasks(): array
    { return [new ScheduledTask(name:'process_clickup_migration_jobs',description:'Process queued ClickUp migration jobs',schedule:'*/5 * * * *',handler:[ClickUpWorkerHandler::class,'run'],enabled:true,timeout:300,overlapAllowed:false,notifyOnError:true)]; }
    public function getAssets(): array { return ['js'=>['web/assets/js/clickup-migration.js'],'css'=>['web/assets/css/clickup-migration.css']]; }
    public function getConfig(): array
    { return ['request_timeout_seconds'=>60,'max_retries'=>4,'default_batch_size'=>100,'max_attachment_size_mb'=>20,'include_completed_by_default'=>false,'include_archived_by_default'=>false,'include_comments_by_default'=>true,'include_attachments_by_default'=>false,'max_tasks_per_job'=>0]; }
}
