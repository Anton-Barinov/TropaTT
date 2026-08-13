<?php
declare(strict_types=1);

use Module\Crm\TogglMigration\Controller\TogglMigrationPageController;

return [
    'module-toggl-migration' => [TogglMigrationPageController::class, 'index'],
];
