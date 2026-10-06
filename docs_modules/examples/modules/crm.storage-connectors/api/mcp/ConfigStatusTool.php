<?php
declare(strict_types=1);

namespace Module\Crm\StorageConnectors\Mcp;

use Api\System\Library\Module\Mcp\ModuleMcpToolInterface;
use Module\Crm\StorageConnectors\Service\StorageManagerService;
use Api\System\Library\Database\DatabaseConnectionPool;

final class ConfigStatusTool implements ModuleMcpToolInterface
{
    public function execute(array $arguments, array $context): array
    {
        $orgId = (int)($context['organization_id'] ?? 1);
        $db = DatabaseConnectionPool::getConnection();
        $service = new StorageManagerService($db);
        $config = $service->getConfig($orgId);

        return [
            'ok' => true,
            'has_config' => $config !== null,
            'config' => $config,
        ];
    }
}
