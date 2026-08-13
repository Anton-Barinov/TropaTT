<?php
declare(strict_types=1);

use Module\Crm\AsanaMigration\Controller\AsanaMigrationPageController;

return [
    'module-asana-migration' => [AsanaMigrationPageController::class, 'index'],
];
