<?php
declare(strict_types=1);

namespace Api\System\Library\Module\Mcp;

use Api\System\Library\Container;
use Api\System\Library\Module\ModuleExecutionContext;

/**
 * Standard contract for module-provided MCP tools.
 */
interface ModuleMcpToolInterface
{
    /**
     * Execute the tool with validated arguments and authorized workspace context.
     *
     * @param array<string, mixed> $arguments Cleaned and validated arguments from the MCP request
     * @param ModuleExecutionContext $context Immutable workspace, actor, and correlation context
     * @param Container $container Dependency injection container
     * @return array<string, mixed> Structured response payload
     */
    public function execute(array $arguments, ModuleExecutionContext $context, Container $container): array;
}
