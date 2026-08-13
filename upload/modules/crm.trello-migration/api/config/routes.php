<?php
declare(strict_types=1);

use Module\Crm\TrelloMigration\Controller\TrelloMigrationController;

return [
    ['methods' => ['GET'], 'route' => '/connections', 'controller' => TrelloMigrationController::class, 'action' => 'listConnections', 'auth' => true, 'required_permissions' => ['module.trello-migration.view']],
    ['methods' => ['POST'], 'route' => '/connections', 'controller' => TrelloMigrationController::class, 'action' => 'createConnection', 'auth' => true, 'required_permissions' => ['module.trello-migration.manage', 'module.trello-migration.secret_manage']],
    ['methods' => ['GET'], 'route' => '/connections/{public_id}', 'controller' => TrelloMigrationController::class, 'action' => 'getConnection', 'auth' => true, 'required_permissions' => ['module.trello-migration.view']],
    ['methods' => ['PATCH'], 'route' => '/connections/{public_id}', 'controller' => TrelloMigrationController::class, 'action' => 'updateConnection', 'auth' => true, 'required_permissions' => ['module.trello-migration.manage', 'module.trello-migration.secret_manage']],
    ['methods' => ['DELETE'], 'route' => '/connections/{public_id}', 'controller' => TrelloMigrationController::class, 'action' => 'deleteConnection', 'auth' => true, 'required_permissions' => ['module.trello-migration.delete']],
    ['methods' => ['POST'], 'route' => '/connections/{public_id}/test', 'controller' => TrelloMigrationController::class, 'action' => 'testConnection', 'auth' => true, 'required_permissions' => ['module.trello-migration.manage']],
    ['methods' => ['POST'], 'route' => '/connections/{public_id}/discover', 'controller' => TrelloMigrationController::class, 'action' => 'discover', 'auth' => true, 'required_permissions' => ['module.trello-migration.run']],
    ['methods' => ['GET'], 'route' => '/connections/{public_id}/user-mappings', 'controller' => TrelloMigrationController::class, 'action' => 'listUserMappings', 'auth' => true, 'required_permissions' => ['module.trello-migration.view']],
    ['methods' => ['PATCH'], 'route' => '/connections/{public_id}/user-mappings/{mapping_id}', 'controller' => TrelloMigrationController::class, 'action' => 'updateUserMapping', 'auth' => true, 'required_permissions' => ['module.trello-migration.manage']],
    ['methods' => ['GET'], 'route' => '/connections/{public_id}/board-configs', 'controller' => TrelloMigrationController::class, 'action' => 'listBoardConfigs', 'auth' => true, 'required_permissions' => ['module.trello-migration.view']],
    ['methods' => ['PUT'], 'route' => '/connections/{public_id}/board-configs/{board_id}', 'controller' => TrelloMigrationController::class, 'action' => 'saveBoardConfig', 'auth' => true, 'required_permissions' => ['module.trello-migration.manage']],
    ['methods' => ['GET'], 'route' => '/jobs', 'controller' => TrelloMigrationController::class, 'action' => 'listJobs', 'auth' => true, 'required_permissions' => ['module.trello-migration.view']],
    ['methods' => ['POST'], 'route' => '/jobs', 'controller' => TrelloMigrationController::class, 'action' => 'createJob', 'auth' => true, 'required_permissions' => ['module.trello-migration.run', 'project.manage', 'task.manage']],
    ['methods' => ['GET'], 'route' => '/jobs/{public_id}', 'controller' => TrelloMigrationController::class, 'action' => 'getJob', 'auth' => true, 'required_permissions' => ['module.trello-migration.view']],
    ['methods' => ['POST'], 'route' => '/jobs/{public_id}/run', 'controller' => TrelloMigrationController::class, 'action' => 'startJob', 'auth' => true, 'required_permissions' => ['module.trello-migration.run']],
    ['methods' => ['POST'], 'route' => '/jobs/{public_id}/pause', 'controller' => TrelloMigrationController::class, 'action' => 'pauseJob', 'auth' => true, 'required_permissions' => ['module.trello-migration.run']],
    ['methods' => ['POST'], 'route' => '/jobs/{public_id}/resume', 'controller' => TrelloMigrationController::class, 'action' => 'resumeJob', 'auth' => true, 'required_permissions' => ['module.trello-migration.run']],
    ['methods' => ['POST'], 'route' => '/jobs/{public_id}/cancel', 'controller' => TrelloMigrationController::class, 'action' => 'cancelJob', 'auth' => true, 'required_permissions' => ['module.trello-migration.run']],
    ['methods' => ['POST'], 'route' => '/jobs/{public_id}/retry-failed', 'controller' => TrelloMigrationController::class, 'action' => 'retryFailed', 'auth' => true, 'required_permissions' => ['module.trello-migration.run']],
    ['methods' => ['POST'], 'route' => '/jobs/{public_id}/rollback', 'controller' => TrelloMigrationController::class, 'action' => 'rollbackJob', 'auth' => true, 'required_permissions' => ['module.trello-migration.delete']],
    ['methods' => ['GET'], 'route' => '/jobs/{public_id}/items', 'controller' => TrelloMigrationController::class, 'action' => 'listJobItems', 'auth' => true, 'required_permissions' => ['module.trello-migration.view']],
    ['methods' => ['GET'], 'route' => '/jobs/{public_id}/logs', 'controller' => TrelloMigrationController::class, 'action' => 'listJobLogs', 'auth' => true, 'required_permissions' => ['module.trello-migration.view']],
    ['methods' => ['GET'], 'route' => '/jobs/{public_id}/report', 'controller' => TrelloMigrationController::class, 'action' => 'getReport', 'auth' => true, 'required_permissions' => ['module.trello-migration.report_view']],
    ['methods' => ['POST'], 'route' => '/webhooks/{webhook_public_id}', 'controller' => TrelloMigrationController::class, 'action' => 'receiveWebhook', 'auth' => false],
    ['methods' => ['POST'], 'route' => '/connections/{public_id}/webhooks', 'controller' => TrelloMigrationController::class, 'action' => 'createWebhook', 'auth' => true, 'required_permissions' => ['module.trello-migration.manage', 'module.trello-migration.secret_manage']],
    ['methods' => ['DELETE'], 'route' => '/webhooks/{webhook_public_id}', 'controller' => TrelloMigrationController::class, 'action' => 'deleteWebhook', 'auth' => true, 'required_permissions' => ['module.trello-migration.manage']],
    ['methods' => ['HEAD'], 'route' => '/webhooks/{webhook_public_id}', 'controller' => TrelloMigrationController::class, 'action' => 'validateWebhook', 'auth' => false],
];
