<?php
declare(strict_types=1);

namespace Api\System\Library\Module\Mcp;

/**
 * Value object representing a validated module-owned MCP tool declaration.
 */
final class ModuleMcpToolDefinition
{
    /**
     * @param string $name Fully-qualified namespaced name (e.g. "crm_ecommerce_gateway.sync_orders" or "vendor.module.action")
     * @param string $moduleName Owning module name in module_registry (e.g. "crm.ecommerce-gateway")
     * @param string $description Concise, human-readable description for LLM discovery
     * @param array<string, mixed> $inputSchema JSON Schema with type=object and additionalProperties=false
     * @param array<int, string> $permissions List of required CRM permission codes
     * @param string $permissionMode 'all' | 'any' | 'none'
     * @param string $handler Class or Class::method allowlisted under module namespace
     * @param bool $workspaceRequired Whether tool execution requires an authoritative workspace
     */
    public function __construct(
        public readonly string $name,
        public readonly string $moduleName,
        public readonly string $description,
        public readonly array $inputSchema,
        public readonly array $permissions,
        public readonly string $permissionMode = 'all',
        public readonly string $handler = '',
        public readonly bool $workspaceRequired = true,
    ) {
    }

    /**
     * Convert to standard MCP tool schema array for tools/list.
     *
     * @return array<string, mixed>
     */
    public function toMcpTool(): array
    {
        return [
            'name' => $this->name,
            'description' => $this->description,
            'inputSchema' => $this->inputSchema,
            'module' => $this->moduleName,
        ];
    }
}
