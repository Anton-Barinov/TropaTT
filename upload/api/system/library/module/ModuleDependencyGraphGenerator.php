<?php
declare(strict_types=1);

namespace Api\System\Library\Module;

final class ModuleDependencyGraphGenerator
{
    /**
     * Generate DOT format graph from module manifests.
     * @param array<string, Manifest> $modules
     */
    public function generateDot(array $modules, string $format = 'svg'): string
    {
        $lines = [];
        $lines[] = 'digraph ModuleDependencies {';
        $lines[] = '  rankdir=LR;';
        $lines[] = '  node [shape=box, style=filled, fontname="Arial"];';

        foreach ($modules as $name => $manifest) {
            $color = '#e8f5e9';
            $lines[] = "  \"{$name}\" [label=\"{$name}\\n{$manifest->version}\", fillcolor=\"{$color}\"];";

            foreach ($manifest->dependencies as $dep) {
                $depName = $dep['name'] ?? '';
                $depVersion = $dep['version'] ?? '*';
                if ($depName !== '') {
                    $lines[] = "  \"{$name}\" -> \"{$depName}\" [label=\"{$depVersion}\"];";
                }
            }
        }

        $lines[] = '}';

        return implode("\n", $lines);
    }

    /**
     * Generate ASCII art representation of the dependency tree.
     * @param array<string, Manifest> $modules
     */
    public function generateAscii(array $modules, string $rootModule = ''): string
    {
        $output = '';

        foreach ($modules as $name => $manifest) {
            if ($rootModule !== '' && $name !== $rootModule) {
                continue;
            }

            $output .= $this->renderNode($name, $modules, '', true);
        }

        return $output;
    }

    /**
     * @param array<string, Manifest> $modules
     */
    private function renderNode(string $name, array $modules, string $prefix, bool $isLast): string
    {
        $manifest = $modules[$name] ?? null;
        if ($manifest === null) {
            return "{$prefix}" . ($isLast ? '└── ' : '├── ') . "{$name} (not found)\n";
        }

        $output = "{$prefix}" . ($isLast ? '└── ' : '├── ') . "{$name} v{$manifest->version}\n";

        $deps = $manifest->dependencies;
        $count = count($deps);

        foreach ($deps as $i => $dep) {
            $depName = $dep['name'] ?? '';
            if ($depName === '') {
                continue;
            }
            $childPrefix = $prefix . ($isLast ? '    ' : '│   ');
            $output .= $this->renderNode($depName, $modules, $childPrefix, $i === $count - 1);
        }

        return $output;
    }
}
