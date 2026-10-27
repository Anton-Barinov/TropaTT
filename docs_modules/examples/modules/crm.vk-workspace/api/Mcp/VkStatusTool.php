<?php

declare(strict_types=1);

namespace Module\Crm\VkWorkspace\Mcp;

use Api\System\Library\Container;
use Api\System\Library\Module\Mcp\ModuleMcpToolInterface;
use Api\System\Library\Module\ModuleExecutionContext;

final class VkStatusTool implements ModuleMcpToolInterface
{
    public function execute(array $arguments, ModuleExecutionContext $context, Container $container): array
    {
        if ($context->organizationId <= 0 || $context->organizationPublicId === null) {
            return ['success' => false, 'status' => 'unavailable', 'error' => 'ACTIVE_WORKSPACE_REQUIRED'];
        }

        return [
            'success' => true,
            'provider' => 'VK WorkSpace',
            'status' => 'provider_api_unavailable',
            'configured' => false,
            'connected' => false,
            'features' => ['calls' => false, 'calendar' => false, 'mail' => false, 'disk' => false, 'teams' => false],
            'message' => 'VK WorkSpace integration is not available yet. No provider operation was performed.',
        ];
    }
}
