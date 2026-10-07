<?php
declare(strict_types=1);

use Module\Crm\TelegramBot\Web\Controller\TelegramBotWebController;

return [
    'module-telegram-bot' => [TelegramBotWebController::class, 'index'],
];
