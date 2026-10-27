<?php
declare(strict_types=1);

namespace Module\Crm\GoogleDrive\Mcp;

use Api\System\Library\Container;
use Api\System\Library\Module\Mcp\ModuleMcpToolInterface;
use Api\System\Library\Module\ModuleExecutionContext;
use Module\Crm\GoogleDrive\Service\GoogleDriveStorageService;
use Throwable;

final class DriveStatusTool implements ModuleMcpToolInterface
{
    public function execute(array $arguments, ModuleExecutionContext $context, Container $container): array
    {
        if ($context->organizationId <= 0) return ['provider' => 'Google Drive', 'status' => 'unavailable', 'connected' => false, 'error' => 'WORKSPACE_REQUIRED'];
        try {
            $state = (new GoogleDriveStorageService($container->get('db.pdo'), $context->organizationId))->getStatus();
            $state['provider'] = 'Google Drive';
            $state['status'] = !empty($state['connected']) ? 'connected' : (!empty($state['configured']) ? 'not_checked' : 'not_configured');
            return $state;
        } catch (Throwable) {
            return ['provider' => 'Google Drive', 'status' => 'unavailable', 'connected' => false, 'error' => 'STATUS_UNAVAILABLE'];
        }
    }
}
