<?php
declare(strict_types=1);

namespace Module\Crm\TodoistMigration\Controller;

use Web\System\Core\Controller;

final class TodoistMigrationPageController extends Controller
{
    public function index(): void
    { $this->render(__DIR__.'/../template/page/todoist_migration.php',['title'=>'Миграция из Todoist','route'=>'module-todoist-migration']); }
}
