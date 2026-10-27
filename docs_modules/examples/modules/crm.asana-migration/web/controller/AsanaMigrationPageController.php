<?php
declare(strict_types=1);

namespace Module\Crm\AsanaMigration\Controller;

use Web\System\Core\Controller;

final class AsanaMigrationPageController extends Controller
{
    public function index(): void
    {
        $this->render(__DIR__ . '/../template/page/asana_migration.php', [
            'title' => 'Миграция из Asana',
            'route' => 'module-asana-migration',
        ]);
    }
}
