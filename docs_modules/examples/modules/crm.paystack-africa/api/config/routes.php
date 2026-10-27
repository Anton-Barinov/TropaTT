<?php
declare(strict_types=1);

use Module\Crm\PaystackAfrica\Controller\PaystackAfricaApiController;

return [
    ['methods' => ['GET'], 'route' => '/status', 'controller' => PaystackAfricaApiController::class, 'action' => 'getStatus', 'auth' => true, 'workspace_required' => true, 'required_permissions' => ['settings.view']],
    ['methods' => ['GET'], 'route' => '/settings', 'controller' => PaystackAfricaApiController::class, 'action' => 'getStatus', 'auth' => true, 'workspace_required' => true, 'required_permissions' => ['settings.view']],
    ['methods' => ['POST'], 'route' => '/settings', 'controller' => PaystackAfricaApiController::class, 'action' => 'saveSettings', 'auth' => true, 'workspace_required' => true, 'required_permissions' => ['settings.edit']],
    ['methods' => ['POST'], 'route' => '/connection/test', 'controller' => PaystackAfricaApiController::class, 'action' => 'testConnection', 'auth' => true, 'workspace_required' => true, 'required_permissions' => ['settings.edit']],
    ['methods' => ['GET'], 'route' => '/transactions', 'controller' => PaystackAfricaApiController::class, 'action' => 'listTransactions', 'auth' => true, 'workspace_required' => true, 'required_permissions' => ['finance.view']],
    ['methods' => ['POST'], 'route' => '/transactions', 'controller' => PaystackAfricaApiController::class, 'action' => 'initializeTransaction', 'auth' => true, 'workspace_required' => true, 'required_permissions' => ['finance.edit']],
    ['methods' => ['POST'], 'route' => '/transactions/verify', 'controller' => PaystackAfricaApiController::class, 'action' => 'verifyTransaction', 'auth' => true, 'workspace_required' => true, 'required_permissions' => ['finance.view']],
    ['methods' => ['POST'], 'route' => '/webhook/{webhook_id}', 'controller' => PaystackAfricaApiController::class, 'action' => 'webhook', 'auth' => false],
];
