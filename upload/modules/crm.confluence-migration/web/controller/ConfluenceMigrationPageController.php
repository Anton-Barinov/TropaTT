<?php
declare(strict_types=1);

namespace Module\Crm\ConfluenceMigration\Controller;

use Web\System\Core\Controller;

final class ConfluenceMigrationPageController extends Controller
{
    public function index(): void
    {
        $this->render(__DIR__ . '/../template/page/confluence_migration.php', [
            'title' => 'Миграция из Confluence',
            'route' => 'module-confluence-migration',
        ]);
    }
}
