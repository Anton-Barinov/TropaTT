<?php
declare(strict_types=1);

namespace Module\Crm\ActiveCollabMigration\Controller;

use Web\System\Core\Controller;

final class ActiveCollabMigrationPageController extends Controller
{
    public function index(): void
    {
        $this->render(__DIR__ . '/../template/page/activecollab_migration.php', [
            'title' => 'Миграция из ActiveCollab',
            'route' => 'module-activecollab-migration',
        ]);
    }
}
