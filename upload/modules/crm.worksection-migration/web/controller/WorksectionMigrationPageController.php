<?php
declare(strict_types=1);

namespace Module\Crm\WorksectionMigration\Controller;

use Web\System\Core\Controller;

final class WorksectionMigrationPageController extends Controller
{
    public function index(): void
    {
        $this->render(__DIR__ . '/../template/page/worksection_migration.php', [
            'title' => 'Миграция из Worksection',
            'route' => 'module-worksection-migration',
        ]);
    }
}
