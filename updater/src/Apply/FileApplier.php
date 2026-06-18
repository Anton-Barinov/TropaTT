<?php
declare(strict_types=1);

namespace Updater\Apply;

use Updater\Package\PathGuard;

final class FileApplier
{
    public function __construct(
        private readonly string $basePath,
        private readonly string $storageDir,
        private readonly array $protectedPaths
    ) {
    }

    public function apply(string $jobId, array $manifest): array
    {
        $stagingDir = $this->storageDir . '/staging/' . basename($jobId);
        if (!is_dir($stagingDir)) {
            throw new \RuntimeException('Staging directory is missing.');
        }

        $guard = new PathGuard($this->protectedPaths);
        $files = $this->filesFromManifest($manifest);
        $hashes = is_array($manifest['file_hashes'] ?? null) ? $manifest['file_hashes'] : [];
        $applied = [];

        foreach ($files['delete'] as $relative) {
            $relative = $this->assertAllowed($guard, $relative);
            $target = $this->basePath . '/' . $relative;
            if (is_file($target) && !unlink($target)) {
                throw new \RuntimeException('Unable to delete file: ' . $relative);
            }
            $applied[] = ['path' => $relative, 'action' => 'delete'];
        }

        foreach (array_merge($files['add'], $files['modify']) as $relative) {
            $relative = $this->assertAllowed($guard, $relative);
            $source = $stagingDir . '/' . $relative;
            if (!is_file($source)) {
                throw new \RuntimeException('Staged file is missing: ' . $relative);
            }
            $target = $this->basePath . '/' . $relative;
            $targetDir = dirname($target);
            if (!is_dir($targetDir)) {
                mkdir($targetDir, 0775, true);
            }
            if (!copy($source, $target)) {
                throw new \RuntimeException('Unable to apply file: ' . $relative);
            }
            $expected = is_array($hashes[$relative] ?? null) ? (string)($hashes[$relative]['sha256'] ?? '') : '';
            $actual = hash_file('sha256', $target) ?: '';
            if ($expected !== '' && !hash_equals($expected, $actual)) {
                throw new \RuntimeException('Applied file hash mismatch: ' . $relative);
            }
            $applied[] = ['path' => $relative, 'action' => is_file($target) ? 'write' : 'add', 'sha256' => $actual];
        }

        return ['count' => count($applied), 'files' => $applied];
    }

    /**
     * @return array{add:array<int,string>,modify:array<int,string>,delete:array<int,string>}
     */
    public function filesFromManifest(array $manifest): array
    {
        $files = is_array($manifest['files'] ?? null) ? $manifest['files'] : [];
        return [
            'add' => array_values(array_filter(array_map('strval', is_array($files['add'] ?? null) ? $files['add'] : []))),
            'modify' => array_values(array_filter(array_map('strval', is_array($files['modify'] ?? null) ? $files['modify'] : []))),
            'delete' => array_values(array_filter(array_map('strval', is_array($files['delete'] ?? null) ? $files['delete'] : []))),
        ];
    }

    private function assertAllowed(PathGuard $guard, string $relative): string
    {
        $normalized = $guard->normalize($relative);
        if ($normalized === null || !$guard->isAllowed($normalized)) {
            throw new \RuntimeException('Forbidden update path: ' . $relative);
        }
        return $normalized;
    }
}
