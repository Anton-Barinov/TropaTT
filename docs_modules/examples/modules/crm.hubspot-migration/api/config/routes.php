<?php
declare(strict_types=1);

use Module\Crm\HubSpotMigration\Controller\HubSpotMigrationApiController;

return [
    ['methods' => ['GET'], 'route' => '/status', 'controller' => HubSpotMigrationApiController::class, 'action' => 'getStatus', 'auth' => true],
    ['methods' => ['POST'], 'route' => '/stage', 'controller' => HubSpotMigrationApiController::class, 'action' => 'stageBatch', 'auth' => true],
];
