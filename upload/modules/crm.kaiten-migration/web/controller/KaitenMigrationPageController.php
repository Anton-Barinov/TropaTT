<?php
declare(strict_types=1);

namespace Module\Crm\KaitenMigration\Controller;

use Web\System\Core\Controller;

final class KaitenMigrationPageController extends Controller
{
    public function index(): void
    {
        $this->render(__DIR__.'/../template/page/kaiten_migration.php',['title'=>'Миграция из Kaiten','route'=>'module-kaiten-migration']);
    }
}
