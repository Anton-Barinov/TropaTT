<?php
declare(strict_types=1);

use Module\Crm\TogglMigration\Controller\TogglMigrationController;

return [
    ['methods' => ['GET'], 'route' => '/connections', 'controller' => TogglMigrationController::class, 'action' => 'listConnections', 'auth' => true, 'required_permissions' => ['module.toggl-migration.view']],
    ['methods' => ['POST'], 'route' => '/connections', 'controller' => TogglMigrationController::class, 'action' => 'createConnection', 'auth' => true, 'required_permissions' => ['module.toggl-migration.manage', 'module.toggl-migration.secret_manage']],
    ['methods' => ['GET'], 'route' => '/connections/{public_id}', 'controller' => TogglMigrationController::class, 'action' => 'getConnection', 'auth' => true, 'required_permissions' => ['module.toggl-migration.view']],
    ['methods' => ['PATCH'], 'route' => '/connections/{public_id}', 'controller' => TogglMigrationController::class, 'action' => 'updateConnection', 'auth' => true, 'required_permissions' => ['module.toggl-migration.manage', 'module.toggl-migration.secret_manage']],
    ['methods' => ['DELETE'], 'route' => '/connections/{public_id}', 'controller' => TogglMigrationController::class, 'action' => 'deleteConnection', 'auth' => true, 'required_permissions' => ['module.toggl-migration.delete']],
    ['methods' => ['POST'], 'route' => '/connections/{public_id}/test', 'controller' => TogglMigrationController::class, 'action' => 'testConnection', 'auth' => true, 'required_permissions' => ['module.toggl-migration.manage']],
    ['methods' => ['GET'], 'route' => '/connections/{public_id}/workspaces', 'controller' => TogglMigrationController::class, 'action' => 'listWorkspaces', 'auth' => true, 'required_permissions' => ['module.toggl-migration.view']],
    ['methods' => ['POST'], 'route' => '/connections/{public_id}/discover', 'controller' => TogglMigrationController::class, 'action' => 'discover', 'auth' => true, 'required_permissions' => ['module.toggl-migration.run']],
    ['methods' => ['GET'], 'route' => '/connections/{public_id}/user-mappings', 'controller' => TogglMigrationController::class, 'action' => 'listUserMappings', 'auth' => true, 'required_permissions' => ['module.toggl-migration.view']],
    ['methods' => ['PATCH'], 'route' => '/connections/{public_id}/user-mappings/{mapping_id}', 'controller' => TogglMigrationController::class, 'action' => 'updateUserMapping', 'auth' => true, 'required_permissions' => ['module.toggl-migration.manage']],
    ['methods' => ['GET'], 'route' => '/jobs', 'controller' => TogglMigrationController::class, 'action' => 'listJobs', 'auth' => true, 'required_permissions' => ['module.toggl-migration.view']],
    ['methods' => ['POST'], 'route' => '/jobs', 'controller' => TogglMigrationController::class, 'action' => 'createJob', 'auth' => true, 'required_permissions' => ['module.toggl-migration.run', 'project.manage', 'task.manage', 'import.manage']],
    ['methods' => ['GET'], 'route' => '/jobs/{public_id}', 'controller' => TogglMigrationController::class, 'action' => 'getJob', 'auth' => true, 'required_permissions' => ['module.toggl-migration.view']],
    ['methods' => ['POST'], 'route' => '/jobs/{public_id}/run', 'controller' => TogglMigrationController::class, 'action' => 'startJob', 'auth' => true, 'required_permissions' => ['module.toggl-migration.run']],
    ['methods' => ['POST'], 'route' => '/jobs/{public_id}/pause', 'controller' => TogglMigrationController::class, 'action' => 'pauseJob', 'auth' => true, 'required_permissions' => ['module.toggl-migration.run']],
    ['methods' => ['POST'], 'route' => '/jobs/{public_id}/resume', 'controller' => TogglMigrationController::class, 'action' => 'resumeJob', 'auth' => true, 'required_permissions' => ['module.toggl-migration.run']],
    ['methods' => ['POST'], 'route' => '/jobs/{public_id}/cancel', 'controller' => TogglMigrationController::class, 'action' => 'cancelJob', 'auth' => true, 'required_permissions' => ['module.toggl-migration.run']],
    ['methods' => ['POST'], 'route' => '/jobs/{public_id}/retry-failed', 'controller' => TogglMigrationController::class, 'action' => 'retryFailed', 'auth' => true, 'required_permissions' => ['module.toggl-migration.run']],
    ['methods' => ['POST'], 'route' => '/jobs/{public_id}/rollback', 'controller' => TogglMigrationController::class, 'action' => 'rollbackJob', 'auth' => true, 'required_permissions' => ['module.toggl-migration.delete']],
    ['methods' => ['GET'], 'route' => '/jobs/{public_id}/items', 'controller' => TogglMigrationController::class, 'action' => 'listJobItems', 'auth' => true, 'required_permissions' => ['module.toggl-migration.view']],
    ['methods' => ['GET'], 'route' => '/jobs/{public_id}/logs', 'controller' => TogglMigrationController::class, 'action' => 'listJobLogs', 'auth' => true, 'required_permissions' => ['module.toggl-migration.view']],
    ['methods' => ['GET'], 'route' => '/jobs/{public_id}/report', 'controller' => TogglMigrationController::class, 'action' => 'getReport', 'auth' => true, 'required_permissions' => ['module.toggl-migration.report_view']],
];
