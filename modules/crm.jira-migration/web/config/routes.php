<?php
declare(strict_types=1);

use Module\Crm\JiraMigration\Controller\JiraMigrationPageController;

return [
    'module-jira-migration' => [JiraMigrationPageController::class, 'index'],
];
