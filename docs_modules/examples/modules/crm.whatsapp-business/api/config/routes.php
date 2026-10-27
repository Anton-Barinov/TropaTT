<?php
declare(strict_types=1);

use Module\Crm\WhatsAppBusiness\Api\Controller\WhatsAppBusinessApiController;

return [
    ['methods' => ['GET'], 'route' => '/webhook/{org_id}', 'controller' => WhatsAppBusinessApiController::class, 'action' => 'verifyWebhook', 'auth' => false],
    ['methods' => ['POST'], 'route' => '/webhook/{org_id}', 'controller' => WhatsAppBusinessApiController::class, 'action' => 'handleWebhook', 'auth' => false],
    ['methods' => ['GET'], 'route' => '/config', 'controller' => WhatsAppBusinessApiController::class, 'action' => 'getConfigStatus', 'auth' => true, 'workspace_required' => true, 'required_permissions' => ['settings.view']],
    ['methods' => ['POST'], 'route' => '/config', 'controller' => WhatsAppBusinessApiController::class, 'action' => 'saveConfig', 'auth' => true, 'workspace_required' => true, 'required_permissions' => ['settings.edit']],
    ['methods' => ['GET'], 'route' => '/inbox', 'controller' => WhatsAppBusinessApiController::class, 'action' => 'listInbox', 'auth' => true, 'workspace_required' => true, 'required_permissions' => ['settings.view']],
    ['methods' => ['GET'], 'route' => '/inbox/{conversation_id}', 'controller' => WhatsAppBusinessApiController::class, 'action' => 'getConversation', 'auth' => true, 'workspace_required' => true, 'required_permissions' => ['settings.view']],
    ['methods' => ['POST'], 'route' => '/inbox/{conversation_id}/reply', 'controller' => WhatsAppBusinessApiController::class, 'action' => 'reply', 'auth' => true, 'workspace_required' => true, 'required_permissions' => ['settings.edit'], 'idempotency' => 'required'],
];
