<?php
declare(strict_types=1);

namespace Module\Crm\StorageConnectors\Mcp;

use Api\System\Library\Module\Mcp\ModuleMcpToolInterface;
use Module\Crm\StorageConnectors\Service\StorageManagerService;
use Api\System\Library\Database\DatabaseConnectionPool;

final class PreflightTool implements ModuleMcpToolInterface
{
    public function execute(array $arguments, array $context): array
    {
        $db = DatabaseConnectionPool::getConnection();
        $service = new StorageManagerService($db);
        return $service->preflight();
    }
}
