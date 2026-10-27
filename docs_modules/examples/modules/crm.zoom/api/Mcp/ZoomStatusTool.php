<?php

declare(strict_types=1);

namespace Module\Crm\Zoom\Mcp;

use Api\System\Library\Container;
use Api\System\Library\Module\Mcp\ModuleMcpToolInterface;
use Api\System\Library\Module\ModuleExecutionContext;
use Module\Crm\Zoom\Service\ZoomService;
use Throwable;

final class ZoomStatusTool implements ModuleMcpToolInterface
{
    public function execute(array $arguments, ModuleExecutionContext $context, Container $container): array
    {
        if ($context->organizationId <= 0 || $context->organizationPublicId === null) return ['success' => false, 'status' => 'unavailable', 'error' => 'ACTIVE_WORKSPACE_REQUIRED'];
        try {
            $db = $container->get('db.pdo');
            if (!$db instanceof \PDO) return ['success' => false, 'error' => 'DATABASE_UNAVAILABLE'];
            return ['success' => true, 'provider' => 'Zoom', ...(new ZoomService($db, $context->organizationId))->getStatus()];
        } catch (Throwable) {
            return ['success' => false, 'provider' => 'Zoom', 'status' => 'unavailable', 'error' => 'ZOOM_STATUS_UNAVAILABLE'];
        }
    }
}
