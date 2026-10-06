<?php
declare(strict_types=1);

namespace Module\Crm\StarterKits\Mcp;

use Api\System\Library\Module\Mcp\ModuleMcpToolInterface;
use Module\Crm\StarterKits\Service\StarterKitService;
use Api\System\Library\Database\DatabaseConnectionPool;

final class PreviewKitTool implements ModuleMcpToolInterface
{
    public function execute(array $arguments, array $context): array
    {
        $kitId = (string)($arguments['kit_id'] ?? '');
        $orgId = (int)($context['organization_id'] ?? 1);
        $locale = (string)($arguments['locale'] ?? ($context['user_locale'] ?? 'ru-ru'));

        $db = DatabaseConnectionPool::getConnection();
        $service = new StarterKitService($db);
        $preview = $service->preview($kitId, $orgId, $locale);

        return [
            'ok' => true,
            'preview' => $preview,
        ];
    }
}
