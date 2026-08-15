<?php
declare(strict_types=1);

namespace Module\Crm\NotionMigration\Controller;

use Web\System\Core\Controller;

final class NotionMigrationPageController extends Controller
{
    public function index(): void
    {
        $this->render(__DIR__ . '/../template/page/notion_migration.php', [
            'title' => 'Миграция из Notion',
            'route' => 'module-notion-migration',
        ]);
    }
}
