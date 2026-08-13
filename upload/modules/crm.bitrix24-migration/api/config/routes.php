<?php
declare(strict_types=1);

use Module\Crm\Bitrix24Migration\Controller\Bitrix24MigrationController;

return [
    ['methods' => ['GET'], 'route' => '/connections', 'controller' => Bitrix24MigrationController::class, 'action' => 'listConnections', 'auth' => true, 'required_permissions' => ['module.bitrix24-migration.view']],
    ['methods' => ['POST'], 'route' => '/connections', 'controller' => Bitrix24MigrationController::class, 'action' => 'createConnection', 'auth' => true, 'required_permissions' => ['module.bitrix24-migration.manage', 'module.bitrix24-migration.secret_manage']],
    ['methods' => ['GET'], 'route' => '/connections/{public_id}', 'controller' => Bitrix24MigrationController::class, 'action' => 'getConnection', 'auth' => true, 'required_permissions' => ['module.bitrix24-migration.view']],
    ['methods' => ['PATCH'], 'route' => '/connections/{public_id}', 'controller' => Bitrix24MigrationController::class, 'action' => 'updateConnection', 'auth' => true, 'required_permissions' => ['module.bitrix24-migration.manage', 'module.bitrix24-migration.secret_manage']],
    ['methods' => ['DELETE'], 'route' => '/connections/{public_id}', 'controller' => Bitrix24MigrationController::class, 'action' => 'deleteConnection', 'auth' => true, 'required_permissions' => ['module.bitrix24-migration.delete']],
    ['methods' => ['POST'], 'route' => '/connections/{public_id}/test', 'controller' => Bitrix24MigrationController::class, 'action' => 'testConnection', 'auth' => true, 'required_permissions' => ['module.bitrix24-migration.manage']],
    ['methods' => ['POST'], 'route' => '/connections/{public_id}/discover', 'controller' => Bitrix24MigrationController::class, 'action' => 'discover', 'auth' => true, 'required_permissions' => ['module.bitrix24-migration.run']],
    ['methods' => ['GET'], 'route' => '/connections/{public_id}/user-mappings', 'controller' => Bitrix24MigrationController::class, 'action' => 'listUserMappings', 'auth' => true, 'required_permissions' => ['module.bitrix24-migration.view']],
    ['methods' => ['PATCH'], 'route' => '/connections/{public_id}/user-mappings/{mapping_id}', 'controller' => Bitrix24MigrationController::class, 'action' => 'updateUserMapping', 'auth' => true, 'required_permissions' => ['module.bitrix24-migration.manage']],
    ['methods' => ['GET'], 'route' => '/jobs', 'controller' => Bitrix24MigrationController::class, 'action' => 'listJobs', 'auth' => true, 'required_permissions' => ['module.bitrix24-migration.view']],
    ['methods' => ['POST'], 'route' => '/jobs', 'controller' => Bitrix24MigrationController::class, 'action' => 'createJob', 'auth' => true, 'required_permissions' => ['module.bitrix24-migration.run']],
    ['methods' => ['GET'], 'route' => '/jobs/{public_id}', 'controller' => Bitrix24MigrationController::class, 'action' => 'getJob', 'auth' => true, 'required_permissions' => ['module.bitrix24-migration.view']],
    ['methods' => ['POST'], 'route' => '/jobs/{public_id}/run', 'controller' => Bitrix24MigrationController::class, 'action' => 'startJob', 'auth' => true, 'required_permissions' => ['module.bitrix24-migration.run']],
    ['methods' => ['POST'], 'route' => '/jobs/{public_id}/pause', 'controller' => Bitrix24MigrationController::class, 'action' => 'pauseJob', 'auth' => true, 'required_permissions' => ['module.bitrix24-migration.run']],
    ['methods' => ['POST'], 'route' => '/jobs/{public_id}/resume', 'controller' => Bitrix24MigrationController::class, 'action' => 'resumeJob', 'auth' => true, 'required_permissions' => ['module.bitrix24-migration.run']],
    ['methods' => ['POST'], 'route' => '/jobs/{public_id}/cancel', 'controller' => Bitrix24MigrationController::class, 'action' => 'cancelJob', 'auth' => true, 'required_permissions' => ['module.bitrix24-migration.run']],
    ['methods' => ['POST'], 'route' => '/jobs/{public_id}/retry-failed', 'controller' => Bitrix24MigrationController::class, 'action' => 'retryFailed', 'auth' => true, 'required_permissions' => ['module.bitrix24-migration.run']],
    ['methods' => ['POST'], 'route' => '/jobs/{public_id}/rollback', 'controller' => Bitrix24MigrationController::class, 'action' => 'rollbackJob', 'auth' => true, 'required_permissions' => ['module.bitrix24-migration.delete']],
    ['methods' => ['GET'], 'route' => '/jobs/{public_id}/items', 'controller' => Bitrix24MigrationController::class, 'action' => 'listJobItems', 'auth' => true, 'required_permissions' => ['module.bitrix24-migration.view']],
    ['methods' => ['GET'], 'route' => '/jobs/{public_id}/logs', 'controller' => Bitrix24MigrationController::class, 'action' => 'listJobLogs', 'auth' => true, 'required_permissions' => ['module.bitrix24-migration.view']],
    ['methods' => ['GET'], 'route' => '/jobs/{public_id}/report', 'controller' => Bitrix24MigrationController::class, 'action' => 'getReport', 'auth' => true, 'required_permissions' => ['module.bitrix24-migration.report_view']],
];
