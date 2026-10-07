<?php
declare(strict_types=1);

use Module\Crm\StarterKits\Api\Controller\StarterKitsApiController;

return [
    ['methods' => ['GET'], 'route' => '/kits', 'controller' => StarterKitsApiController::class, 'action' => 'listKits', 'auth' => true],
    ['methods' => ['GET'], 'route' => '/preview', 'controller' => StarterKitsApiController::class, 'action' => 'preview', 'auth' => true],
    ['methods' => ['POST'], 'route' => '/apply', 'controller' => StarterKitsApiController::class, 'action' => 'apply', 'auth' => true],
];
