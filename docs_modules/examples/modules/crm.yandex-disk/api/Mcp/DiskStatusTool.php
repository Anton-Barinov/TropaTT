<?php

declare(strict_types=1);

namespace Module\Crm\YandexDisk\Mcp;

use Api\System\Library\Container;
use Api\System\Library\Module\Mcp\ModuleMcpToolInterface;
use Api\System\Library\Module\ModuleExecutionContext;

final class DiskStatusTool implements ModuleMcpToolInterface
{
    public function execute(array $arguments, ModuleExecutionContext $context, Container $container): array
    {
        if ($context->organizationId <= 0 || $context->organizationPublicId === null) {
            return ['provider' => 'Yandex Disk', 'status' => 'unavailable', 'error' => 'ACTIVE_WORKSPACE_REQUIRED'];
        }

        return [
            'provider' => 'Yandex Disk',
            'status' => 'not_configured',
            'connected' => false,
            'upload_available' => false,
            'public_links_available' => false,
            'message' => 'Yandex Disk connection and upload are not implemented; no operations were performed.',
        ];
    }
}
