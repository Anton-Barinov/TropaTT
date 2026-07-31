<?php
declare(strict_types=1);

use Module\Crm\ConfluenceMigration\Controller\ConfluenceMigrationController;

return [
    // Connections
    ['methods' => ['GET'], 'route' => '/connections', 'controller' => ConfluenceMigrationController::class, 'action' => 'listConnections', 'auth' => true],
    ['methods' => ['POST'], 'route' => '/connections', 'controller' => ConfluenceMigrationController::class, 'action' => 'createConnection', 'auth' => true, 'required_permissions' => ['module.confluence-migration.manage', 'module.confluence-migration.secret_manage']],
    ['methods' => ['GET'], 'route' => '/connections/{public_id}', 'controller' => ConfluenceMigrationController::class, 'action' => 'getConnection', 'auth' => true],
    ['methods' => ['PATCH'], 'route' => '/connections/{public_id}', 'controller' => ConfluenceMigrationController::class, 'action' => 'updateConnection', 'auth' => true, 'required_permissions' => ['module.confluence-migration.manage', 'module.confluence-migration.secret_manage']],
    ['methods' => ['DELETE'], 'route' => '/connections/{public_id}', 'controller' => ConfluenceMigrationController::class, 'action' => 'deleteConnection', 'auth' => true, 'required_permissions' => ['module.confluence-migration.delete']],
    ['methods' => ['POST'], 'route' => '/connections/{public_id}/test', 'controller' => ConfluenceMigrationController::class, 'action' => 'testConnection', 'auth' => true, 'required_permissions' => ['module.confluence-migration.manage']],

    // Discovery
    ['methods' => ['POST'], 'route' => '/connections/{public_id}/discover', 'controller' => ConfluenceMigrationController::class, 'action' => 'discoverSpaces', 'auth' => true, 'required_permissions' => ['module.confluence-migration.run']],

    // Jobs
    ['methods' => ['GET'], 'route' => '/jobs', 'controller' => ConfluenceMigrationController::class, 'action' => 'listJobs', 'auth' => true],
    ['methods' => ['POST'], 'route' => '/jobs', 'controller' => ConfluenceMigrationController::class, 'action' => 'createJob', 'auth' => true, 'required_permissions' => ['module.confluence-migration.run', 'knowledge.import', 'knowledge.create', 'knowledge.edit', 'knowledge.publish']],
    ['methods' => ['GET'], 'route' => '/jobs/{public_id}', 'controller' => ConfluenceMigrationController::class, 'action' => 'getJob', 'auth' => true],

    // Job lifecycle
    ['methods' => ['POST'], 'route' => '/jobs/{public_id}/start', 'controller' => ConfluenceMigrationController::class, 'action' => 'startJob', 'auth' => true, 'required_permissions' => ['module.confluence-migration.run']],
    ['methods' => ['POST'], 'route' => '/jobs/{public_id}/pause', 'controller' => ConfluenceMigrationController::class, 'action' => 'pauseJob', 'auth' => true, 'required_permissions' => ['module.confluence-migration.run']],
    ['methods' => ['POST'], 'route' => '/jobs/{public_id}/resume', 'controller' => ConfluenceMigrationController::class, 'action' => 'resumeJob', 'auth' => true, 'required_permissions' => ['module.confluence-migration.run']],
    ['methods' => ['POST'], 'route' => '/jobs/{public_id}/cancel', 'controller' => ConfluenceMigrationController::class, 'action' => 'cancelJob', 'auth' => true, 'required_permissions' => ['module.confluence-migration.run']],
    ['methods' => ['POST'], 'route' => '/jobs/{public_id}/retry-failed', 'controller' => ConfluenceMigrationController::class, 'action' => 'retryFailed', 'auth' => true, 'required_permissions' => ['module.confluence-migration.run']],

    // Job data
    ['methods' => ['GET'], 'route' => '/jobs/{public_id}/items', 'controller' => ConfluenceMigrationController::class, 'action' => 'listJobItems', 'auth' => true],
    ['methods' => ['GET'], 'route' => '/jobs/{public_id}/logs', 'controller' => ConfluenceMigrationController::class, 'action' => 'listJobLogs', 'auth' => true],
    ['methods' => ['GET'], 'route' => '/jobs/{public_id}/report', 'controller' => ConfluenceMigrationController::class, 'action' => 'getReport', 'auth' => true],
    ['methods' => ['GET'], 'route' => '/jobs/{public_id}/unresolved-links', 'controller' => ConfluenceMigrationController::class, 'action' => 'listUnresolvedLinks', 'auth' => true],
    ['methods' => ['GET'], 'route' => '/jobs/{public_id}/unsupported-macros', 'controller' => ConfluenceMigrationController::class, 'action' => 'listUnsupportedMacros', 'auth' => true],
    ['methods' => ['GET'], 'route' => '/jobs/{public_id}/download-report', 'controller' => ConfluenceMigrationController::class, 'action' => 'downloadReport', 'auth' => true],

    // User/group mappings
    ['methods' => ['GET'], 'route' => '/connections/{public_id}/user-mappings', 'controller' => ConfluenceMigrationController::class, 'action' => 'listUserMappings', 'auth' => true],
    ['methods' => ['PATCH'], 'route' => '/connections/{public_id}/user-mappings/{mapping_id}', 'controller' => ConfluenceMigrationController::class, 'action' => 'updateUserMapping', 'auth' => true, 'required_permissions' => ['module.confluence-migration.manage']],
    ['methods' => ['GET'], 'route' => '/connections/{public_id}/group-mappings', 'controller' => ConfluenceMigrationController::class, 'action' => 'listGroupMappings', 'auth' => true],
    ['methods' => ['PATCH'], 'route' => '/connections/{public_id}/group-mappings/{mapping_id}', 'controller' => ConfluenceMigrationController::class, 'action' => 'updateGroupMapping', 'auth' => true, 'required_permissions' => ['module.confluence-migration.manage']],

    // Admin/settings
    ['methods' => ['GET'], 'route' => '/settings', 'controller' => ConfluenceMigrationController::class, 'action' => 'getSettings', 'auth' => true],
    ['methods' => ['PATCH'], 'route' => '/settings', 'controller' => ConfluenceMigrationController::class, 'action' => 'updateSettings', 'auth' => true, 'required_permissions' => ['module.confluence-migration.manage']],
];
