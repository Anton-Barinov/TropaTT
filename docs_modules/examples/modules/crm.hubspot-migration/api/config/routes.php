<?php
declare(strict_types=1);

use Module\Crm\HubSpotMigration\Controller\HubSpotMigrationApiController;

return [
    ['methods' => ['GET'], 'route' => '/status', 'controller' => HubSpotMigrationApiController::class, 'action' => 'getStatus', 'auth' => true, 'workspace_required' => true, 'required_permissions' => ['settings.view']],
];
