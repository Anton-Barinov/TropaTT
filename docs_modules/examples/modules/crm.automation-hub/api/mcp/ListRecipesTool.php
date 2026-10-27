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
        $orgId = (int)($context['organization_id'] ?? $context['user']['organization_id'] ?? 0);
        if ($orgId < 1) {
            throw new \RuntimeException('Active workspace is required.');
        }
        $db = DatabaseConnectionPool::getConnection();
        $service = new AutomationHubService($db);

        return [
            'ok' => true,
            'recipes' => $service->listRecipes($orgId),
        ];
    }
}
