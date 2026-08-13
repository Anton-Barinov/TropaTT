<?php
declare(strict_types=1);

use Module\Crm\AsanaMigration\Controller\AsanaMigrationController;

return [
    ['methods' => ['GET'], 'route' => '/connections', 'controller' => AsanaMigrationController::class, 'action' => 'listConnections', 'auth' => true, 'required_permissions' => ['module.asana-migration.view']],
    ['methods' => ['POST'], 'route' => '/connections', 'controller' => AsanaMigrationController::class, 'action' => 'createConnection', 'auth' => true, 'required_permissions' => ['module.asana-migration.manage', 'module.asana-migration.secret_manage']],
    ['methods' => ['GET'], 'route' => '/connections/{public_id}', 'controller' => AsanaMigrationController::class, 'action' => 'getConnection', 'auth' => true, 'required_permissions' => ['module.asana-migration.view']],
    ['methods' => ['PATCH'], 'route' => '/connections/{public_id}', 'controller' => AsanaMigrationController::class, 'action' => 'updateConnection', 'auth' => true, 'required_permissions' => ['module.asana-migration.manage', 'module.asana-migration.secret_manage']],
    ['methods' => ['DELETE'], 'route' => '/connections/{public_id}', 'controller' => AsanaMigrationController::class, 'action' => 'deleteConnection', 'auth' => true, 'required_permissions' => ['module.asana-migration.delete']],
    ['methods' => ['POST'], 'route' => '/connections/{public_id}/test', 'controller' => AsanaMigrationController::class, 'action' => 'testConnection', 'auth' => true, 'required_permissions' => ['module.asana-migration.manage']],
    ['methods' => ['GET'], 'route' => '/connections/{public_id}/workspaces', 'controller' => AsanaMigrationController::class, 'action' => 'listWorkspaces', 'auth' => true, 'required_permissions' => ['module.asana-migration.view']],
    ['methods' => ['POST'], 'route' => '/connections/{public_id}/discover', 'controller' => AsanaMigrationController::class, 'action' => 'discover', 'auth' => true, 'required_permissions' => ['module.asana-migration.run']],
    ['methods' => ['GET'], 'route' => '/connections/{public_id}/user-mappings', 'controller' => AsanaMigrationController::class, 'action' => 'listUserMappings', 'auth' => true, 'required_permissions' => ['module.asana-migration.view']],
    ['methods' => ['PATCH'], 'route' => '/connections/{public_id}/user-mappings/{mapping_id}', 'controller' => AsanaMigrationController::class, 'action' => 'updateUserMapping', 'auth' => true, 'required_permissions' => ['module.asana-migration.manage']],
    ['methods' => ['GET'], 'route' => '/jobs', 'controller' => AsanaMigrationController::class, 'action' => 'listJobs', 'auth' => true, 'required_permissions' => ['module.asana-migration.view']],
    ['methods' => ['POST'], 'route' => '/jobs', 'controller' => AsanaMigrationController::class, 'action' => 'createJob', 'auth' => true, 'required_permissions' => ['module.asana-migration.run', 'project.manage', 'task.manage', 'import.manage']],
    ['methods' => ['GET'], 'route' => '/jobs/{public_id}', 'controller' => AsanaMigrationController::class, 'action' => 'getJob', 'auth' => true, 'required_permissions' => ['module.asana-migration.view']],
    ['methods' => ['POST'], 'route' => '/jobs/{public_id}/run', 'controller' => AsanaMigrationController::class, 'action' => 'startJob', 'auth' => true, 'required_permissions' => ['module.asana-migration.run']],
    ['methods' => ['POST'], 'route' => '/jobs/{public_id}/pause', 'controller' => AsanaMigrationController::class, 'action' => 'pauseJob', 'auth' => true, 'required_permissions' => ['module.asana-migration.run']],
    ['methods' => ['POST'], 'route' => '/jobs/{public_id}/resume', 'controller' => AsanaMigrationController::class, 'action' => 'resumeJob', 'auth' => true, 'required_permissions' => ['module.asana-migration.run']],
    ['methods' => ['POST'], 'route' => '/jobs/{public_id}/cancel', 'controller' => AsanaMigrationController::class, 'action' => 'cancelJob', 'auth' => true, 'required_permissions' => ['module.asana-migration.run']],
    ['methods' => ['POST'], 'route' => '/jobs/{public_id}/retry-failed', 'controller' => AsanaMigrationController::class, 'action' => 'retryFailed', 'auth' => true, 'required_permissions' => ['module.asana-migration.run']],
    ['methods' => ['POST'], 'route' => '/jobs/{public_id}/rollback', 'controller' => AsanaMigrationController::class, 'action' => 'rollbackJob', 'auth' => true, 'required_permissions' => ['module.asana-migration.delete']],
    ['methods' => ['GET'], 'route' => '/jobs/{public_id}/items', 'controller' => AsanaMigrationController::class, 'action' => 'listJobItems', 'auth' => true, 'required_permissions' => ['module.asana-migration.view']],
    ['methods' => ['GET'], 'route' => '/jobs/{public_id}/logs', 'controller' => AsanaMigrationController::class, 'action' => 'listJobLogs', 'auth' => true, 'required_permissions' => ['module.asana-migration.view']],
    ['methods' => ['GET'], 'route' => '/jobs/{public_id}/report', 'controller' => AsanaMigrationController::class, 'action' => 'getReport', 'auth' => true, 'required_permissions' => ['module.asana-migration.report_view']],
];
