<?php
declare(strict_types=1);

use Module\Crm\WorksectionMigration\Controller\WorksectionMigrationController;

return [
    ['methods' => ['GET'], 'route' => '/connections', 'controller' => WorksectionMigrationController::class, 'action' => 'listConnections', 'auth' => true, 'required_permissions' => ['module.worksection-migration.view']],
    ['methods' => ['POST'], 'route' => '/connections', 'controller' => WorksectionMigrationController::class, 'action' => 'createConnection', 'auth' => true, 'required_permissions' => ['module.worksection-migration.manage', 'module.worksection-migration.secret_manage']],
    ['methods' => ['GET'], 'route' => '/connections/{public_id}', 'controller' => WorksectionMigrationController::class, 'action' => 'getConnection', 'auth' => true, 'required_permissions' => ['module.worksection-migration.view']],
    ['methods' => ['PATCH'], 'route' => '/connections/{public_id}', 'controller' => WorksectionMigrationController::class, 'action' => 'updateConnection', 'auth' => true, 'required_permissions' => ['module.worksection-migration.manage', 'module.worksection-migration.secret_manage']],
    ['methods' => ['DELETE'], 'route' => '/connections/{public_id}', 'controller' => WorksectionMigrationController::class, 'action' => 'deleteConnection', 'auth' => true, 'required_permissions' => ['module.worksection-migration.delete']],
    ['methods' => ['POST'], 'route' => '/connections/{public_id}/test', 'controller' => WorksectionMigrationController::class, 'action' => 'testConnection', 'auth' => true, 'required_permissions' => ['module.worksection-migration.manage']],
    ['methods' => ['GET'], 'route' => '/connections/{public_id}/workspaces', 'controller' => WorksectionMigrationController::class, 'action' => 'listWorkspaces', 'auth' => true, 'required_permissions' => ['module.worksection-migration.view']],
    ['methods' => ['POST'], 'route' => '/connections/{public_id}/discover', 'controller' => WorksectionMigrationController::class, 'action' => 'discover', 'auth' => true, 'required_permissions' => ['module.worksection-migration.run']],
    ['methods' => ['GET'], 'route' => '/connections/{public_id}/user-mappings', 'controller' => WorksectionMigrationController::class, 'action' => 'listUserMappings', 'auth' => true, 'required_permissions' => ['module.worksection-migration.view']],
    ['methods' => ['PATCH'], 'route' => '/connections/{public_id}/user-mappings/{mapping_id}', 'controller' => WorksectionMigrationController::class, 'action' => 'updateUserMapping', 'auth' => true, 'required_permissions' => ['module.worksection-migration.manage']],
    ['methods' => ['GET'], 'route' => '/jobs', 'controller' => WorksectionMigrationController::class, 'action' => 'listJobs', 'auth' => true, 'required_permissions' => ['module.worksection-migration.view']],
    ['methods' => ['POST'], 'route' => '/jobs', 'controller' => WorksectionMigrationController::class, 'action' => 'createJob', 'auth' => true, 'required_permissions' => ['module.worksection-migration.run', 'project.manage', 'task.manage', 'import.manage']],
    ['methods' => ['GET'], 'route' => '/jobs/{public_id}', 'controller' => WorksectionMigrationController::class, 'action' => 'getJob', 'auth' => true, 'required_permissions' => ['module.worksection-migration.view']],
    ['methods' => ['POST'], 'route' => '/jobs/{public_id}/run', 'controller' => WorksectionMigrationController::class, 'action' => 'startJob', 'auth' => true, 'required_permissions' => ['module.worksection-migration.run']],
    ['methods' => ['POST'], 'route' => '/jobs/{public_id}/pause', 'controller' => WorksectionMigrationController::class, 'action' => 'pauseJob', 'auth' => true, 'required_permissions' => ['module.worksection-migration.run']],
    ['methods' => ['POST'], 'route' => '/jobs/{public_id}/resume', 'controller' => WorksectionMigrationController::class, 'action' => 'resumeJob', 'auth' => true, 'required_permissions' => ['module.worksection-migration.run']],
    ['methods' => ['POST'], 'route' => '/jobs/{public_id}/cancel', 'controller' => WorksectionMigrationController::class, 'action' => 'cancelJob', 'auth' => true, 'required_permissions' => ['module.worksection-migration.run']],
    ['methods' => ['POST'], 'route' => '/jobs/{public_id}/retry-failed', 'controller' => WorksectionMigrationController::class, 'action' => 'retryFailed', 'auth' => true, 'required_permissions' => ['module.worksection-migration.run']],
    ['methods' => ['POST'], 'route' => '/jobs/{public_id}/rollback', 'controller' => WorksectionMigrationController::class, 'action' => 'rollbackJob', 'auth' => true, 'required_permissions' => ['module.worksection-migration.delete']],
    ['methods' => ['GET'], 'route' => '/jobs/{public_id}/items', 'controller' => WorksectionMigrationController::class, 'action' => 'listJobItems', 'auth' => true, 'required_permissions' => ['module.worksection-migration.view']],
    ['methods' => ['GET'], 'route' => '/jobs/{public_id}/logs', 'controller' => WorksectionMigrationController::class, 'action' => 'listJobLogs', 'auth' => true, 'required_permissions' => ['module.worksection-migration.view']],
    ['methods' => ['GET'], 'route' => '/jobs/{public_id}/report', 'controller' => WorksectionMigrationController::class, 'action' => 'getReport', 'auth' => true, 'required_permissions' => ['module.worksection-migration.report_view']],
];
