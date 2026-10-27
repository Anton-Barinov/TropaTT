<?php
declare(strict_types=1);

namespace Module\Crm\StorageConnectors\Mcp;

use Api\System\Library\Container;
use Api\System\Library\Module\Mcp\ModuleMcpToolInterface;
use Api\System\Library\Module\ModuleExecutionContext;
use Module\Crm\StorageConnectors\Service\StorageManagerService;

final class PreflightTool implements ModuleMcpToolInterface
{
    public function execute(array $arguments, ModuleExecutionContext $context, Container $container): array
    {
        if ($context->organizationId <= 0) return ['ok' => false, 'error' => 'WORKSPACE_REQUIRED'];
        return (new StorageManagerService($container->get('db.pdo')))->preflight();
    }
}
