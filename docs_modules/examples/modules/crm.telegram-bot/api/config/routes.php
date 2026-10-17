<?php
declare(strict_types=1);

use Module\Crm\TelegramBot\Api\Controller\TelegramBotApiController;

return [
    ['methods' => ['POST'], 'route' => '/webhook/{org_id}', 'controller' => TelegramBotApiController::class, 'action' => 'handleWebhook', 'auth' => false],
    ['methods' => ['POST'], 'route' => '/bind-request', 'controller' => TelegramBotApiController::class, 'action' => 'requestBinding', 'auth' => true],
];
