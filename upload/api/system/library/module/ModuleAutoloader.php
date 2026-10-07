<?php
declare(strict_types=1);

namespace Api\System\Library\Module;

final class ModuleAutoloader
{
    private string $projectRoot;

    /** @var array<string,string> Module name -> base path */
    private array $modulePaths = [];

    public function __construct(string $projectRoot)
    {
        $this->projectRoot = $projectRoot;
    }

    public function registerModule(string $moduleName, string $vendor): void
    {
        $plainName = $moduleName;
        if (str_starts_with($plainName, $vendor . '.')) {
            $plainName = substr($plainName, strlen($vendor) + 1);
        }
        $namespaceKey = strtolower($vendor) . '.' . strtolower($this->dashToCamel($plainName));
        $filesystemPath = $this->projectRoot . '/modules/' . $vendor . '.' . $plainName;
        $this->modulePaths[$namespaceKey] = $filesystemPath;
    }

    public function loadClass(string $class): bool
    {
        if (strncmp($class, 'Module\\', 7) !== 0) {
            return false;
        }

        $parts = explode('\\', substr($class, 7), 3);
        if (count($parts) < 3) {
            return false;
        }

        [$vendor, $name, $rest] = $parts;
        $moduleKey = strtolower($vendor) . '.' . strtolower($name);
        $basePath = $this->modulePaths[$moduleKey] ?? ($this->projectRoot . '/modules/' . $vendor . '.' . $name);
        $relativePath = str_replace('\\', '/', $rest) . '.php';
        $relativePaths = array_values(array_unique($this->pathCandidates($relativePath)));

        foreach (['api', 'web'] as $area) {
            foreach ($relativePaths as $candidate) {
                $path = $basePath . '/' . $area . '/' . $candidate;

                if (is_file($path) && !class_exists($class, false)) {
                    require_once $path;
                    if (
                        class_exists($class, false)
                        || interface_exists($class, false)
                        || trait_exists($class, false)
                        || enum_exists($class, false)
                    ) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    public function register(): void
    {
        spl_autoload_register([$this, 'loadClass'], true, false);
    }

    private function dashToCamel(string $name): string
    {
        return str_replace(' ', '', ucwords(str_replace('-', ' ', $name)));
    }

    /**
     * Candidate relative paths for a namespace-derived path.
     *
     * Modules ship three layouts for the same namespace shape: the area
     * segment may be duplicated by the area prefix (`Web\Controller\X` under
     * `web/`), kept as a directory on its own (`web/Controller/X.php` or
     * `web/controller/X.php`), or omitted entirely (`web/X.php`). Each
     * candidate is additionally tried with its first segment lower-cased so
     * directory casing never decides whether a class loads.
     *
     * @return array<int, string>
     */
    private function pathCandidates(string $relativePath): array
    {
        $candidates = [$relativePath, $this->lowerFirstPathSegment($relativePath)];

        if (preg_match('#^(?:api|web)/(.+)$#i', $relativePath, $matches) === 1) {
            $stripped = $matches[1];
            $candidates[] = $stripped;
            $candidates[] = $this->lowerFirstPathSegment($stripped);
        }

        return array_values(array_unique($candidates));
    }

    private function lowerFirstPathSegment(string $relativePath): string
    {
        $parts = explode('/', $relativePath);
        if ($parts === []) {
            return $relativePath;
        }
        $parts[0] = strtolower($parts[0]);
        return implode('/', $parts);
    }
}
