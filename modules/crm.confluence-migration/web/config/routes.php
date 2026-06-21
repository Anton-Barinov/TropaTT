<?php
declare(strict_types=1);

use Module\Crm\ConfluenceMigration\Controller\ConfluenceMigrationPageController;

return [
    'module-confluence-migration' => [ConfluenceMigrationPageController::class, 'index'],
];
