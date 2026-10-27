<?php
declare(strict_types=1);

namespace Module\Crm\ClickUpMigration\Controller;

use Web\System\Core\Controller;

final class ClickUpMigrationPageController extends Controller
{
    public function index(): void
    { $this->render(__DIR__.'/../template/page/clickup_migration.php',['title'=>'Миграция из ClickUp','route'=>'module-clickup-migration']); }
}
