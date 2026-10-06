<?php
declare(strict_types=1);

namespace Module\Crm\StarterKits\Mcp;

use Api\System\Library\Module\Mcp\ModuleMcpToolInterface;
use Module\Crm\StarterKits\Service\StarterKitService;
use Api\System\Library\Database\DatabaseConnectionPool;

final class ApplyKitTool implements ModuleMcpToolInterface
{
    public function execute(array $arguments, array $context): array
    {
        $kitId = (string)($arguments['kit_id'] ?? '');
        $orgId = (int)($context['organization_id'] ?? 1);
        $userId = isset($context['user_id']) ? (int)$context['user_id'] : null;
        $components = isset($arguments['selected_components']) ? (array)$arguments['selected_components'] : null;
        $locale = (string)($arguments['locale'] ?? ($context['user_locale'] ?? 'ru-ru'));

        $db = DatabaseConnectionPool::getConnection();
        $service = new StarterKitService($db);
        $result = $service->apply($kitId, $orgId, $userId, $components, $locale);

        return $result;
    }
}
