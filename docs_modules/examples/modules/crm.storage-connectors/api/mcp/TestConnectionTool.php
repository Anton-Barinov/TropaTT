<?php
declare(strict_types=1);

namespace Module\Crm\StorageConnectors\Mcp;

use Api\System\Library\Module\Mcp\ModuleMcpToolInterface;
use Module\Crm\StorageConnectors\Service\StorageManagerService;
use Api\System\Library\Database\DatabaseConnectionPool;

final class TestConnectionTool implements ModuleMcpToolInterface
{
    public function execute(array $arguments, array $context): array
    {
        $orgId = (int)($context['organization_id'] ?? 1);
        $db = DatabaseConnectionPool::getConnection();
        $service = new StorageManagerService($db);
        $adapter = $service->getAdapter($orgId);

        if ($adapter === null) {
            return [
                'ok' => false,
                'message' => 'No active storage provider configured for workspace.',
            ];
        }

        return $adapter->testConnection();
    }
}
