<?php
declare(strict_types=1);

namespace Module\Crm\StarterKits\Mcp;

use Api\System\Library\Module\Mcp\ModuleMcpToolInterface;
use Module\Crm\StarterKits\Service\StarterKitService;
use Api\System\Library\Database\DatabaseConnectionPool;

final class ListKitsTool implements ModuleMcpToolInterface
{
    public function execute(array $arguments, array $context): array
    {
        $db = DatabaseConnectionPool::getConnection();
        $service = new StarterKitService($db);
        $locale = (string)($arguments['locale'] ?? ($context['user_locale'] ?? 'ru-ru'));
        $kits = $service->listKits($locale);

        return [
            'ok' => true,
            'kits' => $kits,
        ];
    }
}
