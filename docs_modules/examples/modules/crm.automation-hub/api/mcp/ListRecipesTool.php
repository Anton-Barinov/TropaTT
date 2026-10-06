<?php
declare(strict_types=1);

namespace Module\Crm\AutomationHub\Mcp;

use Api\System\Library\Module\Mcp\ModuleMcpToolInterface;
use Module\Crm\AutomationHub\Service\AutomationHubService;
use Api\System\Library\Database\DatabaseConnectionPool;

final class ListRecipesTool implements ModuleMcpToolInterface
{
    public function execute(array $arguments, array $context): array
    {
        $orgId = (int)($context['organization_id'] ?? 1);
        $db = DatabaseConnectionPool::getConnection();
        $service = new AutomationHubService($db);

        return [
            'ok' => true,
            'recipes' => $service->listRecipes($orgId),
        ];
    }
}
