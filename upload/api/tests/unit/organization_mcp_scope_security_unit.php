<?php
declare(strict_types=1);

/**
 * MCP organization boundary regression contract.
 *
 * MCP tool arguments are nested in JSON-RPC and therefore cannot rely on the
 * outer HTTP request when resolving the active organization. This contract
 * keeps service-direct task/project reads and filesystem-backed semantic
 * search on the same scoped actor path as REST.
 */

$source = (string)file_get_contents(dirname(__DIR__, 2) . '/controller/mcp/McpController.php');

$assert = static function (bool $ok, string $message): void {
    if (!$ok) {
        fwrite(STDERR, "[FAIL] {$message}\n");
        exit(1);
    }
    echo "[OK] {$message}\n";
};

$method = static function (string $name) use ($source): string {
    $start = strpos($source, 'private function ' . $name . '(');
    if ($start === false) {
        return '';
    }
    $next = strpos($source, "\n    private function ", $start + 1);
    return substr($source, $start, $next === false ? null : $next - $start);
};

$semantic = $method('crmSearchAiSemantic');
$listTasks = $method('crmListTasks');
$getTask = $method('crmGetTask');
$listProjects = $method('crmListProjects');
$getProject = $method('crmGetProject');

$assert(str_contains($source, 'private function organizationRequestForArguments(array $arguments): ?Request'), 'MCP builds a synthetic request from nested tool arguments');
$assert(str_contains($source, 'private function organizationScopedActorForArguments(array $actor, array $arguments): array'), 'MCP has an argument-aware scoped actor resolver');
$assert(str_contains($source, '$request = $this->organizationRequestForArguments($arguments);'), 'MCP context validation uses the argument-aware request');

$assert(str_contains($semantic, '$actor = $this->organizationScopedActorForArguments($this->actor(), $arguments);'), 'MCP semantic search resolves active organization into actor');
$assert(str_contains($semantic, '$this->canAccessSemanticEntity($item, $actor)'), 'MCP semantic search filters every hit through entity access');
$assert(!str_contains($semantic, '$this->publicData($service->search($query,'), 'MCP never returns the raw filesystem index');

foreach ([
    'task list' => $listTasks,
    'task get' => $getTask,
    'project list' => $listProjects,
    'project get' => $getProject,
] as $label => $body) {
    $assert($body !== '', "{$label} MCP handler exists");
    $assert(str_contains($body, 'organizationScopedActorForArguments'), "{$label} uses the active organization actor");
}

$assert(str_contains($source, 'private function canAccessSemanticEntity(array $item, array $actor): bool'), 'semantic results use a centralized access check');
$assert(str_contains($source, "'task' => is_array(\$this->container->get('service.task')->get(\$entityPublicId, \$actor))"), 'semantic task hits are checked by scoped TaskService');
$assert(str_contains($source, "'project' => is_array(\$this->container->get('service.project')->get(\$entityPublicId, \$actor))"), 'semantic project hits are checked by scoped ProjectService');

echo "[OK] organization MCP scope and semantic search security contract passed\n";
