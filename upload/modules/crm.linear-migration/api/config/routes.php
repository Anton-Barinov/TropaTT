<?php
declare(strict_types=1);

use Module\Crm\LinearMigration\Controller\LinearController;

return [
    // Connections
    ['methods' => ['GET'], 'route' => '/connections', 'controller' => LinearController::class, 'action' => 'listConnections', 'auth' => true, 'required_permissions' => ['module.linear-migration.view']],
    ['methods' => ['POST'], 'route' => '/connections', 'controller' => LinearController::class, 'action' => 'createConnection', 'auth' => true, 'required_permissions' => ['module.linear-migration.manage', 'module.linear-migration.secret_manage']],
    ['methods' => ['GET'], 'route' => '/connections/{public_id}', 'controller' => LinearController::class, 'action' => 'getConnection', 'auth' => true, 'required_permissions' => ['module.linear-migration.view']],
    ['methods' => ['PATCH'], 'route' => '/connections/{public_id}', 'controller' => LinearController::class, 'action' => 'updateConnection', 'auth' => true, 'required_permissions' => ['module.linear-migration.manage']],
    ['methods' => ['DELETE'], 'route' => '/connections/{public_id}', 'controller' => LinearController::class, 'action' => 'deleteConnection', 'auth' => true, 'required_permissions' => ['module.linear-migration.delete']],
    ['methods' => ['POST'], 'route' => '/connections/{public_id}/test', 'controller' => LinearController::class, 'action' => 'testConnection', 'auth' => true, 'required_permissions' => ['module.linear-migration.manage']],

    // Discovery
    ['methods' => ['POST'], 'route' => '/connections/{public_id}/discover', 'controller' => LinearController::class, 'action' => 'discover', 'auth' => true, 'required_permissions' => ['module.linear-migration.run']],

    // Jobs
    ['methods' => ['GET'], 'route' => '/jobs', 'controller' => LinearController::class, 'action' => 'listJobs', 'auth' => true, 'required_permissions' => ['module.linear-migration.view']],
    ['methods' => ['POST'], 'route' => '/jobs', 'controller' => LinearController::class, 'action' => 'createJob', 'auth' => true, 'required_permissions' => ['module.linear-migration.run', 'project.manage', 'task.manage']],
    ['methods' => ['GET'], 'route' => '/jobs/{public_id}', 'controller' => LinearController::class, 'action' => 'getJob', 'auth' => true, 'required_permissions' => ['module.linear-migration.view']],
    ['methods' => ['POST'], 'route' => '/jobs/{public_id}/run', 'controller' => LinearController::class, 'action' => 'runJob', 'auth' => true, 'required_permissions' => ['module.linear-migration.run', 'project.manage', 'task.manage']],
    ['methods' => ['GET'], 'route' => '/jobs/{public_id}/items', 'controller' => LinearController::class, 'action' => 'listJobItems', 'auth' => true, 'required_permissions' => ['module.linear-migration.view']],
    ['methods' => ['GET'], 'route' => '/jobs/{public_id}/logs', 'controller' => LinearController::class, 'action' => 'listJobLogs', 'auth' => true, 'required_permissions' => ['module.linear-migration.view']],
];
