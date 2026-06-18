<?php
declare(strict_types=1);

namespace Updater\Package;

final class PathGuard
{
    public function __construct(private readonly array $protectedPatterns)
    {
    }

    public function normalize(string $path): ?string
    {
        $path = str_replace('\\', '/', trim($path));
        if ($path === '' || str_contains($path, "\0") || preg_match('/^[A-Za-z]:/', $path) || str_starts_with($path, '/') || str_contains($path, '../')) {
            return null;
        }
        $path = preg_replace('#/+#', '/', $path) ?? '';
        foreach (explode('/', $path) as $segment) {
            if ($segment === '' || $segment === '.' || $segment === '..' || preg_match('/[\x00-\x1F]/', $segment)) {
                return null;
            }
        }
        return $path;
    }

    public function isAllowed(string $path): bool
    {
        $normalized = $this->normalize($path);
        if ($normalized === null) {
            return false;
        }
        foreach ($this->protectedPatterns as $pattern) {
            if ($this->match((string)$pattern, $normalized)) {
                return false;
            }
        }
        return true;
    }

    private function match(string $pattern, string $path): bool
    {
        $quoted = preg_quote($pattern, '#');
        $quoted = str_replace('\*\*', '.*', $quoted);
        $quoted = str_replace('\*', '[^/]*', $quoted);
        return preg_match('#^' . $quoted . '$#', $path) === 1;
    }
}
