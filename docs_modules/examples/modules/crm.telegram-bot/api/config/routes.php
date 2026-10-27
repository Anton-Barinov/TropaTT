<?php
declare(strict_types=1);

use Module\Crm\TelegramBot\Api\Controller\TelegramBotApiController;

// Keep the public Telegram webhook separate from authenticated workspace APIs.
// Every authenticated route is scoped to the active workspace and has a
// capability check; the central router accepts any one listed capability.
return [
    ['methods' => ['GET'], 'route' => '/status', 'controller' => TelegramBotApiController::class, 'action' => 'getStatus', 'auth' => true, 'workspace_required' => true, 'required_permissions' => ['settings.view']],
    ['methods' => ['POST'], 'route' => '/config', 'controller' => TelegramBotApiController::class, 'action' => 'saveConfig', 'auth' => true, 'workspace_required' => true, 'required_permissions' => ['settings.edit']],
    ['methods' => ['POST'], 'route' => '/test-connection', 'controller' => TelegramBotApiController::class, 'action' => 'testConnection', 'auth' => true, 'workspace_required' => true, 'required_permissions' => ['settings.edit']],
    ['methods' => ['POST'], 'route' => '/set-webhook', 'controller' => TelegramBotApiController::class, 'action' => 'setWebhook', 'auth' => true, 'workspace_required' => true, 'required_permissions' => ['settings.edit']],
    ['methods' => ['POST'], 'route' => '/delete-webhook', 'controller' => TelegramBotApiController::class, 'action' => 'deleteWebhook', 'auth' => true, 'workspace_required' => true, 'required_permissions' => ['settings.edit']],
    ['methods' => ['POST'], 'route' => '/poll', 'controller' => TelegramBotApiController::class, 'action' => 'pollUpdates', 'auth' => true, 'workspace_required' => true, 'required_permissions' => ['settings.edit']],
    ['methods' => ['POST'], 'route' => '/send-message', 'controller' => TelegramBotApiController::class, 'action' => 'sendTestMessage', 'auth' => true, 'workspace_required' => true, 'required_permissions' => ['settings.edit']],
    ['methods' => ['POST'], 'route' => '/import-env', 'controller' => TelegramBotApiController::class, 'action' => 'importEnv', 'auth' => true, 'workspace_required' => true, 'required_permissions' => ['settings.edit']],
    ['methods' => ['GET'], 'route' => '/bindings', 'controller' => TelegramBotApiController::class, 'action' => 'listBindings', 'auth' => true, 'workspace_required' => true, 'required_permissions' => ['settings.view']],
    ['methods' => ['DELETE'], 'route' => '/bindings/{id}', 'controller' => TelegramBotApiController::class, 'action' => 'deleteBinding', 'auth' => true, 'workspace_required' => true, 'required_permissions' => ['settings.edit']],
    ['methods' => ['GET'], 'route' => '/recent-updates', 'controller' => TelegramBotApiController::class, 'action' => 'getRecentUpdates', 'auth' => true, 'workspace_required' => true, 'required_permissions' => ['settings.view']],
    ['methods' => ['POST'], 'route' => '/bind-request', 'controller' => TelegramBotApiController::class, 'action' => 'requestBinding', 'auth' => true, 'workspace_required' => true, 'required_permissions' => ['settings.view']],
    ['methods' => ['GET'], 'route' => '/organizations', 'controller' => TelegramBotApiController::class, 'action' => 'listOrganizations', 'auth' => true, 'workspace_required' => true, 'required_permissions' => ['settings.view']],
    ['methods' => ['GET'], 'route' => '/projects', 'controller' => TelegramBotApiController::class, 'action' => 'listProjects', 'auth' => true, 'workspace_required' => true, 'required_permissions' => ['settings.view']],
    ['methods' => ['GET'], 'route' => '/dialogs', 'controller' => TelegramBotApiController::class, 'action' => 'listDialogs', 'auth' => true, 'workspace_required' => true, 'required_permissions' => ['intake.view']],
    ['methods' => ['GET'], 'route' => '/dialogs/{id}', 'controller' => TelegramBotApiController::class, 'action' => 'getDialog', 'auth' => true, 'workspace_required' => true, 'required_permissions' => ['intake.view']],
    ['methods' => ['POST'], 'route' => '/dialogs/{id}/reply', 'controller' => TelegramBotApiController::class, 'action' => 'replyDialog', 'auth' => true, 'workspace_required' => true, 'required_permissions' => ['intake.manage']],
    ['methods' => ['POST'], 'route' => '/dialogs/{id}/status', 'controller' => TelegramBotApiController::class, 'action' => 'updateDialogStatus', 'auth' => true, 'workspace_required' => true, 'required_permissions' => ['intake.manage']],
    ['methods' => ['POST'], 'route' => '/dialogs/{id}/create-task', 'controller' => TelegramBotApiController::class, 'action' => 'createTaskFromDialog', 'auth' => true, 'workspace_required' => true, 'required_permissions' => ['task.manage']],
    ['methods' => ['POST'], 'route' => '/webhook/{org_id}', 'controller' => TelegramBotApiController::class, 'action' => 'handleWebhook', 'auth' => false, 'workspace_required' => false],
];
