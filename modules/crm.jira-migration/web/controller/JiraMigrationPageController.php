<?php
declare(strict_types=1);

namespace Module\Crm\JiraMigration\Controller;

use Web\System\Core\Controller;

final class JiraMigrationPageController extends Controller
{
    public function index(): void
    {
        $this->render(__DIR__ . '/../template/page/jira_migration.php', [
            'title' => 'Миграция из Jira',
            'route' => 'module-jira-migration',
        ]);
    }
}
