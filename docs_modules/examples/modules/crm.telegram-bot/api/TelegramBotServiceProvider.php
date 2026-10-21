<?php
declare(strict_types=1);

namespace Module\Crm\TelegramBot;

use Api\System\Library\Container;
use Api\System\Library\Module\AbstractModuleServiceProvider;

final class TelegramBotServiceProvider extends AbstractModuleServiceProvider
{
    public function register(Container $container): void
    {
    }

    public function boot(Container $container): void
    {
    }

    /**
     * @return array<int, array{route: string, label: string, icon: string, permission: string|null, parent: string|null}>
     */
    public function getMenuItems(): array
    {
        return [
            [
                'route' => 'module-telegram-bot',
                'label' => 'Telegram Бот',
                'icon' => '<i class="fa-brands fa-telegram"></i>',
                'permission' => 'settings.edit',
                'parent' => null,
            ],
        ];
    }
}
