<?php
declare(strict_types=1);

namespace Api\System\Library\Module;

use RuntimeException;

final class PluginManager
{
    private string $modulesDir;

    /** @var array<string,Manifest> */
    private array $discovered = [];

    /** @var array<string,bool> */
    private array $loaded = [];

    /** @var array<string, array<string, array{code: string, field: string, message: string, value: mixed, rule: string}>> */
    private array $validationErrors = [];

    public function __construct(string $projectRoot)
    {
        $dir = $projectRoot . '/modules';
        $this->modulesDir = $dir;
    }

    public function getModulesDir(): string
    {
        return $this->modulesDir;
    }

    /**
     * Scan modules/ directory and return discovered manifests.
     * @return array<string,Manifest>
     */
    public function discover(): array
    {
        $this->discovered = [];
        $this->validationErrors = [];

        if (!is_dir($this->modulesDir)) {
            return [];
        }

        $items = scandir($this->modulesDir);
        if ($items === false) {
            return [];
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..' || $item[0] === '.') {
                continue;
            }

            $manifestPath = $this->modulesDir . '/' . $item . '/manifest.json';
            if (!is_file($manifestPath)) {
                continue;
            }

            $manifest = $this->loadManifest($manifestPath);
            if ($manifest !== null) {
                if (isset($this->discovered[$manifest->name])) {
                    $this->addError($manifest->name, 'E_MANIFEST_DUPLICATE_NAME', 'name',
                        "Duplicate module name: {$manifest->name}",
                        $manifest->name, 'unique');
                    continue;
                }
                $this->discovered[$manifest->name] = $manifest;
            }
        }

