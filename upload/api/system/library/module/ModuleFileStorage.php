<?php
declare(strict_types=1);

namespace Api\System\Library\Module;

final class ModuleFileStorage
{
    private string $basePath;
    private string $moduleName;

    public function __construct(string $moduleName, string $storageBase)
    {
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
        $dir = $this->basePath . '/' . trim($subDir, '/');
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }

        $path = $dir . '/' . $filename;
        file_put_contents($path, $content, LOCK_EX);

        return 'modules/' . $this->moduleName . '/' . trim($subDir, '/') . '/' . $filename;
    }

    /**
     * Read a stored file.
     */
    public function get(string $path): ?string
    {
        $fullPath = $this->basePath . '/' . ltrim($path, '/');
        if (!is_file($fullPath)) {
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
        $fullPath = $this->basePath . '/' . ltrim($path, '/');
        if (!is_file($fullPath)) {
            return false;
        }

        return unlink($fullPath);
    }

    /**
     * Check if a file exists.
     */
    public function exists(string $path): bool
    {
        return is_file($this->basePath . '/' . ltrim($path, '/'));
    }

    /**
     * List files in a subdirectory.
     * @return array<int, array{name: string, size: int, modified: int}>
     */
    public function list(string $subDir): array
    {
        $dir = $this->basePath . '/' . trim($subDir, '/');
        if (!is_dir($dir)) {
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
}
