<?php
declare(strict_types=1);

namespace Module\Crm\Dropbox\Mcp;

use Api\System\Library\Container;
use Api\System\Library\Module\Mcp\ModuleMcpToolInterface;
use Api\System\Library\Module\ModuleExecutionContext;
use Module\Crm\Dropbox\Service\DropboxStorageService;
use Throwable;

final class DropboxStatusTool implements ModuleMcpToolInterface
{
    public function execute(array $arguments, ModuleExecutionContext $context, Container $container): array
    {
        if ($context->organizationId <= 0) return ['provider' => 'Dropbox', 'connected' => false, 'error' => 'WORKSPACE_REQUIRED'];
        try {
            $state = (new DropboxStorageService($container->get('db.pdo'), $context->organizationId))->getStatus();
            $state['provider'] = 'Dropbox';
            $state['status'] = !empty($state['connected']) ? 'connected' : (!empty($state['configured']) ? 'not_checked' : 'not_configured');
            return $state;
        } catch (Throwable) {
            return ['provider' => 'Dropbox', 'connected' => false, 'error' => 'STATUS_UNAVAILABLE'];
        }
    }
}
