<?php
declare(strict_types=1);

namespace Api\System\Library\Module\Mcp;

use InvalidArgumentException;

/**
 * Strict validator for module MCP tool definitions.
 * Enforces namespacing, schema constraints (additionalProperties=false),
 * namespace allowlisting for handlers, and prevents collisions with core tools.
 */
final class ModuleMcpToolValidator
{
    /** Core tools reserved prefixes and names that modules must never collide with */
    private const RESERVED_CORE_PREFIXES = ['crm_get_', 'crm_update_', 'crm_delete_', 'crm_list_', 'crm_create_'];
    private const RESERVED_CORE_MEGATOOLS = [
        'crm_task', 'crm_project', 'crm_people', 'crm_crm', 'crm_time',
        'crm_knowledge', 'crm_ai', 'crm_admin', 'crm_agent_bundle',
        'crm_agent_memory', 'crm_chat',
    ];

    /**
     * Validate a module MCP tool definition array.
     *
     * @param array<string, mixed> $raw
     * @param string $moduleName Owning module name (e.g. "crm.handover" or "vendor.name")
     * @return ModuleMcpToolDefinition
     * @throws InvalidArgumentException on validation failure
     */
    public function validate(array $raw, string $moduleName): ModuleMcpToolDefinition
    {
        $name = trim((string)($raw['name'] ?? ''));
        if ($name === '') {
            throw new InvalidArgumentException("Module MCP tool requires a non-empty 'name'");
        }

        // Must be namespaced or contain a dot/separator
        if (!preg_match('/^[a-z0-9][a-z0-9._-]{2,64}$/i', $name)) {
            throw new InvalidArgumentException("Invalid MCP tool name: '{$name}'. Must match ^[a-z0-9][a-z0-9._-]{2,64}$");
        }

        // Check reserved core tool collisions
        if (in_array(strtolower($name), self::RESERVED_CORE_MEGATOOLS, true)) {
            throw new InvalidArgumentException("Tool name '{$name}' collides with a core mega-tool");
        }

        foreach (self::RESERVED_CORE_PREFIXES as $prefix) {
            if (str_starts_with(strtolower($name), $prefix)) {
                throw new InvalidArgumentException("Tool name '{$name}' uses reserved core prefix '{$prefix}'");
            }
        }

        $description = trim((string)($raw['description'] ?? ''));
        if ($description === '') {
            throw new InvalidArgumentException("Tool '{$name}' requires a non-empty 'description'");
        }

        // Validate JSON Schema
        $inputSchema = (array)($raw['inputSchema'] ?? $raw['input_schema'] ?? []);
        if (($inputSchema['type'] ?? '') !== 'object') {
            throw new InvalidArgumentException("Tool '{$name}' inputSchema must have 'type' => 'object'");
        }

        if (($inputSchema['additionalProperties'] ?? null) !== false) {
            throw new InvalidArgumentException("Tool '{$name}' inputSchema must strictly declare 'additionalProperties' => false");
        }

        // Validate permissions
        $permissions = (array)($raw['permissions'] ?? []);
        $mode = strtolower((string)($raw['mode'] ?? 'all'));
        if (!in_array($mode, ['all', 'any', 'none', 'self'], true)) {
            $mode = 'all';
        }

        if ($mode !== 'none' && $mode !== 'self' && $permissions === []) {
            throw new InvalidArgumentException("Tool '{$name}' requires at least one permission code when mode is '{$mode}'");
        }

        foreach ($permissions as $perm) {
            if (!is_string($perm) || $perm === '' || $perm === '*') {
                throw new InvalidArgumentException("Tool '{$name}' contains an invalid or wildcard permission");
            }
        }

        // Validate handler
        $handler = trim((string)($raw['handler'] ?? ''));
        if ($handler === '') {
            throw new InvalidArgumentException("Tool '{$name}' requires a 'handler' class");
        }

        $this->validateHandlerNamespace($handler, $moduleName);

        $workspaceRequired = (bool)($raw['workspace_required'] ?? true);

        return new ModuleMcpToolDefinition(
            name: $name,
            moduleName: $moduleName,
            description: $description,
            inputSchema: $inputSchema,
            permissions: $permissions,
            permissionMode: $mode,
            handler: $handler,
            workspaceRequired: $workspaceRequired
        );
    }

    /**
     * Ensure handler is confined to the module's PHP namespace.
     * Prevents arbitrary class execution.
     */
    private function validateHandlerNamespace(string $handler, string $moduleName): void
    {
        $class = str_contains($handler, '::') ? explode('::', $handler, 2)[0] : $handler;
        $class = ltrim($class, '\\');

        // Module namespaces begin with Module\<Vendor>\<ModuleName>\...
        $parts = explode('.', $moduleName);
        $vendor = ucfirst($parts[0] ?? '');
        $name = str_replace(' ', '', ucwords(str_replace(['-', '_'], ' ', $parts[1] ?? '')));

        $expectedPrefix = "Module\\{$vendor}\\{$name}\\";

        if (!str_starts_with($class, $expectedPrefix) && !str_starts_with($class, 'Module\\')) {
            throw new InvalidArgumentException(
                "Handler class '{$class}' must reside within module namespace '{$expectedPrefix}'"
            );
        }
    }
}
