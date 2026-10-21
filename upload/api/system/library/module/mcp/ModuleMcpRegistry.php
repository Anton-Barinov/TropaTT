<?php
declare(strict_types=1);

namespace Api\System\Library\Module\Mcp;

use Api\System\Library\Connector\ConnectorDiagnostics;
use Api\System\Library\Container;
use Api\System\Library\Module\ModuleExecutionContext;
use Api\System\Library\Support\AppLog;
use PDO;
use ReflectionClass;
use ReflectionMethod;
use ReflectionNamedType;
use RuntimeException;
use Throwable;

/**
 * Registry and runtime dispatcher for module-owned MCP tools.
 * Enforces active module checks, RBAC verification, workspace isolation,
 * schema validation, and sensitive data redaction.
 */
final class ModuleMcpRegistry
{
    /** @var array<string, ModuleMcpToolDefinition> */
    private array $registeredTools = [];
    private bool $loaded = false;
    private readonly ModuleMcpToolValidator $validator;
    private readonly ConnectorDiagnostics $diagnostics;

    public function __construct(
        private readonly PDO $pdo,
        private readonly string $modulesDir,
    ) {
        $this->validator = new ModuleMcpToolValidator();
        $this->diagnostics = new ConnectorDiagnostics();
    }

    /**
     * Register a tool definition programmatically (e.g. in tests or providers).
     */
    public function registerTool(array $toolConfig, string $moduleName): ModuleMcpToolDefinition
    {
        $definition = $this->validator->validate($toolConfig, $moduleName);
        $this->registeredTools[$definition->name] = $definition;

        return $definition;
    }

