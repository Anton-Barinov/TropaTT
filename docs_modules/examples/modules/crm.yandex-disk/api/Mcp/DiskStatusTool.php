<?php
declare(strict_types=1);

namespace Module\Crm\YandexDisk\Mcp;

use Api\System\Library\Container;
use Api\System\Library\Module\Mcp\ModuleMcpToolInterface;
use Api\System\Library\Module\ModuleExecutionContext;
use Module\Crm\YandexDisk\Service\YandexDiskService;
use Throwable;

final class DiskStatusTool implements ModuleMcpToolInterface
{
    public function execute(array $arguments, ModuleExecutionContext $context, Container $container): array
    {
        if ($context->organizationId <= 0) return ['provider' => 'Yandex Disk', 'connected' => false, 'error' => 'WORKSPACE_REQUIRED'];
        try {
            $state = (new YandexDiskService($container->get('db.pdo'), $context->organizationId))->getStatus();
            $state['provider'] = 'Yandex Disk';
            $state['status'] = !empty($state['connected']) ? 'connected' : (!empty($state['configured']) ? 'not_checked' : 'not_configured');
            return $state;
        } catch (Throwable) {
            return ['provider' => 'Yandex Disk', 'connected' => false, 'error' => 'STATUS_UNAVAILABLE'];
        }
    }
}
