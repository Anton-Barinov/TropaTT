<?php
declare(strict_types=1);

namespace Api\System\Library\Support;

final class Autoloader
{
    public function __construct(private readonly string $basePath)
    {
    }

    public function register(): void
    {
        spl_autoload_register(function (string $class): void {
            $prefix = 'Api\\';
            if (!str_starts_with($class, $prefix)) {
                return;
            }

            $relative = substr($class, strlen($prefix));
            if ($relative === false || $relative === '') {
                return;
            }

            $parts = explode('\\', $relative);
            $basename = array_pop($parts);
            $lowerDirs = array_map(static fn(string $p): string => strtolower($p), $parts);

            $candidates = [
                $this->basePath . '/' . str_replace('\\', '/', $relative) . '.php',
                $this->basePath . '/' . strtolower(str_replace('\\', '/', $relative)) . '.php',
                $this->basePath . '/' . implode('/', $lowerDirs) . '/' . $basename . '.php',
                $this->basePath . '/' . implode('/', $lowerDirs) . '/' . strtolower($basename) . '.php',
            ];

            foreach ($candidates as $path) {
                if (is_file($path)) {
                    require_once $path;
                    return;
                }
            }
        });
    }
}
