<?php
declare(strict_types=1);

use Module\Crm\WorksectionMigration\Controller\WorksectionMigrationPageController;

return [
    'module-worksection-migration' => [WorksectionMigrationPageController::class, 'index'],
];
