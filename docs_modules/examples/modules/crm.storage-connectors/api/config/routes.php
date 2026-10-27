<?php
declare(strict_types=1);

use Module\Crm\StorageConnectors\Api\Controller\StorageApiController;

return [
    ['methods' => ['GET'], 'route' => '/preflight', 'controller' => StorageApiController::class, 'action' => 'preflight', 'auth' => true, 'workspace_required' => true, 'required_permissions' => ['settings.view']],
    ['methods' => ['GET'], 'route' => '/config', 'controller' => StorageApiController::class, 'action' => 'getConfig', 'auth' => true, 'workspace_required' => true, 'required_permissions' => ['settings.view']],
    ['methods' => ['POST'], 'route' => '/config', 'controller' => StorageApiController::class, 'action' => 'saveConfig', 'auth' => true, 'workspace_required' => true, 'required_permissions' => ['settings.edit']],
    ['methods' => ['POST'], 'route' => '/test-connection', 'controller' => StorageApiController::class, 'action' => 'testConnection', 'auth' => true, 'workspace_required' => true, 'required_permissions' => ['settings.edit']],
    ['methods' => ['POST'], 'route' => '/migrate-batch', 'controller' => StorageApiController::class, 'action' => 'migrateBatch', 'auth' => true, 'workspace_required' => true, 'required_permissions' => ['settings.edit']],
    ['methods' => ['GET'], 'route' => '/migration-failures', 'controller' => StorageApiController::class, 'action' => 'migrationFailures', 'auth' => true, 'workspace_required' => true, 'required_permissions' => ['settings.view']],
    ['methods' => ['POST'], 'route' => '/migration-retry', 'controller' => StorageApiController::class, 'action' => 'retryMigrationFailure', 'auth' => true, 'workspace_required' => true, 'required_permissions' => ['settings.edit']],
];
