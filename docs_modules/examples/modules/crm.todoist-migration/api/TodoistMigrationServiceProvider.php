<?php
declare(strict_types=1);

namespace Module\Crm\TodoistMigration;

use Api\System\Library\Container;
use Api\System\Library\Module\AbstractModuleServiceProvider;
use Api\System\Library\Module\ScheduledTask;
use Module\Crm\TodoistMigration\Cron\TodoistWorkerHandler;
use Module\Crm\TodoistMigration\Repository\TodoistMigrationRepository;

final class TodoistMigrationServiceProvider extends AbstractModuleServiceProvider
{
    public function register(Container $container): void
    { $container->factory('module.todoist_migration.repository',fn(Container $c):TodoistMigrationRepository=>new TodoistMigrationRepository($c->get('db.pdo'))); }
    public function boot(Container $container): void {}
    public function getPermissions(): array
    { return ['module.todoist-migration.view','module.todoist-migration.manage','module.todoist-migration.run','module.todoist-migration.secret_manage','module.todoist-migration.delete','module.todoist-migration.report_view']; }
    public function getMenuItems(): array
    { return [['route'=>'module-todoist-migration','label'=>'Миграция из Todoist','icon'=>'<i class="fa-solid fa-list-check"></i>','permission'=>'module.todoist-migration.view','parent'=>null]]; }
    public function getScheduledTasks(): array
    { return [new ScheduledTask(name:'process_todoist_migration_jobs',description:'Process queued Todoist migration jobs',schedule:'*/5 * * * *',handler:[TodoistWorkerHandler::class,'run'],enabled:true,timeout:300,overlapAllowed:false,notifyOnError:true)]; }
    public function getAssets(): array { return ['js'=>['web/assets/js/todoist-migration.js'],'css'=>['web/assets/css/todoist-migration.css']]; }
    public function getConfig(): array
    { return ['request_timeout_seconds'=>60,'max_retries'=>4,'default_batch_size'=>100,'max_attachment_size_mb'=>20,'include_completed_by_default'=>false,'include_archived_by_default'=>false,'include_comments_by_default'=>true,'include_attachments_by_default'=>false,'max_tasks_per_job'=>0]; }
}
