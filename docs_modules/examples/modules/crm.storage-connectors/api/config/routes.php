<?php
declare(strict_types=1);

use Module\Crm\StorageConnectors\Api\Controller\StorageApiController;

return [
    ['methods' => ['GET'], 'route' => '/preflight', 'controller' => StorageApiController::class, 'action' => 'preflight', 'auth' => true],
    ['methods' => ['GET'], 'route' => '/config', 'controller' => StorageApiController::class, 'action' => 'getConfig', 'auth' => true],
    ['methods' => ['POST'], 'route' => '/config', 'controller' => StorageApiController::class, 'action' => 'saveConfig', 'auth' => true],
    ['methods' => ['POST'], 'route' => '/test-connection', 'controller' => StorageApiController::class, 'action' => 'testConnection', 'auth' => true],
    ['methods' => ['POST'], 'route' => '/migrate-batch', 'controller' => StorageApiController::class, 'action' => 'migrateBatch', 'auth' => true],
];
