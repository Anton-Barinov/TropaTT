<?php
declare(strict_types=1);

use Module\Crm\NotionMigration\Controller\NotionMigrationController;

return [
    // Connections
    ['methods' => ['GET'], 'route' => '/connections', 'controller' => NotionMigrationController::class, 'action' => 'listConnections', 'auth' => true],
    ['methods' => ['POST'], 'route' => '/connections', 'controller' => NotionMigrationController::class, 'action' => 'createConnection', 'auth' => true, 'required_permissions' => ['module.notion-migration.manage', 'module.notion-migration.secret_manage']],
    ['methods' => ['GET'], 'route' => '/connections/{public_id}', 'controller' => NotionMigrationController::class, 'action' => 'getConnection', 'auth' => true],
    ['methods' => ['PATCH'], 'route' => '/connections/{public_id}', 'controller' => NotionMigrationController::class, 'action' => 'updateConnection', 'auth' => true, 'required_permissions' => ['module.notion-migration.manage', 'module.notion-migration.secret_manage']],
    ['methods' => ['DELETE'], 'route' => '/connections/{public_id}', 'controller' => NotionMigrationController::class, 'action' => 'deleteConnection', 'auth' => true, 'required_permissions' => ['module.notion-migration.delete']],
    ['methods' => ['POST'], 'route' => '/connections/{public_id}/test', 'controller' => NotionMigrationController::class, 'action' => 'testConnection', 'auth' => true, 'required_permissions' => ['module.notion-migration.manage']],

    // Discovery
    ['methods' => ['POST'], 'route' => '/connections/{public_id}/discover', 'controller' => NotionMigrationController::class, 'action' => 'discoverObjects', 'auth' => true, 'required_permissions' => ['module.notion-migration.run']],

    // Jobs
    ['methods' => ['GET'], 'route' => '/jobs', 'controller' => NotionMigrationController::class, 'action' => 'listJobs', 'auth' => true],
    ['methods' => ['POST'], 'route' => '/jobs', 'controller' => NotionMigrationController::class, 'action' => 'createJob', 'auth' => true, 'required_permissions' => ['module.notion-migration.run', 'knowledge.import', 'knowledge.create', 'knowledge.edit', 'knowledge.publish']],
    ['methods' => ['GET'], 'route' => '/jobs/{public_id}', 'controller' => NotionMigrationController::class, 'action' => 'getJob', 'auth' => true],

    // Job lifecycle
    ['methods' => ['POST'], 'route' => '/jobs/{public_id}/start', 'controller' => NotionMigrationController::class, 'action' => 'startJob', 'auth' => true, 'required_permissions' => ['module.notion-migration.run']],
    ['methods' => ['POST'], 'route' => '/jobs/{public_id}/pause', 'controller' => NotionMigrationController::class, 'action' => 'pauseJob', 'auth' => true, 'required_permissions' => ['module.notion-migration.run']],
    ['methods' => ['POST'], 'route' => '/jobs/{public_id}/resume', 'controller' => NotionMigrationController::class, 'action' => 'resumeJob', 'auth' => true, 'required_permissions' => ['module.notion-migration.run']],
    ['methods' => ['POST'], 'route' => '/jobs/{public_id}/cancel', 'controller' => NotionMigrationController::class, 'action' => 'cancelJob', 'auth' => true, 'required_permissions' => ['module.notion-migration.run']],
    ['methods' => ['POST'], 'route' => '/jobs/{public_id}/retry-failed', 'controller' => NotionMigrationController::class, 'action' => 'retryFailed', 'auth' => true, 'required_permissions' => ['module.notion-migration.run']],

    // Job data
    ['methods' => ['GET'], 'route' => '/jobs/{public_id}/items', 'controller' => NotionMigrationController::class, 'action' => 'listJobItems', 'auth' => true],
    ['methods' => ['GET'], 'route' => '/jobs/{public_id}/logs', 'controller' => NotionMigrationController::class, 'action' => 'listJobLogs', 'auth' => true],
    ['methods' => ['GET'], 'route' => '/jobs/{public_id}/report', 'controller' => NotionMigrationController::class, 'action' => 'getReport', 'auth' => true],
    ['methods' => ['GET'], 'route' => '/jobs/{public_id}/download-report', 'controller' => NotionMigrationController::class, 'action' => 'downloadReport', 'auth' => true],

    // User mappings
    ['methods' => ['GET'], 'route' => '/connections/{public_id}/user-mappings', 'controller' => NotionMigrationController::class, 'action' => 'listUserMappings', 'auth' => true],
    ['methods' => ['PATCH'], 'route' => '/connections/{public_id}/user-mappings/{mapping_id}', 'controller' => NotionMigrationController::class, 'action' => 'updateUserMapping', 'auth' => true, 'required_permissions' => ['module.notion-migration.manage']],

    // Settings
    ['methods' => ['GET'], 'route' => '/settings', 'controller' => NotionMigrationController::class, 'action' => 'getSettings', 'auth' => true],
    ['methods' => ['PATCH'], 'route' => '/settings', 'controller' => NotionMigrationController::class, 'action' => 'updateSettings', 'auth' => true, 'required_permissions' => ['module.notion-migration.manage']],
];
