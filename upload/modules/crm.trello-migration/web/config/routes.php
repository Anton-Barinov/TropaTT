<?php
declare(strict_types=1);

use Module\Crm\TrelloMigration\Controller\TrelloMigrationPageController;

return [
    'module-trello-migration' => [TrelloMigrationPageController::class, 'index'],
];
