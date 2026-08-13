<?php
declare(strict_types=1);

namespace Module\Crm\TrelloMigration\Controller;

use Web\System\Core\Controller;

final class TrelloMigrationPageController extends Controller
{
    public function index(): void
    {
        $this->render(__DIR__ . '/../template/page/trello_migration.php', [
            'title' => 'Миграция из Trello',
            'route' => 'module-trello-migration',
        ]);
    }
}
