<?php
declare(strict_types=1);

namespace Module\Crm\StorageConnectors\Mcp;

use Api\System\Library\Container;
use Api\System\Library\Module\Mcp\ModuleMcpToolInterface;
use Api\System\Library\Module\ModuleExecutionContext;
use Module\Crm\StorageConnectors\Service\StorageManagerService;
use Throwable;

final class ConfigStatusTool implements ModuleMcpToolInterface
{
    public function execute(array $arguments, ModuleExecutionContext $context, Container $container): array
    {
        if ($context->organizationId <= 0) return ['ok' => false, 'error' => 'WORKSPACE_REQUIRED'];
        try {
            $config = (new StorageManagerService($container->get('db.pdo')))->getConfig($context->organizationId);
            return ['ok' => true, 'has_config' => $config !== null, 'config' => $config];
        } catch (Throwable) {
            return ['ok' => false, 'error' => 'STORAGE_STATUS_UNAVAILABLE'];
        }
    }
}
