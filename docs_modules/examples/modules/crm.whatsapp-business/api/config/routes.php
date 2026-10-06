<?php
declare(strict_types=1);

use Module\Crm\WhatsAppBusiness\Api\Controller\WhatsAppBusinessApiController;

return [
    'POST' => [
        'api/v1/whatsapp/webhook/{org_id}' => [WhatsAppBusinessApiController::class, 'handleWebhook'],
    ],
    'GET' => [
        'api/v1/whatsapp/inbox' => [WhatsAppBusinessApiController::class, 'listInbox'],
    ],
];
