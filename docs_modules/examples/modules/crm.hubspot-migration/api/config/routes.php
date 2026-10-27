<?php

declare(strict_types=1);

use Module\Crm\HubSpotMigration\Controller\HubSpotMigrationApiController;

return [
    ['methods' => ['GET'], 'route' => '/status', 'controller' => HubSpotMigrationApiController::class, 'action' => 'getStatus', 'auth' => true, 'workspace_required' => true, 'required_permissions' => ['client.view']],
    ['methods' => ['POST'], 'route' => '/sessions', 'controller' => HubSpotMigrationApiController::class, 'action' => 'createSession', 'auth' => true, 'workspace_required' => true, 'required_permissions' => ['client.manage']],
    ['methods' => ['POST'], 'route' => '/sessions/contacts', 'controller' => HubSpotMigrationApiController::class, 'action' => 'stageContacts', 'auth' => true, 'workspace_required' => true, 'required_permissions' => ['client.manage']],
    ['methods' => ['GET'], 'route' => '/sessions/{session_id}', 'controller' => HubSpotMigrationApiController::class, 'action' => 'getSession', 'auth' => true, 'workspace_required' => true, 'required_permissions' => ['client.view']],
    ['methods' => ['POST'], 'route' => '/sessions/{session_id}/import', 'controller' => HubSpotMigrationApiController::class, 'action' => 'importBatch', 'auth' => true, 'workspace_required' => true, 'required_permissions' => ['client.manage']],
    ['methods' => ['POST'], 'route' => '/sessions/{session_id}/retry-errors', 'controller' => HubSpotMigrationApiController::class, 'action' => 'retryFailedRecords', 'auth' => true, 'workspace_required' => true, 'required_permissions' => ['client.manage']],
    ['methods' => ['POST'], 'route' => '/sessions/{session_id}/discard-pending', 'controller' => HubSpotMigrationApiController::class, 'action' => 'discardPending', 'auth' => true, 'workspace_required' => true, 'required_permissions' => ['client.manage']],
    ['methods' => ['POST'], 'route' => '/sessions/{session_id}/rollback', 'controller' => HubSpotMigrationApiController::class, 'action' => 'rollback', 'auth' => true, 'workspace_required' => true, 'required_permissions' => ['client.manage']],
];
