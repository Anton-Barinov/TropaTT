<?php
declare(strict_types=1);

use Module\Crm\WhatsAppBusiness\Api\Controller\WhatsAppBusinessApiController;

return [
    ['methods' => ['POST'], 'route' => '/webhook/{org_id}', 'controller' => WhatsAppBusinessApiController::class, 'action' => 'handleWebhook', 'auth' => false],
    ['methods' => ['GET'], 'route' => '/inbox', 'controller' => WhatsAppBusinessApiController::class, 'action' => 'listInbox', 'auth' => true],
    ['methods' => ['POST'], 'route' => '/send-template', 'controller' => WhatsAppBusinessApiController::class, 'action' => 'sendTemplate', 'auth' => true],
];
