<?php
declare(strict_types=1);

namespace Api\System\Library\Module;

use InvalidArgumentException;

/**
 * Validates module route declarations for strict security, namespace isolation,
 * RBAC constraints, and workspace context declarations.
 *
 * Ensures fail-closed behavior for malformed or unauthorized route entries.
 */
final class ModuleRouteValidator
{
    private const ALLOWED_METHODS = ['GET', 'POST', 'PUT', 'DELETE', 'PATCH', 'OPTIONS', 'HEAD'];

    /**
     * Validate and normalize a single module route declaration.
     *
     * @param array<string, mixed> $route
     * @param string $modulePrefix The module prefix (e.g. "crm.handover")
     * @return array<string, mixed> Normalized and validated route array
     * @throws InvalidArgumentException on validation failure
     */
    public function validate(array $route, string $modulePrefix): array
    {
        // 1. Validate route path
        $path = (string)($route['route'] ?? $route['pattern'] ?? '');
        $path = trim($path);
        if ($path === '') {
            throw new InvalidArgumentException("Module route requires a non-empty 'route' path");
        }
        if (str_contains($path, '..') || str_contains($path, '//')) {
            throw new InvalidArgumentException("Module route path contains illegal path traversal characters: '{$path}'");
        }

        // 2. Validate HTTP methods
        $methods = $route['methods'] ?? ['GET', 'POST', 'PUT', 'DELETE', 'PATCH'];
        if (is_string($methods)) {
            $methods = [$methods];
        }
        if (!is_array($methods) || $methods === []) {
            throw new InvalidArgumentException("Module route requires a non-empty array of HTTP methods");
        }

        $normalizedMethods = [];
        foreach ($methods as $m) {
            $upper = strtoupper(trim((string)$m));
            if (!in_array($upper, self::ALLOWED_METHODS, true)) {
                throw new InvalidArgumentException("Unsupported HTTP method '{$m}' in module route");
            }
            $normalizedMethods[] = $upper;
        }
        $normalizedMethods = array_values(array_unique($normalizedMethods));

        // 3. Validate Controller class - must reside within Module namespace
        $controller = trim((string)($route['controller'] ?? ''));
        if ($controller === '') {
            throw new InvalidArgumentException("Module route requires a 'controller' class");
        }

        $controllerClean = ltrim($controller, '\\');
        if (!str_starts_with($controllerClean, 'Module\\')) {
            throw new InvalidArgumentException(
                "Module route controller '{$controller}' must reside within the 'Module\\' namespace"
            );
        }

        // 4. Validate Action method name
        $action = trim((string)($route['action'] ?? ''));
        if ($action === '' || preg_match('/^[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*$/', $action) !== 1) {
            throw new InvalidArgumentException("Module route requires a valid PHP action method name: '{$action}'");
        }

        // 5. Validate Auth mode & Workspace requirement
        $auth = (bool)($route['auth'] ?? true);
        $workspaceRequired = isset($route['workspace_required'])
            ? (bool)$route['workspace_required']
            : $auth; // Defaults to true for authenticated routes, false for public webhooks

        // 6. Validate Required Permissions (prohibit wildcard *)
        $permissions = $route['required_permissions'] ?? [];
        if (is_string($permissions) && $permissions !== '') {
            $permissions = [$permissions];
        }
        if (!is_array($permissions)) {
            $permissions = [];
        }

        $cleanPerms = [];
        foreach ($permissions as $p) {
            $pStr = trim((string)$p);
            if ($pStr === '' || $pStr === '*') {
                throw new InvalidArgumentException("Module route contains invalid or wildcard permission '{$pStr}'");
            }
            $cleanPerms[] = $pStr;
        }

        // 7. Validate Idempotency declaration
        $idempotency = $route['idempotency'] ?? false;
        if ($idempotency !== false && $idempotency !== true && $idempotency !== 'required') {
            $idempotency = (bool)$idempotency;
        }

        return [
            'route' => $path,
            'methods' => $normalizedMethods,
            'controller' => $controller,
            'action' => $action,
            'auth' => $auth,
            'workspace_required' => $workspaceRequired,
            'required_permissions' => $cleanPerms,
            'idempotency' => $idempotency,
            'module_name' => $modulePrefix,
            'external_ok' => (bool)($route['external_ok'] ?? false),
            'external_executor_ok' => (bool)($route['external_executor_ok'] ?? false),
            'external_write_ok' => (bool)($route['external_write_ok'] ?? false),
            'sse' => (bool)($route['sse'] ?? false),
            'binary' => (bool)($route['binary'] ?? false),
        ];
    }
}
