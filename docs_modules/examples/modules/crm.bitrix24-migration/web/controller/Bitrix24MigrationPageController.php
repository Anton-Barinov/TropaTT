<?php
declare(strict_types=1);

namespace Module\Crm\Bitrix24Migration\Controller;

use Web\System\Core\Controller;

final class Bitrix24MigrationPageController extends Controller
{
    public function index(): void
    {
        $this->render(__DIR__ . '/../template/page/bitrix24_migration.php', ['title' => 'Миграция из Битрикс24', 'route' => 'module-bitrix24-migration']);
    }
}
