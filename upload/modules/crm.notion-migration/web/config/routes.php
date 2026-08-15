<?php
declare(strict_types=1);

use Module\Crm\NotionMigration\Controller\NotionMigrationPageController;

return [
    'module-notion-migration' => [NotionMigrationPageController::class, 'index'],
];
