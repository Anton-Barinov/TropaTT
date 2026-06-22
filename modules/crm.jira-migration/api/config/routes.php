<?php
declare(strict_types=1);

use Module\Crm\JiraMigration\Controller\JiraMigrationController;

return [
    // Connections
    ['methods' => ['GET'], 'route' => '/connections', 'controller' => JiraMigrationController::class, 'action' => 'listConnections', 'auth' => true],
    ['methods' => ['POST'], 'route' => '/connections', 'controller' => JiraMigrationController::class, 'action' => 'createConnection', 'auth' => true, 'required_permissions' => ['module.jira-migration.manage', 'module.jira-migration.secret_manage']],
    ['methods' => ['GET'], 'route' => '/connections/{public_id}', 'controller' => JiraMigrationController::class, 'action' => 'getConnection', 'auth' => true],
    ['methods' => ['PATCH'], 'route' => '/connections/{public_id}', 'controller' => JiraMigrationController::class, 'action' => 'updateConnection', 'auth' => true, 'required_permissions' => ['module.jira-migration.manage', 'module.jira-migration.secret_manage']],
    ['methods' => ['DELETE'], 'route' => '/connections/{public_id}', 'controller' => JiraMigrationController::class, 'action' => 'deleteConnection', 'auth' => true, 'required_permissions' => ['module.jira-migration.delete']],
    ['methods' => ['POST'], 'route' => '/connections/{public_id}/test', 'controller' => JiraMigrationController::class, 'action' => 'testConnection', 'auth' => true, 'required_permissions' => ['module.jira-migration.manage']],

    // Discovery
    ['methods' => ['POST'], 'route' => '/discover', 'controller' => JiraMigrationController::class, 'action' => 'discover', 'auth' => true, 'required_permissions' => ['module.jira-migration.view']],

    // Dry-run
    ['methods' => ['POST'], 'route' => '/dry-run', 'controller' => JiraMigrationController::class, 'action' => 'createDryRun', 'auth' => true, 'required_permissions' => ['module.jira-migration.run']],

    // Jobs
    ['methods' => ['GET'], 'route' => '/jobs', 'controller' => JiraMigrationController::class, 'action' => 'listJobs', 'auth' => true],
    ['methods' => ['POST'], 'route' => '/jobs', 'controller' => JiraMigrationController::class, 'action' => 'createJob', 'auth' => true, 'required_permissions' => ['module.jira-migration.run']],
    ['methods' => ['GET'], 'route' => '/jobs/{public_id}', 'controller' => JiraMigrationController::class, 'action' => 'getJob', 'auth' => true],

    // Job lifecycle
    ['methods' => ['POST'], 'route' => '/jobs/{public_id}/run', 'controller' => JiraMigrationController::class, 'action' => 'startJob', 'auth' => true, 'required_permissions' => ['module.jira-migration.run']],
    ['methods' => ['POST'], 'route' => '/jobs/{public_id}/pause', 'controller' => JiraMigrationController::class, 'action' => 'pauseJob', 'auth' => true, 'required_permissions' => ['module.jira-migration.run']],
    ['methods' => ['POST'], 'route' => '/jobs/{public_id}/cancel', 'controller' => JiraMigrationController::class, 'action' => 'cancelJob', 'auth' => true, 'required_permissions' => ['module.jira-migration.run']],
    ['methods' => ['POST'], 'route' => '/jobs/{public_id}/retry-failed', 'controller' => JiraMigrationController::class, 'action' => 'retryFailed', 'auth' => true, 'required_permissions' => ['module.jira-migration.run']],

    // Job data
    ['methods' => ['GET'], 'route' => '/jobs/{public_id}/items', 'controller' => JiraMigrationController::class, 'action' => 'listJobItems', 'auth' => true],
    ['methods' => ['GET'], 'route' => '/jobs/{public_id}/logs', 'controller' => JiraMigrationController::class, 'action' => 'listJobLogs', 'auth' => true],
    ['methods' => ['GET'], 'route' => '/jobs/{public_id}/report', 'controller' => JiraMigrationController::class, 'action' => 'getReport', 'auth' => true],

    // Mappings
    ['methods' => ['GET'], 'route' => '/mappings', 'controller' => JiraMigrationController::class, 'action' => 'listMappings', 'auth' => true],
    ['methods' => ['PATCH'], 'route' => '/mappings/{public_id}', 'controller' => JiraMigrationController::class, 'action' => 'updateMapping', 'auth' => true, 'required_permissions' => ['module.jira-migration.manage']],

    // Unresolved
    ['methods' => ['GET'], 'route' => '/unresolved', 'controller' => JiraMigrationController::class, 'action' => 'listUnresolved', 'auth' => true],
];
