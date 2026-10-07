<?php
declare(strict_types=1);

use Module\Crm\GoogleDrive\Controller\GoogleDriveApiController;

return [
    ['methods' => ['GET'], 'route' => '/status', 'controller' => GoogleDriveApiController::class, 'action' => 'getStatus', 'auth' => true],
];
