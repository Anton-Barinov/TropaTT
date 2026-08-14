<?php
declare(strict_types=1);

use Module\Crm\ActiveCollabMigration\Controller\ActiveCollabMigrationPageController;

return [
    'module-activecollab-migration' => [ActiveCollabMigrationPageController::class, 'index'],
];
