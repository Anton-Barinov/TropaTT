<?php
declare(strict_types=1);

namespace Updater\Rollback;

final class RollbackManager
{
    public function __construct(
        private readonly string $basePath,
        private readonly string $storageDir
    ) {
    }

    public function rollback(string $backupId): array
    {
        $backupId = basename($backupId);
        $backupDir = $this->storageDir . '/backups/' . $backupId;
        $manifestPath = $backupDir . '/manifest.json';
        if (!is_file($manifestPath)) {
            throw new \RuntimeException('Backup manifest not found.');
        }

        $manifest = json_decode((string)file_get_contents($manifestPath), true);
        if (!is_array($manifest)) {
            throw new \RuntimeException('Backup manifest is invalid.');
        }

        $restored = [];
        $items = is_array($manifest['items'] ?? null) ? $manifest['items'] : [];
        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }
            $relative = str_replace('\\', '/', trim((string)($item['path'] ?? '')));
            if ($relative === '' || str_contains($relative, '../') || str_starts_with($relative, '/')) {
                throw new \RuntimeException('Backup contains invalid path.');
            }

            $target = $this->basePath . '/' . $relative;
            if (($item['existed'] ?? false) === true) {
                $source = $backupDir . '/files/' . $relative;
                if (!is_file($source)) {
                    throw new \RuntimeException('Backup file is missing: ' . $relative);
                }
                $targetDir = dirname($target);
                if (!is_dir($targetDir)) {
                    mkdir($targetDir, 0775, true);
                }
                if (!copy($source, $target)) {
                    throw new \RuntimeException('Unable to restore file: ' . $relative);
                }
                $restored[] = ['path' => $relative, 'action' => 'restore', 'sha256' => hash_file('sha256', $target) ?: null];
                continue;
            }

            if (is_file($target) && !unlink($target)) {
                throw new \RuntimeException('Unable to remove newly-created file: ' . $relative);
            }
            $restored[] = ['path' => $relative, 'action' => 'remove'];
        }

        return [
            'backup_id' => $backupId,
            'restored_count' => count($restored),
            'files' => $restored,
        ];
    }
}