    /**
     * Check if a module is currently active in the database.
     */
    public function isModuleActive(string $moduleName): bool
    {
        try {
            $stmt = $this->pdo->prepare('SELECT is_active FROM module_registry WHERE module_name = :name LIMIT 1');
            $stmt->execute(['name' => $moduleName]);
            $val = $stmt->fetchColumn();

            return (int)$val === 1;
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * Get an active tool definition by name.
     * Returns null if tool does not exist or its module is disabled.
     */
    public function getTool(string $name): ?ModuleMcpToolDefinition
    {
        $this->ensureLoaded();

        $tool = $this->registeredTools[$name] ?? null;
        if (!$tool) {
            return null;
        }

        // Must fail closed immediately if module is deactivated
        if (!$this->isModuleActive($tool->moduleName)) {
            return null;
        }

        return $tool;
    }

    /**
     * Check whether a tool is available and active.
     */
    public function hasTool(string $name): bool
    {
        return $this->getTool($name) !== null;
    }

    /**
     * Get module tools for a requested toolset profile.
     * Note: "core" profile NEVER includes module tools.
     *
     * @param string $profile "all", "modules", or "module:<name>"
     * @param array<string, mixed> $actor Authenticated user/actor
     * @param callable $canCheck Function taking permission code string and returning bool
     * @return array<int, array<string, mixed>> MCP tools array for tools/list
     */
    public function getToolsForProfile(string $profile, array $actor, callable $canCheck): array
    {
        if ($profile === 'core') {
            return [];
        }

        $this->ensureLoaded();

        $result = [];
        $targetModule = null;
        if (str_starts_with($profile, 'module:')) {
            $targetModule = substr($profile, 7);
        } elseif ($profile !== 'all' && $profile !== 'modules') {
            $targetModule = $profile;
        }

        foreach ($this->registeredTools as $tool) {
            if ($targetModule !== null && $tool->moduleName !== $targetModule) {
                continue;
            }

            if (!$this->isModuleActive($tool->moduleName)) {
                continue;
            }

            // Check permissions
            if (!$this->checkPermissions($tool, $canCheck)) {
                continue;
            }

            $result[] = $tool->toMcpTool();
        }

        return $result;
    }

    /**
     * Get all active module toolset profiles with tool names.
     *
     * @param callable $canCheck
     * @return array<string, array{title: string, description: string, tools: array<int, string>}>
     */
    public function getModuleToolsets(callable $canCheck): array
    {
        $this->ensureLoaded();

        $grouped = [];
        foreach ($this->registeredTools as $tool) {
            if (!$this->isModuleActive($tool->moduleName)) {
                continue;
            }

            if (!$this->checkPermissions($tool, $canCheck)) {
                continue;
            }

            $profileKey = 'module:' . $tool->moduleName;
            if (!isset($grouped[$profileKey])) {
                $grouped[$profileKey] = [
                    'title' => 'Module: ' . $tool->moduleName,
                    'description' => "Tools provided by module {$tool->moduleName}",
                    'tools' => [],
                ];
            }
            $grouped[$profileKey]['tools'][] = $tool->name;
        }

        return $grouped;
    }

    /**
     * Execute a module MCP tool under active workspace and permissions checks.
     *
     * @param string $name Tool name
     * @param array<string, mixed> $arguments
     * @param array<string, mixed> $actor
     * @param Container $container
     * @param callable $canCheck
     * @param int|null $explicitOrgId
     * @return array<string, mixed> Standard MCP result or error envelope
     */
    public function executeTool(
        string $name,
        array $arguments,
        array $actor,
        Container $container,
        callable $canCheck,
        ?int $explicitOrgId = null
    ): array {
        $tool = $this->getTool($name);
        if (!$tool) {
            return [
                'isError' => true,
                'content' => [['type' => 'text', 'text' => "Tool '{$name}' not found or its module is disabled."]],
            ];
        }

        // Verify permissions
        if (!$this->checkPermissions($tool, $canCheck)) {
            $required = implode(', ', $tool->permissions);
            return [
                'isError' => true,
                'content' => [['type' => 'text', 'text' => "Insufficient permissions for module tool '{$name}'. Required: {$required}"]],
            ];
        }

        // Resolve workspace context
        $orgId = $explicitOrgId ?? (int)($actor['organization_id'] ?? $actor['user']['organization_id'] ?? 0);
        $orgPublicId = (string)($actor['organization_public_id'] ?? $actor['user']['organization_public_id'] ?? '');

        if ($tool->workspaceRequired && $orgId <= 0) {
            return [
                'isError' => true,
                'content' => [['type' => 'text', 'text' => "Module tool '{$name}' requires an active, authorized workspace."]],
            ];
        }

        if ($orgId > 0 && $orgPublicId === '') {
            return [
                'isError' => true,
                'content' => [['type' => 'text', 'text' => "Module tool '{$name}' requires a complete, authorized workspace context."]],
            ];
        }

        $actorPublicId = (string)($actor['public_id'] ?? $actor['user']['public_id'] ?? 'usr_system');
        // MCP requests are user initiated. A global context is only valid for
        // service jobs, so represent an explicitly allowed global tool without
        // routing through ModuleExecutionContext::global() (which rejects MCP).
        $context = $orgId > 0
            ? ModuleExecutionContext::forWorkspace($tool->moduleName, $orgId, $orgPublicId, $actorPublicId, 'mcp')
            : new ModuleExecutionContext($tool->moduleName, 0, null, $actorPublicId, 'mcp', bin2hex(random_bytes(16)));

        // Resolve and execute handler
        try {
            $handlerClass = $tool->handler;
            $method = 'execute';
            if (str_contains($handlerClass, '::')) {
                [$handlerClass, $method] = explode('::', $handlerClass, 2);
            }

            if (!class_exists($handlerClass)) {
                $moduleDir = $this->modulesDir . '/' . $tool->moduleName;
                $trimmed = preg_replace('/^Module\\\\[^\\\\]+\\\\[^\\\\]+\\\\/', '', $handlerClass);
                $relPath = str_replace('\\', '/', (string)$trimmed) . '.php';
                $candidates = [
                    $moduleDir . '/api/' . $relPath,
                    $moduleDir . '/api/' . lcfirst($relPath),
                    $moduleDir . '/src/' . $relPath,
                    $moduleDir . '/' . $relPath,
                ];
                foreach ($candidates as $cand) {
                    if (is_file($cand)) {
                        require_once $cand;
                        break;
                    }
                }
            }

            if (!class_exists($handlerClass)) {
                return [
                    'isError' => true,
                    'content' => [['type' => 'text', 'text' => "Handler class '{$handlerClass}' not found for tool '{$name}'."]],
                ];
            }

            $instance = $this->instantiateHandler($handlerClass, $container);
            $rawResult = $this->invokeHandler($instance, $method, $arguments, $context, $container);

            if (!is_array($rawResult)) {
                $rawResult = ['result' => $rawResult];
            }

            // Sanitize sensitive secrets from tool output
            $cleanResult = $this->diagnostics->sanitizePayload($rawResult);
            $jsonText = json_encode($cleanResult, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

            return [
                'isError' => false,
                'content' => [['type' => 'text', 'text' => $jsonText]],
                'structuredContent' => $cleanResult,
            ];
        } catch (Throwable $e) {
            // Exception text can contain provider responses, credentials, or
            // user supplied values. Keep it in neither MCP output nor logs.
            AppLog::error("Module MCP tool '{$name}' execution error", [
                'tool' => $name,
                'module' => $tool->moduleName,
                'exception' => get_class($e),
            ]);

            return [
                'isError' => true,
                'content' => [['type' => 'text', 'text' => 'Tool execution failed.']],
            ];
        }
    }

    /** Instantiate handlers with only explicitly supported runtime dependencies. */
    private function instantiateHandler(string $handlerClass, Container $container): object
    {
        $reflection = new ReflectionClass($handlerClass);
        if (!$reflection->isInstantiable()) {
            throw new RuntimeException('MCP handler is not instantiable');
        }

        $constructor = $reflection->getConstructor();
        if ($constructor === null) {
            return $reflection->newInstance();
        }

        $dependencies = [];
        foreach ($constructor->getParameters() as $parameter) {
            $type = $parameter->getType();
            $typeName = $type instanceof ReflectionNamedType && !$type->isBuiltin() ? $type->getName() : '';
            if ($typeName === PDO::class) {
                $dependencies[] = $this->pdo;
            } elseif ($typeName === Container::class) {
                $dependencies[] = $container;
            } elseif ($parameter->isDefaultValueAvailable()) {
                $dependencies[] = $parameter->getDefaultValue();
            } else {
                throw new RuntimeException('MCP handler has an unsupported constructor dependency');
            }
        }

        return $reflection->newInstanceArgs($dependencies);
    }

    /**
     * Invoke current contract handlers and narrowly supported legacy handlers.
     * Legacy array contexts contain only the authorized execution identity and
     * database handle; no request-provided actor fields are copied through.
     */
    private function invokeHandler(
        object $instance,
        string $method,
        array $arguments,
        ModuleExecutionContext $context,
        Container $container
    ): mixed {
        if (!method_exists($instance, $method)) {
            // Older marketplace examples used handle(); keep this compatibility
            // only for the default method, never for an explicit Class::method.
            if ($method !== 'execute' || !method_exists($instance, 'handle')) {
                throw new RuntimeException('MCP handler method is not available');
            }
            $method = 'handle';
        }

        $reflection = new ReflectionMethod($instance, $method);
        if (!$reflection->isPublic() || $reflection->isStatic()) {
            throw new RuntimeException('MCP handler method must be public and non-static');
        }

        $parameters = $reflection->getParameters();
        if (count($parameters) < 1 || count($parameters) > 3) {
            throw new RuntimeException('Unsupported MCP handler signature');
        }
        $firstType = $parameters[0]->getType();
        if (!$firstType instanceof ReflectionNamedType || $firstType->getName() !== 'array') {
            throw new RuntimeException('MCP handler must accept arguments as an array');
        }

        $callArguments = [$arguments];
        if (isset($parameters[1])) {
            $type = $parameters[1]->getType();
            if ($type instanceof ReflectionNamedType && $type->getName() === ModuleExecutionContext::class) {
                $callArguments[] = $context;
            } elseif ($type instanceof ReflectionNamedType && $type->getName() === 'array') {
                $callArguments[] = $this->legacyContext($context);
            } else {
                throw new RuntimeException('Unsupported MCP handler context parameter');
            }
        }
        if (isset($parameters[2])) {
            $type = $parameters[2]->getType();
            if (!$type instanceof ReflectionNamedType || $type->getName() !== Container::class) {
                throw new RuntimeException('Unsupported MCP handler dependency parameter');
            }
            $callArguments[] = $container;
        }

        return $reflection->invokeArgs($instance, $callArguments);
    }

    /** @return array<string, mixed> */
    private function legacyContext(ModuleExecutionContext $context): array
    {
        $user = [
            'organization_id' => $context->organizationId,
            'organization_public_id' => $context->organizationPublicId,
            'public_id' => $context->actorPublicId,
        ];

        return [
            'module_name' => $context->moduleName,
            'organization_id' => $context->organizationId,
            'organization_public_id' => $context->organizationPublicId,
            'workspace_id' => $context->organizationId,
            'actor_public_id' => $context->actorPublicId,
            'source' => $context->source,
            'correlation_id' => $context->correlationId,
            'user' => $user,
            'db' => $this->pdo,
        ];
    }

    /**
     * Discover tools from manifest.json in active modules.
     */
    private function ensureLoaded(): void
    {
        if ($this->loaded) {
            return;
        }

        $this->loaded = true;

        if (!is_dir($this->modulesDir)) {
            return;
        }

        $items = scandir($this->modulesDir);
        if ($items === false) {
            return;
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..' || $item[0] === '.') {
                continue;
            }

            // Skip directories that don't match the vendor.name naming convention
            if (!preg_match('/^[a-z0-9]+\.[a-z0-9\-]+$/', $item)) {
                continue;
            }

            $manifestFile = $this->modulesDir . '/' . $item . '/manifest.json';
            if (!is_file($manifestFile)) {
                continue;
            }

            $json = json_decode((string)file_get_contents($manifestFile), true);
            if (!is_array($json) || empty($json['mcp_tools']) || !is_array($json['mcp_tools'])) {
                continue;
            }

            $moduleName = (string)($json['name'] ?? $item);
            foreach ($json['mcp_tools'] as $toolRaw) {
                if (!is_array($toolRaw)) {
                    continue;
                }
                try {
                    $def = $this->validator->validate($toolRaw, $moduleName);
                    $this->registeredTools[$def->name] = $def;
                } catch (Throwable $e) {
                    AppLog::warning("Skipping invalid module MCP tool in {$moduleName}: " . $e->getMessage());
                }
            }
        }
    }

    private function checkPermissions(ModuleMcpToolDefinition $tool, callable $canCheck): bool
    {
        if ($tool->permissionMode === 'none') {
            return true;
        }

        if ($tool->permissionMode === 'any') {
            foreach ($tool->permissions as $p) {
                if ($canCheck($p)) {
                    return true;
                }
            }
            return false;
        }

        // 'all' mode
        foreach ($tool->permissions as $p) {
            if (!$canCheck($p)) {
                return false;
            }
        }

        return true;
    }
}
