<?php
declare(strict_types=1);

namespace Module\Crm\ShtabMigration\Controller;

use Web\System\Core\Controller;

final class ShtabMigrationPageController extends Controller
{
    public function index(): void { $this->render(__DIR__.'/../template/page/shtab_migration.php',['title'=>'Миграция из Shtab.app','route'=>'module-shtab-migration']); }
}
