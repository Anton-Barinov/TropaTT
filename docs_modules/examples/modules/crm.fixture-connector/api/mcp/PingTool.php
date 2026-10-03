<?php
declare(strict_types=1);

namespace Module\Crm\FixtureConnector\Mcp;

use Api\System\Library\Module\Mcp\ModuleMcpToolInterface;
use Api\System\Library\Module\ModuleExecutionContext;
use Api\System\Library\Container;

final class PingTool implements ModuleMcpToolInterface
{
    /**
     * @param array<string, mixed> $arguments
     * @return array<string, mixed>
     */
    public function execute(array $arguments, ModuleExecutionContext $context, Container $container): array
    {
        return [
            'status' => 'pong',
            'module' => $context->moduleName,
            'organization_id' => $context->organizationId,
            'organization_public_id' => $context->organizationPublicId,
            'actor_user_id' => $context->actorPublicId,
            'execution_mode' => $context->source,
            'echo' => (string)($arguments['echo'] ?? 'pong'),
        ];
    }
}
