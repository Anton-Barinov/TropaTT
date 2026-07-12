<?php
declare(strict_types=1);

namespace Api\System\Library\Module;

final class DependencyResolver
{
    /** @var array<string, Manifest> */
    private array $installed;

    /** @param array<string, Manifest> $installed */
    public function __construct(array $installed)
    {
        $this->installed = $installed;
    }

    /**
     * Check if a module's dependencies are satisfied by installed modules.
     * @return array{ok: bool, missing: array<string>, version_mismatch: array<string>}
     */
    public function checkDependencies(Manifest $manifest): array
    {
        $result = ['ok' => true, 'missing' => [], 'version_mismatch' => []];

        foreach ($manifest->dependencies as $dep) {
            $depName = $dep['name'] ?? '';
            $depVersion = $dep['version'] ?? '*';

            if ($depName === '') {
                continue;
            }

            if (!isset($this->installed[$depName])) {
                $result['ok'] = false;
                $result['missing'][] = $depName;
                continue;
            }

            $installedVersion = $this->installed[$depName]->version;
            if ($depVersion !== '*' && !$this->versionMatches($installedVersion, $depVersion)) {
                $result['ok'] = false;
                $result['version_mismatch'][] = "{$depName}: requires {$depVersion}, installed {$installedVersion}";
            }
        }

        return $result;
    }

    /**
     * Check for conflicts with installed modules.
     * @return array<int, string>
     */
    public function checkConflicts(Manifest $manifest): array
    {
        $conflicts = [];

        foreach ($this->installed as $name => $installed) {
            $installedConflicts = $installed->dependencies ?? [];
            foreach ($installedConflicts as $dep) {
                $conflictName = $dep['conflict'] ?? '';
                if ($conflictName === $manifest->name) {
                    $conflicts[] = "Conflicts with installed module: {$name}";
                }
            }
        }

        return $conflicts;
    }

    /**
     * @return array<string> Module names in dependency order
     */
    public function resolveOrder(): array
    {
        $graph = [];
        $inDegree = [];

        foreach ($this->installed as $name => $manifest) {
            if (!isset($graph[$name])) {
                $graph[$name] = [];
            }
            if (!isset($inDegree[$name])) {
                $inDegree[$name] = 0;
            }
            foreach ($manifest->dependencies as $dep) {
                $depName = $dep['name'] ?? '';
                if ($depName !== '' && isset($this->installed[$depName])) {
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

    private function versionMatches(string $current, string $constraint): bool
    {
        $currentParts = array_map('intval', explode('.', $current) + [0, 0, 0]);

        if (preg_match('/^>=(\d+)\.(\d+)\.(\d+)$/', $constraint, $m)) {
            $cmp = ($currentParts[0] * 1000000 + $currentParts[1] * 1000 + $currentParts[2])
                 - ((int)$m[1] * 1000000 + (int)$m[2] * 1000 + (int)$m[3]);
            return $cmp >= 0;
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

        return false;
    }
}
