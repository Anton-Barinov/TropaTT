<?php
declare(strict_types=1);

use Module\Crm\HubspotMigration\Web\Controller\HubSpotMigrationWebController;

return [
    'module-hubspot-migration' => [HubSpotMigrationWebController::class, 'index'],
];
