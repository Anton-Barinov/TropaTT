<?php
declare(strict_types=1);

use Module\Crm\WhatsAppBusiness\Web\Controller\WhatsAppBusinessWebController;

return [
    'GET' => [
        'whatsapp-inbox' => [WhatsAppBusinessWebController::class, 'index'],
    ],
];
