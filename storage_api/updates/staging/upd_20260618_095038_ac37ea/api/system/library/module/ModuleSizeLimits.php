<?php
declare(strict_types=1);

namespace Api\System\Library\Module;

final class ModuleSizeLimits
{
    private int $maxPackageSize = 52428800;
    private int $maxExtractedSize = 209715200;
    private int $maxFilesPerModule = 500;
    private int $maxFileSize = 10485760;
    private int $maxTablesPerModule = 20;
    private int $maxDbRowsPerModule = 1000000;
    private int $maxCacheSizePerModule = 104857600;

    public function checkPackageSize(string $archivePath): bool
    {
        return is_file($archivePath) && filesize($archivePath) <= $this->maxPackageSize;
    }

    public function checkExtractedSize(string $dir): bool
    {
        return $this->getDirSize($dir) <= $this->maxExtractedSize;
    }

    public function checkFileCount(string $dir): bool
    {
        $count = 0;
        $this->countFiles($dir, $count);
        return $count <= $this->maxFilesPerModule;
    }

    public function checkFileSize(string $filePath): bool
    {
        return is_file($filePath) && filesize($filePath) <= $this->maxFileSize;
    }

    public function getMaxTablesPerModule(): int
    {
        return $this->maxTablesPerModule;
    }

    public function getMaxDbRowsPerModule(): int
    {
        return $this->maxDbRowsPerModule;
    }

    public function getMaxCacheSizePerModule(): int
    {
        return $this->maxCacheSizePerModule;
    }

    private function getDirSize(string $dir): int
    {
        $size = 0;
        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS)
        );

        /** @var \SplFileInfo $file */
        foreach ($files as $file) {
            if ($file->isFile()) {
                $size += $file->getSize();
            }
        }

        return $size;
    }

    private function countFiles(string $dir, int &$count): void
    {
        $items = scandir($dir);
        if ($items === false) {
            return;
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $path = $dir . '/' . $item;
            if (is_file($path)) {
                $count++;
            } elseif (is_dir($path)) {
                $this->countFiles($path, $count);
            }
        }
    }
}
