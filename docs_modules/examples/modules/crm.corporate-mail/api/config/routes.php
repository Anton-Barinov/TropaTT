<?php
declare(strict_types=1);

use Module\Crm\CorporateMail\Api\Controller\CorporateMailApiController;

return [
    ['methods' => ['GET'], 'route' => '/status', 'controller' => CorporateMailApiController::class, 'action' => 'getStatus', 'auth' => true, 'workspace_required' => true, 'required_permissions' => ['settings.view']],
    ['methods' => ['GET'], 'route' => '/mailboxes', 'controller' => CorporateMailApiController::class, 'action' => 'listMailboxes', 'auth' => true, 'workspace_required' => true, 'required_permissions' => ['settings.view']],
    ['methods' => ['GET'], 'route' => '/mailboxes/{public_id}', 'controller' => CorporateMailApiController::class, 'action' => 'getMailbox', 'auth' => true, 'workspace_required' => true, 'required_permissions' => ['settings.view']],
    ['methods' => ['GET'], 'route' => '/mailboxes/{public_id}/threads', 'controller' => CorporateMailApiController::class, 'action' => 'listThreads', 'auth' => true, 'workspace_required' => true, 'required_permissions' => ['corporate_mail.read']],
    ['methods' => ['GET'], 'route' => '/mailboxes/{public_id}/messages', 'controller' => CorporateMailApiController::class, 'action' => 'listMessages', 'auth' => true, 'workspace_required' => true, 'required_permissions' => ['corporate_mail.read']],
    ['methods' => ['POST'], 'route' => '/mailboxes', 'controller' => CorporateMailApiController::class, 'action' => 'saveMailbox', 'auth' => true, 'workspace_required' => true, 'required_permissions' => ['settings.edit']],
    ['methods' => ['DELETE'], 'route' => '/mailboxes/{public_id}', 'controller' => CorporateMailApiController::class, 'action' => 'deleteMailbox', 'auth' => true, 'workspace_required' => true, 'required_permissions' => ['settings.edit']],
    ['methods' => ['POST'], 'route' => '/mailboxes/{public_id}/test', 'controller' => CorporateMailApiController::class, 'action' => 'testMailbox', 'auth' => true, 'workspace_required' => true, 'required_permissions' => ['settings.edit']],
    ['methods' => ['GET'], 'route' => '/mailboxes/{public_id}/sync-failures', 'controller' => CorporateMailApiController::class, 'action' => 'listSyncFailures', 'auth' => true, 'workspace_required' => true, 'required_permissions' => ['settings.view']],
    ['methods' => ['POST'], 'route' => '/mailboxes/{public_id}/sync-failures/retry', 'controller' => CorporateMailApiController::class, 'action' => 'retrySyncFailure', 'auth' => true, 'workspace_required' => true, 'required_permissions' => ['settings.edit']],
    ['methods' => ['POST'], 'route' => '/mailboxes/{public_id}/sync', 'controller' => CorporateMailApiController::class, 'action' => 'syncMailbox', 'auth' => true, 'workspace_required' => true, 'required_permissions' => ['settings.edit']],
    ['methods' => ['POST'], 'route' => '/mailboxes/{public_id}/send', 'controller' => CorporateMailApiController::class, 'action' => 'sendMessage', 'auth' => true, 'workspace_required' => true, 'required_permissions' => ['settings.edit']],
    ['methods' => ['GET'], 'route' => '/mailboxes/{public_id}/send-status', 'controller' => CorporateMailApiController::class, 'action' => 'getSendAttemptStatus', 'auth' => true, 'workspace_required' => true, 'required_permissions' => ['settings.view']],
    ['methods' => ['POST'], 'route' => '/test-connection', 'controller' => CorporateMailApiController::class, 'action' => 'testConnection', 'auth' => true, 'workspace_required' => true, 'required_permissions' => ['settings.edit']],
    ['methods' => ['POST'], 'route' => '/import-env', 'controller' => CorporateMailApiController::class, 'action' => 'importEnv', 'auth' => true, 'workspace_required' => true, 'required_permissions' => ['settings.edit']],
];
