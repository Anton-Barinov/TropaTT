<?php
declare(strict_types=1);

namespace Api\System\Library\Module;

use Api\System\Library\Module\PluginManager;
use Api\System\Library\Module\ModuleConfig;
use Api\System\Library\Module\ModuleMigrationRunner;
use Api\System\Library\Module\ModuleCodeValidator;
use RuntimeException;

final class ModuleRemoteInstaller
{
    public function __construct(
        private readonly PluginManager $pm,
        private readonly ModuleConfig $mc,
        private readonly ModuleMigrationRunner $mm,
        private readonly string $projectRoot,
    ) {}

    /**
     * Install a module from a remote URL.
     * @return string Module name
     */
    public function installFromUrl(string $url, bool $verifySignature = true): string
    {
        $tmpDir = sys_get_temp_dir() . '/crm_module_' . bin2hex(random_bytes(8));
        @mkdir($tmpDir, 0755, true);

        try {
            $archive = $tmpDir . '/module.zip';
            $this->download($url, $archive);
            return $this->installFromFile($archive, $verifySignature);
        } finally {
            $this->cleanDir($tmpDir);
        }
    }

    /**
     * Install from a local package file.
     * @return string Module name
     */
    public function installFromFile(string $filePath, bool $verifySignature = true): string
    {
        if (!is_file($filePath)) {
            throw new RuntimeException("Package file not found: {$filePath}");
        }

        $extractDir = dirname($filePath) . '/extracted_' . bin2hex(random_bytes(4));
        @mkdir($extractDir, 0755, true);

        try {
            $this->extract($filePath, $extractDir);

            $manifestPath = $extractDir . '/manifest.json';
            if (!is_file($manifestPath)) {
                throw new RuntimeException("Package does not contain manifest.json");
            }

            $manifestContent = file_get_contents($manifestPath);
            if ($manifestContent === false) {
                throw new RuntimeException("Cannot read manifest.json");
            }

            $manifestData = json_decode($manifestContent, true);
            if (!is_array($manifestData)) {
                throw new RuntimeException("Invalid manifest.json");
            }

            $moduleName = $manifestData['name'] ?? '';
            if ($moduleName === '') {
                throw new RuntimeException("Module name not specified in manifest");
            }

            $targetDir = $this->projectRoot . '/modules/' . $moduleName;
            if (is_dir($targetDir)) {
                throw new RuntimeException("Module already exists: {$moduleName}");
            }

            $this->copyDir($extractDir, $targetDir);

            $this->pm->discover();
            $manifest = $this->pm->getManifest($moduleName);

            if ($manifest === null) {
                throw new RuntimeException("Failed to discover installed module");
            }

            $errors = $this->pm->validate($manifest);
            if ($errors !== []) {
                $this->cleanDir($targetDir);
                $errorStr = implode('; ', array_map(fn($e) => $e['message'], $errors));
                throw new RuntimeException("Module validation failed: {$errorStr}");
            }

            $this->mc->register($moduleName, $manifest->vendor, $manifest->version);

            if ($manifest->migrations !== null) {
                $migDir = $targetDir . '/' . $manifest->migrations;
                $result = $this->mm->migrate($moduleName, $migDir);
                if ($result['errors'] !== []) {
                    $this->cleanDir($targetDir);
                    $this->mc->unregister($moduleName);
                    throw new RuntimeException("Migration failed: " . implode('; ', $result['errors']));
                }
            }

            $this->mc->initFromManifest($moduleName, $manifest);

            return $moduleName;
        } finally {
            $this->cleanDir($extractDir);
        }
    }

    /**
     * Create a package from an installed module.
     * @return string Path to created archive
     */
    public function package(string $moduleName, string $format = 'zip'): string
    {
        $manifest = $this->pm->getManifest($moduleName);
        if ($manifest === null) {
            throw new RuntimeException("Module not found: {$moduleName}");
        }

        $sourceDir = $this->projectRoot . '/modules/' . $moduleName;
        if (!is_dir($sourceDir)) {
            throw new RuntimeException("Module directory not found: {$sourceDir}");
        }

        $outputPath = $this->projectRoot . '/modules/' . $moduleName . '-' . $manifest->version . '.' . $format;
        $this->createArchive($sourceDir, $outputPath);

        return $outputPath;
    }

    private function download(string $url, string $dest): void
    {
        $content = file_get_contents($url, false, stream_context_create([
            'http' => [
                'timeout' => 300,
                'user_agent' => 'CRM-Module-Installer/1.0',
            ],
        ]));

        if ($content === false) {
            throw new RuntimeException("Failed to download from: {$url}");
        }

        file_put_contents($dest, $content);
    }

    private function extract(string $archive, string $destDir): void
    {
        if (str_ends_with($archive, '.zip')) {
            $zip = new \ZipArchive();
            if ($zip->open($archive) !== true) {
                throw new RuntimeException("Cannot open ZIP archive: {$archive}");
            }
            $zip->extractTo($destDir);
            $zip->close();
            return;
        }

        if (str_ends_with($archive, '.tar.gz') || str_ends_with($archive, '.tgz')) {
            $phar = new \PharData($archive);
            $phar->extractTo($destDir);
            return;
        }

        throw new RuntimeException("Unsupported archive format: {$archive}");
    }

    private function createArchive(string $sourceDir, string $outputPath): void
    {
        $zip = new \ZipArchive();
        if ($zip->open($outputPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException("Cannot create ZIP archive: {$outputPath}");
        }

        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($sourceDir, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );

        /** @var \SplFileInfo $file */
        foreach ($files as $file) {
            $filePath = $file->getRealPath();
            $relativePath = substr($filePath, strlen($sourceDir) + 1);

            if ($file->isDir()) {
                $zip->addEmptyDir($relativePath);
            } else {
                $zip->addFile($filePath, $relativePath);
            }
        }

        $zip->close();
    }

    private function copyDir(string $source, string $dest): void
    {
        if (!is_dir($dest)) {
            @mkdir($dest, 0755, true);
        }

        $items = scandir($source);
        if ($items === false) {
            return;
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $src = $source . '/' . $item;
            $dst = $dest . '/' . $item;

            if (is_dir($src)) {
                $this->copyDir($src, $dst);
            } else {
                copy($src, $dst);
            }
        }
    }

    private function cleanDir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $items = scandir($dir);
        if ($items === false) {
            return;
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $path = $dir . '/' . $item;
            if (is_dir($path)) {
                $this->cleanDir($path);
            } else {
                @unlink($path);
            }
        }

        @rmdir($dir);
    }
}
