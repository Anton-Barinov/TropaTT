<?php
declare(strict_types=1);

use Module\Crm\TelegramBot\Web\Controller\TelegramBotWebController;

return [
    'GET' => [
        'telegram-bot' => [TelegramBotWebController::class, 'index'],
    ],
];
