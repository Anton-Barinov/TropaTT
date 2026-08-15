<?php
declare(strict_types=1);

namespace Module\Crm\LinearMigration\Controller;

use Web\System\Core\Controller;

final class LinearPageController extends Controller
{
    public function index(): void
    {
        $this->render(__DIR__ . '/../template/page/linear.php', [
            'title' => 'Миграция из Linear',
            'route' => 'module-linear-migration',
        ]);
    }
}