        return $this->discovered;
    }

    /**
     * Validate a manifest for structural integrity.
     * @return array<int, array{code: string, field: string, message: string, value: mixed, rule: string}>
     */
    public function validate(Manifest $manifest): array
    {
        $errors = [];

        if ($manifest->name === '') {
            $errors[] = $this->errorStruct('E_MANIFEST_MISSING_FIELD', 'name',
                'Missing required field: name', '', 'required');
        } elseif (!$this->isValidName($manifest->name)) {
            $errors[] = $this->errorStruct('E_MANIFEST_INVALID_NAME', 'name',
                "Invalid module name: \"{$manifest->name}\"",
                $manifest->name, 'regex:/^[a-z0-9]+\.[a-z0-9]+$/');
        }

        if ($manifest->version === '') {
            $errors[] = $this->errorStruct('E_MANIFEST_MISSING_FIELD', 'version',
                'Missing required field: version', '', 'required');
        } elseif (!$this->isValidVersion($manifest->version)) {
            $errors[] = $this->errorStruct('E_MANIFEST_INVALID_VERSION', 'version',
                "Invalid version: \"{$manifest->version}\"",
                $manifest->version, 'semver:X.Y.Z');
        }

        if ($manifest->vendor === '') {
            $errors[] = $this->errorStruct('E_MANIFEST_MISSING_FIELD', 'vendor',
                'Missing required field: vendor', '', 'required');
        }

        if ($manifest->title === '') {
            $errors[] = $this->errorStruct('E_MANIFEST_MISSING_FIELD', 'title',
                'Missing required field: title', '', 'required');
        }

        $moduleDir = $this->modulesDir . '/' . $manifest->name;

        if ($manifest->apiRoutes !== null) {
            $apiRoutesPath = $moduleDir . '/' . $manifest->apiRoutes;
            if (!is_file($apiRoutesPath)) {
                $errors[] = $this->errorStruct('E_MANIFEST_ROUTES_NOT_FOUND', 'api_routes',
                    "API routes file not found: {$apiRoutesPath}",
                    $manifest->apiRoutes, 'file_exists');
            }
        }

        if ($manifest->webRoutes !== null) {
            $webRoutesPath = $moduleDir . '/' . $manifest->webRoutes;
            if (!is_file($webRoutesPath)) {
                $errors[] = $this->errorStruct('E_MANIFEST_ROUTES_NOT_FOUND', 'web_routes',
                    "Web routes file not found: {$webRoutesPath}",
                    $manifest->webRoutes, 'file_exists');
            }
        }        if ($manifest->serviceProvider !== null && $manifest->serviceProvider !== '') {
            $spClass = $manifest->serviceProvider;
            if (!class_exists($spClass)) {
                $spFile = $moduleDir . '/api/' . str_replace('\\', '/', substr($spClass, strrpos($spClass, '\\') + 1)) . '.php';
                if (is_file($spFile)) {
                    try {
                        require_once $spFile;
                    } catch (\Throwable $e) {
                        $errors[] = $this->errorStruct('E_SERVICE_PROVIDER_LOAD_FAILED', 'service_provider',
                            "Failed to load service provider {$spClass}: " . $e->getMessage(), $spClass, 'require_once');
                    }
                }
            }
            if (!class_exists($spClass)) {
                $errors[] = $this->errorStruct('E_SERVICE_PROVIDER_NOT_FOUND', 'service_provider',
                    "Service provider class not found: {$spClass}", $spClass, 'class_exists');
            }
        }

        return $errors;
    }

    /**
     * Check core version compatibility.
     * @param string $coreVersion Current core version (e.g. "1.0.0")
     */
    public function checkCoreCompatibility(Manifest $manifest, string $coreVersion): bool
    {
        $required = $manifest->coreVersion;
        if ($required === '' || $required === '>=1.0.0') {
            return true;
        }

        return $this->versionMatches($coreVersion, $required);
    }

    /**
     * Detect cyclic dependencies among discovered modules.
     * @return array<int, array<string>> List of cycles found
     */
    public function detectCycles(): array
    {
        $graph = [];
        foreach ($this->discovered as $name => $manifest) {
            $graph[$name] = [];
            foreach ($manifest->dependencies as $dep) {
                $depName = $dep['name'] ?? '';
                if ($depName !== '' && isset($graph[$depName]) || isset($this->discovered[$depName])) {
                    $graph[$name][] = $depName;
                }
            }
        }

        $cycles = [];
        $visited = [];
        $recStack = [];

        foreach (array_keys($graph) as $node) {
            if (!isset($visited[$node])) {
                $this->dfsCycleDetection($graph, $node, $visited, $recStack, [], $cycles);
            }
        }

        return $cycles;
    }

    /**
     * Topological sort of modules by dependencies.
     * @return array<string> Module names in load order
     */
    public function resolveDependencyOrder(): array
    {
        $graph = [];
        $inDegree = [];

        foreach ($this->discovered as $name => $manifest) {
            if (!isset($graph[$name])) {
                $graph[$name] = [];
            }
            if (!isset($inDegree[$name])) {
                $inDegree[$name] = 0;
            }
            foreach ($manifest->dependencies as $dep) {
                $depName = $dep['name'] ?? '';
                if ($depName !== '' && isset($this->discovered[$depName])) {
                    $graph[$depName][] = $name;
                    $inDegree[$name] = ($inDegree[$name] ?? 0) + 1;
                }
            }
        }

        $queue = [];
        foreach ($inDegree as $name => $degree) {
            if ($degree === 0) {
                $queue[] = $name;
            }
        }

        $order = [];
        while ($queue !== []) {
            $current = array_shift($queue);
            $order[] = $current;
            foreach ($graph[$current] ?? [] as $neighbor) {
                $inDegree[$neighbor]--;
                if ($inDegree[$neighbor] === 0) {
                    $queue[] = $neighbor;
                }
            }
        }

        return $order;
    }

    /**
     * Load a module with all its dependencies (in topo order).
     * @param array<string>|null $loadOrder Pre-computed dependency order
     */
    public function loadWithDependencies(string $moduleName, ?array $loadOrder = null): bool
    {
        if (!isset($this->discovered[$moduleName])) {
            return false;
        }

        $cycles = $this->detectCycles();
        if ($cycles !== []) {
            foreach ($cycles as $cycle) {
                error_log('[PluginManager] Circular dependency: ' . implode(' → ', $cycle));
            }
            return false;
        }

        if ($loadOrder === null) {
            $loadOrder = $this->resolveDependencyOrder();
        }

        $moduleIndex = array_search($moduleName, $loadOrder, true);
        if ($moduleIndex === false) {
            return false;
        }

        for ($i = 0; $i <= $moduleIndex; $i++) {
            $name = $loadOrder[$i];
            if (!$this->isLoaded($name) && isset($this->discovered[$name])) {
                $this->load($name);
            }
        }

        return $this->isLoaded($moduleName);
    }

    /**
     * Load (mark as loaded) a module by name.
     */
    public function load(string $moduleName): bool
    {
        if (!isset($this->discovered[$moduleName])) {
            return false;
        }

        $manifest = $this->discovered[$moduleName];
        $errors = $this->validate($manifest);
        if ($errors !== []) {
            $this->validationErrors[$moduleName] = $errors;
            return false;
        }

        if (!$this->checkCoreCompatibility($manifest, '1.0.0')) {
            $this->addError($moduleName, 'E_CORE_VERSION', 'core_version',
                "Module requires core {$manifest->coreVersion}", $manifest->coreVersion, 'core_version');
            return false;
        }

        $this->loaded[$moduleName] = true;
        return true;
    }

    /**
     * @return array<string,Manifest>
     */
    public function getActive(): array
    {
        $active = [];
        foreach ($this->loaded as $name => $isLoaded) {
            if ($isLoaded && isset($this->discovered[$name])) {
                $active[$name] = $this->discovered[$name];
            }
        }
        return $active;
    }

    public function isLoaded(string $moduleName): bool
    {
        return isset($this->loaded[$moduleName]) && $this->loaded[$moduleName] === true;
    }

    public function getManifest(string $moduleName): ?Manifest
    {
        return $this->discovered[$moduleName] ?? null;
    }

    /**
     * @return array<string,Manifest>
     */
    public function getDiscovered(): array
    {
        return $this->discovered;
    }

    /**
     * @return array<string, array<int, array{code: string, field: string, message: string, value: mixed, rule: string}>>
     */
    public function getValidationErrors(): array
    {
        return $this->validationErrors;
    }

    /**
     * @return array<int, array{code: string, field: string, message: string, value: mixed, rule: string}>|null
     */
    public function getModuleErrors(string $moduleName): ?array
    {
        return $this->validationErrors[$moduleName] ?? null;
    }

    private function loadManifest(string $path): ?Manifest
    {
        $content = @file_get_contents($path);
        if ($content === false || $content === '') {
            return null;
        }

        $data = json_decode($content, true);
        if (!is_array($data)) {
            return null;
        }

        return Manifest::fromArray($data);
    }

    private function isValidName(string $name): bool
    {
        if (strlen($name) > 64) {
            return false;
        }

        if (!preg_match('/^[a-z0-9]+\.[a-z0-9\-]+$/', $name)) {
            return false;
        }

        if (str_contains($name, '..')) {
            return false;
        }

        return true;
    }

    private function isValidVersion(string $version): bool
    {
        return preg_match('/^\d+\.\d+\.\d+(?:-[a-zA-Z0-9]+(?:\.[a-zA-Z0-9]+)*)?$/', $version) === 1
            && !preg_match('/0\d/', $version);
    }

    private function versionMatches(string $current, string $constraint): bool
    {
        $currentParts = array_map('intval', explode('.', $current) + [0, 0, 0]);

        if ($constraint === '*' || $constraint === '') {
            return true;
        }

        if (preg_match('/^>=(\d+)\.(\d+)\.(\d+)$/', $constraint, $m)) {
            $cmp = ($currentParts[0] * 1000000 + $currentParts[1] * 1000 + $currentParts[2])
                 - ((int)$m[1] * 1000000 + (int)$m[2] * 1000 + (int)$m[3]);
            return $cmp >= 0;
        }

        if (preg_match('/^>(\d+)\.(\d+)\.(\d+)$/', $constraint, $m)) {
            $cmp = ($currentParts[0] * 1000000 + $currentParts[1] * 1000 + $currentParts[2])
                 - ((int)$m[1] * 1000000 + (int)$m[2] * 1000 + (int)$m[3]);
            return $cmp > 0;
        }

        if (preg_match('/^<=(\d+)\.(\d+)\.(\d+)$/', $constraint, $m)) {
            $cmp = ($currentParts[0] * 1000000 + $currentParts[1] * 1000 + $currentParts[2])
                 - ((int)$m[1] * 1000000 + (int)$m[2] * 1000 + (int)$m[3]);
            return $cmp <= 0;
        }

        if (preg_match('/^(\d+)\.(\d+)\.(\d+)$/', $constraint, $m)) {
            return $currentParts[0] === (int)$m[1]
                && $currentParts[1] === (int)$m[2]
                && $currentParts[2] === (int)$m[3];
        }

        if (preg_match('/^\^(\d+)\.(\d+)$/', $constraint, $m)) {
            $major = (int)$m[1];
            $minor = (int)$m[2];
            if ($currentParts[0] !== $major) {
                return false;
            }
            if ($major === 0) {
                return $currentParts[1] >= $minor;
            }
            return $currentParts[1] >= $minor;
        }

        if (preg_match('/^~(\d+)\.(\d+)\.(\d+)$/', $constraint, $m)) {
            if ($currentParts[0] !== (int)$m[1]) return false;
            if ($currentParts[1] !== (int)$m[2]) return false;
            return $currentParts[2] >= (int)$m[3];
        }

        return false;
    }

    /**
     * @param array<string, string[]> $graph
     * @param array<string, bool> $visited
     * @param array<string, bool> $recStack
     * @param array<string> $path
     * @param array<int, array<string>> $cycles
     */
    private function dfsCycleDetection(array $graph, string $node, array &$visited, array &$recStack, array $path, array &$cycles): void
    {
        $visited[$node] = true;
        $recStack[$node] = true;
        $path[] = $node;

        foreach ($graph[$node] ?? [] as $neighbor) {
            if (!isset($visited[$neighbor])) {
                $this->dfsCycleDetection($graph, $neighbor, $visited, $recStack, $path, $cycles);
            } elseif (isset($recStack[$neighbor]) && $recStack[$neighbor]) {
                $cycleStart = array_search($neighbor, $path, true);
                if ($cycleStart !== false) {
                    $cycle = array_slice($path, $cycleStart);
                    $cycle[] = $neighbor;
                    $cycles[] = $cycle;
                }
            }
        }

        $recStack[$node] = false;
    }

    /**
     * @return array{code: string, field: string, message: string, value: mixed, rule: string}
     */
    private function errorStruct(string $code, string $field, string $message, mixed $value, string $rule): array
    {
        return [
            'code' => $code,
            'field' => $field,
            'message' => $message,
            'value' => $value,
            'rule' => $rule,
        ];
    }

    /**
     * @param array{code: string, field: string, message: string, value: mixed, rule: string} $error
     */
    private function addError(string $moduleName, string $code, string $field, string $message, mixed $value, string $rule): void
    {
        if (!isset($this->validationErrors[$moduleName])) {
            $this->validationErrors[$moduleName] = [];
        }
        $this->validationErrors[$moduleName][] = $this->errorStruct($code, $field, $message, $value, $rule);
    }
}
