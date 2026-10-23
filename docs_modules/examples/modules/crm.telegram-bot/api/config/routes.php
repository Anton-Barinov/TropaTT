<?php
declare(strict_types=1);

use Module\Crm\TelegramBot\Api\Controller\TelegramBotApiController;

return [
    ['methods' => ['GET'], 'route' => '/status', 'controller' => TelegramBotApiController::class, 'action' => 'getStatus', 'auth' => true],
    ['methods' => ['POST'], 'route' => '/config', 'controller' => TelegramBotApiController::class, 'action' => 'saveConfig', 'auth' => true],
    ['methods' => ['POST'], 'route' => '/test-connection', 'controller' => TelegramBotApiController::class, 'action' => 'testConnection', 'auth' => true],
    ['methods' => ['POST'], 'route' => '/set-webhook', 'controller' => TelegramBotApiController::class, 'action' => 'setWebhook', 'auth' => true],
    ['methods' => ['POST'], 'route' => '/delete-webhook', 'controller' => TelegramBotApiController::class, 'action' => 'deleteWebhook', 'auth' => true],
    ['methods' => ['POST'], 'route' => '/poll', 'controller' => TelegramBotApiController::class, 'action' => 'pollUpdates', 'auth' => true],
    ['methods' => ['POST'], 'route' => '/send-message', 'controller' => TelegramBotApiController::class, 'action' => 'sendTestMessage', 'auth' => true],
    ['methods' => ['POST'], 'route' => '/import-env', 'controller' => TelegramBotApiController::class, 'action' => 'importEnv', 'auth' => true],
    ['methods' => ['GET'], 'route' => '/bindings', 'controller' => TelegramBotApiController::class, 'action' => 'listBindings', 'auth' => true],
    ['methods' => ['DELETE'], 'route' => '/bindings/{id}', 'controller' => TelegramBotApiController::class, 'action' => 'deleteBinding', 'auth' => true],
    ['methods' => ['GET'], 'route' => '/recent-updates', 'controller' => TelegramBotApiController::class, 'action' => 'getRecentUpdates', 'auth' => true],
    ['methods' => ['POST'], 'route' => '/bind-request', 'controller' => TelegramBotApiController::class, 'action' => 'requestBinding', 'auth' => true],
    ['methods' => ['GET'], 'route' => '/organizations', 'controller' => TelegramBotApiController::class, 'action' => 'listOrganizations', 'auth' => true],
    ['methods' => ['GET'], 'route' => '/projects', 'controller' => TelegramBotApiController::class, 'action' => 'listProjects', 'auth' => true],
    ['methods' => ['GET'], 'route' => '/dialogs', 'controller' => TelegramBotApiController::class, 'action' => 'listDialogs', 'auth' => true],
    ['methods' => ['GET'], 'route' => '/dialogs/{id}', 'controller' => TelegramBotApiController::class, 'action' => 'getDialog', 'auth' => true],
    ['methods' => ['POST'], 'route' => '/dialogs/{id}/reply', 'controller' => TelegramBotApiController::class, 'action' => 'replyDialog', 'auth' => true],
    ['methods' => ['POST'], 'route' => '/dialogs/{id}/status', 'controller' => TelegramBotApiController::class, 'action' => 'updateDialogStatus', 'auth' => true],
    ['methods' => ['POST'], 'route' => '/dialogs/{id}/create-task', 'controller' => TelegramBotApiController::class, 'action' => 'createTaskFromDialog', 'auth' => true],
    ['methods' => ['POST'], 'route' => '/webhook/{org_id}', 'controller' => TelegramBotApiController::class, 'action' => 'handleWebhook', 'auth' => false],
];
