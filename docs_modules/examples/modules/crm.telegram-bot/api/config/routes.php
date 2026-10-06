<?php
declare(strict_types=1);

use Module\Crm\TelegramBot\Api\Controller\TelegramBotApiController;

return [
    'POST' => [
        'api/v1/telegram/webhook/{org_id}' => [TelegramBotApiController::class, 'handleWebhook'],
        'api/v1/telegram/bind-request' => [TelegramBotApiController::class, 'requestBinding'],
    ],
];
