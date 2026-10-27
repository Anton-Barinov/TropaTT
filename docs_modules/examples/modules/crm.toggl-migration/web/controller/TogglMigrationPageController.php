<?php
declare(strict_types=1);

namespace Module\Crm\TogglMigration\Controller;

use Web\System\Core\Controller;

final class TogglMigrationPageController extends Controller
{
    public function index(): void
    {
        $this->render(__DIR__ . '/../template/page/toggl_migration.php', [
            'title' => 'Миграция из Toggl',
            'route' => 'module-toggl-migration',
        ]);
    }
}
