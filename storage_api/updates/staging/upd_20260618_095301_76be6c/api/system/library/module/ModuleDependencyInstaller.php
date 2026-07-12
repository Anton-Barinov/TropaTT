<?php
declare(strict_types=1);

namespace Api\System\Library\Module;

final class ModuleDependencyInstaller
{
    public function __construct(
        private readonly PluginManager $pm,
        private readonly ModuleRemoteInstaller $installer,
    ) {}

    /**
     * Install a module and all its dependencies.
     * @return array{installed: array<string>, errors: array<string>}
     */
    public function installWithDependencies(string $moduleName, ?string $repoUrl = null): array
    {
        $result = ['installed' => [], 'errors' => []];

        $depTree = $this->getDependencyTree($moduleName);

        $order = $this->topoSort($depTree);
        foreach ($order as $name) {
            if ($this->pm->getManifest($name) !== null) {
                continue;
            }

            try {
                if ($repoUrl !== null) {
                    $pkgUrl = rtrim($repoUrl, '/') . '/' . $name . '.zip';
                    $this->installer->installFromUrl($pkgUrl);
                }
                $result['installed'][] = $name;
            } catch (\Throwable $e) {
                $result['errors'][] = "{$name}: " . $e->getMessage();
            }
        }

        return $result;
    }

    /**
     * Get the full dependency tree for a module.
     * @return array<string, array<string>>
     */
    public function getDependencyTree(string $moduleName): array
    {
        $tree = [];
        $visited = [];
        $this->buildTree($moduleName, $tree, $visited);
        return $tree;
    }

    /** @return array{ok: bool, missing: array<string>} */
    public function checkDependencySatisfaction(string $moduleName): array
    {
        $result = ['ok' => true, 'missing' => []];

        $manifest = $this->pm->getManifest($moduleName);
        if ($manifest === null) {
            $result['ok'] = false;
            $result['missing'][] = $moduleName;
            return $result;
        }

        foreach ($manifest->dependencies as $dep) {
            $depName = $dep['name'] ?? '';
            if ($depName !== '' && $this->pm->getManifest($depName) === null) {
                $result['ok'] = false;
                $result['missing'][] = $depName;
            }
        }

        return $result;
    }

    /**
     * @param array<string, array<string>> $graph
     * @return array<string>
     */
    private function topoSort(array $graph): array
    {
        $inDegree = [];
        foreach ($graph as $node => $deps) {
            if (!isset($inDegree[$node])) {
                $inDegree[$node] = 0;
            }
            foreach ($deps as $dep) {
                if (!isset($inDegree[$dep])) {
                    $inDegree[$dep] = 0;
                }
                $inDegree[$node]++;
            }
        }

        $queue = [];
        foreach ($inDegree as $node => $deg) {
            if ($deg === 0) {
                $queue[] = $node;
            }
        }

        $order = [];
        while ($queue !== []) {
            $node = array_shift($queue);
            $order[] = $node;
            foreach ($graph as $n => $deps) {
                if (in_array($node, $deps, true)) {
                    $inDegree[$n]--;
                    if ($inDegree[$n] === 0) {
                        $queue[] = $n;
                    }
                }
            }
        }

        return $order;
    }

    /**
     * @param array<string, array<string>> $tree
     * @param array<string, bool> $visited
     */
    private function buildTree(string $moduleName, array &$tree, array &$visited): void
    {
        if (isset($visited[$moduleName])) {
            return;
        }

        $visited[$moduleName] = true;
        $manifest = $this->pm->getManifest($moduleName);

        if ($manifest !== null) {
            $deps = [];
            foreach ($manifest->dependencies as $dep) {
                $depName = $dep['name'] ?? '';
                if ($depName !== '') {
                    $deps[] = $depName;
                    $this->buildTree($depName, $tree, $visited);
                }
            }
            $tree[$moduleName] = $deps;
        }
    }
}
