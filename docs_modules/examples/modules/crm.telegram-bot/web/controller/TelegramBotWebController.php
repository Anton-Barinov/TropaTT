<?php
declare(strict_types=1);

namespace Module\Crm\TelegramBot\Web\Controller;

use Web\System\Core\Controller;

final class TelegramBotWebController extends Controller
{
    public function index(): void
    {
        $this->render(__DIR__ . '/../template/page/crm_telegram_bot.php', [
            'title' => 'Telegram Бот',
            'route' => 'module-telegram-bot',
        ]);
    }
}
