<?php
declare(strict_types=1);

use Module\Crm\ActiveCollabMigration\Controller\ActiveCollabMigrationController;

return [
    ['methods' => ['GET'], 'route' => '/connections', 'controller' => ActiveCollabMigrationController::class, 'action' => 'listConnections', 'auth' => true, 'required_permissions' => ['module.activecollab-migration.view']],
    ['methods' => ['POST'], 'route' => '/connections', 'controller' => ActiveCollabMigrationController::class, 'action' => 'createConnection', 'auth' => true, 'required_permissions' => ['module.activecollab-migration.manage', 'module.activecollab-migration.secret_manage']],
    ['methods' => ['GET'], 'route' => '/connections/{public_id}', 'controller' => ActiveCollabMigrationController::class, 'action' => 'getConnection', 'auth' => true, 'required_permissions' => ['module.activecollab-migration.view']],
    ['methods' => ['PATCH'], 'route' => '/connections/{public_id}', 'controller' => ActiveCollabMigrationController::class, 'action' => 'updateConnection', 'auth' => true, 'required_permissions' => ['module.activecollab-migration.manage', 'module.activecollab-migration.secret_manage']],
    ['methods' => ['DELETE'], 'route' => '/connections/{public_id}', 'controller' => ActiveCollabMigrationController::class, 'action' => 'deleteConnection', 'auth' => true, 'required_permissions' => ['module.activecollab-migration.delete']],
    ['methods' => ['POST'], 'route' => '/connections/{public_id}/test', 'controller' => ActiveCollabMigrationController::class, 'action' => 'testConnection', 'auth' => true, 'required_permissions' => ['module.activecollab-migration.manage']],
    ['methods' => ['GET'], 'route' => '/connections/{public_id}/workspaces', 'controller' => ActiveCollabMigrationController::class, 'action' => 'listWorkspaces', 'auth' => true, 'required_permissions' => ['module.activecollab-migration.view']],
    ['methods' => ['POST'], 'route' => '/connections/{public_id}/discover', 'controller' => ActiveCollabMigrationController::class, 'action' => 'discover', 'auth' => true, 'required_permissions' => ['module.activecollab-migration.run']],
    ['methods' => ['GET'], 'route' => '/connections/{public_id}/user-mappings', 'controller' => ActiveCollabMigrationController::class, 'action' => 'listUserMappings', 'auth' => true, 'required_permissions' => ['module.activecollab-migration.view']],
    ['methods' => ['PATCH'], 'route' => '/connections/{public_id}/user-mappings/{mapping_id}', 'controller' => ActiveCollabMigrationController::class, 'action' => 'updateUserMapping', 'auth' => true, 'required_permissions' => ['module.activecollab-migration.manage']],
    ['methods' => ['GET'], 'route' => '/jobs', 'controller' => ActiveCollabMigrationController::class, 'action' => 'listJobs', 'auth' => true, 'required_permissions' => ['module.activecollab-migration.view']],
    ['methods' => ['POST'], 'route' => '/jobs', 'controller' => ActiveCollabMigrationController::class, 'action' => 'createJob', 'auth' => true, 'required_permissions' => ['module.activecollab-migration.run', 'project.manage', 'task.manage', 'import.manage']],
    ['methods' => ['GET'], 'route' => '/jobs/{public_id}', 'controller' => ActiveCollabMigrationController::class, 'action' => 'getJob', 'auth' => true, 'required_permissions' => ['module.activecollab-migration.view']],
    ['methods' => ['POST'], 'route' => '/jobs/{public_id}/run', 'controller' => ActiveCollabMigrationController::class, 'action' => 'startJob', 'auth' => true, 'required_permissions' => ['module.activecollab-migration.run']],
    ['methods' => ['POST'], 'route' => '/jobs/{public_id}/pause', 'controller' => ActiveCollabMigrationController::class, 'action' => 'pauseJob', 'auth' => true, 'required_permissions' => ['module.activecollab-migration.run']],
    ['methods' => ['POST'], 'route' => '/jobs/{public_id}/resume', 'controller' => ActiveCollabMigrationController::class, 'action' => 'resumeJob', 'auth' => true, 'required_permissions' => ['module.activecollab-migration.run']],
    ['methods' => ['POST'], 'route' => '/jobs/{public_id}/cancel', 'controller' => ActiveCollabMigrationController::class, 'action' => 'cancelJob', 'auth' => true, 'required_permissions' => ['module.activecollab-migration.run']],
    ['methods' => ['POST'], 'route' => '/jobs/{public_id}/retry-failed', 'controller' => ActiveCollabMigrationController::class, 'action' => 'retryFailed', 'auth' => true, 'required_permissions' => ['module.activecollab-migration.run']],
    ['methods' => ['POST'], 'route' => '/jobs/{public_id}/rollback', 'controller' => ActiveCollabMigrationController::class, 'action' => 'rollbackJob', 'auth' => true, 'required_permissions' => ['module.activecollab-migration.delete']],
    ['methods' => ['GET'], 'route' => '/jobs/{public_id}/items', 'controller' => ActiveCollabMigrationController::class, 'action' => 'listJobItems', 'auth' => true, 'required_permissions' => ['module.activecollab-migration.view']],
    ['methods' => ['GET'], 'route' => '/jobs/{public_id}/logs', 'controller' => ActiveCollabMigrationController::class, 'action' => 'listJobLogs', 'auth' => true, 'required_permissions' => ['module.activecollab-migration.view']],
    ['methods' => ['GET'], 'route' => '/jobs/{public_id}/report', 'controller' => ActiveCollabMigrationController::class, 'action' => 'getReport', 'auth' => true, 'required_permissions' => ['module.activecollab-migration.report_view']],
];
