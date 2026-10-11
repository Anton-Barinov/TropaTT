<?php
declare(strict_types=1);

namespace Api\System\Library\Module;

use InvalidArgumentException;

final class ModuleFileStorage
{
    private string $basePath;
    private string $moduleName;

    public function __construct(string $moduleName, string $storageBase)
    {
        // SEC-001: the module name becomes a path segment, so it must never
        // carry a separator or a traversal component.
        if (preg_match('/\A[A-Za-z0-9][A-Za-z0-9._-]{0,127}\z/D', $moduleName) !== 1) {
            throw new InvalidArgumentException('INVALID_MODULE_NAME');
        }
        $this->moduleName = $moduleName;
        $this->basePath = rtrim($storageBase, '/') . '/modules/' . $moduleName;

        foreach (['', '/uploads', '/temp', '/exports', '/cache'] as $sub) {
            $dir = $this->basePath . $sub;
            if (!is_dir($dir)) {
                @mkdir($dir, 0755, true);
            }
        }
    }

    /**
     * Store uploaded file content.
     * @return string Relative path to stored file
     */
    public function put(string $subDir, string $filename, string $content): string
    {
        $relativeDir = $this->resolveSubDir($subDir);
        $name = $this->resolveFileName($filename);
        $dir = $relativeDir === '' ? $this->basePath : $this->basePath . '/' . $relativeDir;
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }

        $path = $dir . '/' . $name;
        if (!$this->isContained($path)) {
            throw new InvalidArgumentException('INVALID_STORAGE_PATH');
        }
        file_put_contents($path, $content, LOCK_EX);

        $relative = ($relativeDir === '' ? '' : $relativeDir . '/') . $name;

        return 'modules/' . $this->moduleName . '/' . $relative;
    }

    /**
     * Read a stored file.
     */
    public function get(string $path): ?string
    {
        $fullPath = $this->basePath . '/' . $this->resolveSubDir($path);
        if (!$this->isContained($fullPath) || !is_file($fullPath)) {
            return null;
        }

        $content = file_get_contents($fullPath);
        return $content !== false ? $content : null;
    }

    /**
     * Delete a stored file.
     */
    public function delete(string $path): bool
    {
        $fullPath = $this->basePath . '/' . $this->resolveSubDir($path);
        if (!$this->isContained($fullPath) || !is_file($fullPath)) {
            return false;
        }

        return unlink($fullPath);
    }

    /**
     * Check if a file exists.
     */
    public function exists(string $path): bool
    {
        $fullPath = $this->basePath . '/' . $this->resolveSubDir($path);

        return $this->isContained($fullPath) && is_file($fullPath);
    }

    /**
     * List files in a subdirectory.
     * @return array<int, array{name: string, size: int, modified: int}>
     */
    public function list(string $subDir): array
    {
        $dir = $this->basePath . '/' . $this->resolveSubDir($subDir);
        if (!$this->isContained($dir) || !is_dir($dir)) {
            return [];
        }

        $files = [];
        $items = scandir($dir);
        if ($items === false) {
            return [];
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $fullPath = $dir . '/' . $item;
            if (is_file($fullPath)) {
                $files[] = [
                    'name' => $item,
                    'size' => (int)filesize($fullPath),
                    'modified' => (int)filemtime($fullPath),
                ];
            }
        }

        return $files;
    }

    /**
     * Clean temp directory.
     */
    public function cleanTemp(): int
    {
        $tempDir = $this->basePath . '/temp';
        if (!is_dir($tempDir)) {
            return 0;
        }

        $count = 0;
        $files = glob($tempDir . '/*');
        if ($files === false) {
            return 0;
        }

        foreach ($files as $file) {
            if (is_file($file)) {
                unlink($file);
                $count++;
            }
        }

        return $count;
    }

    /**
     * Normalize a caller-supplied relative path inside the module storage root.
     *
     * SEC-001: `..`, absolute paths, NUL and control bytes are rejected so a
     * caller can never escape this module's directory.
     */
    private function resolveSubDir(string $subDir): string
    {
        $normalized = str_replace('\\', '/', $subDir);
        if (str_contains($normalized, "\0")) {
            throw new InvalidArgumentException('INVALID_STORAGE_PATH');
        }

        $segments = [];
        foreach (explode('/', $normalized) as $segment) {
            if ($segment === '' || $segment === '.') {
                continue;
            }
            if ($segment === '..' || preg_match('/[\x00-\x1F\x7F]/', $segment)) {
                throw new InvalidArgumentException('INVALID_STORAGE_PATH');
            }
            $segments[] = $segment;
        }

        return implode('/', $segments);
    }

    /**
     * Reduce a caller-supplied file name to a safe basename.
     */
    private function resolveFileName(string $filename): string
    {
        $name = basename(str_replace('\\', '/', $filename));
        if ($name === '' || $name === '.' || $name === '..'
            || preg_match('/[\x00-\x1F\x7F]/', $name)) {
            throw new InvalidArgumentException('INVALID_STORAGE_FILENAME');
        }

        return $name;
    }

    /**
     * SEC-001: every resolved path must stay under this module's storage root.
     */
    private function isContained(string $fullPath): bool
    {
        return str_starts_with($fullPath, $this->basePath . '/');
    }
}
