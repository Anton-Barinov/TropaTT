<?php
declare(strict_types=1);

namespace Updater\Backup;

final class FileBackupManager extends BackupManager
{
    public function __construct(
        private readonly string $basePath,
        private readonly string $storageDir
    ) {
    }

    /**
     * @param array<int,string> $files
     */
    public function backup(string $jobId, array $files): array
    {
        $backupId = 'backup_' . basename($jobId) . '_' . gmdate('Ymd_His');
        $backupDir = $this->storageDir . '/backups/' . $backupId;
        $filesDir = $backupDir . '/files';
        if (!is_dir($filesDir)) {
            mkdir($filesDir, 0775, true);
        }

        $items = [];
        foreach ($files as $relative) {
            $relative = str_replace('\\', '/', trim((string)$relative));
            if ($relative === '') {
                continue;
            }
            $source = $this->basePath . '/' . $relative;
            $target = $filesDir . '/' . $relative;
            $exists = is_file($source);
            if ($exists) {
                $dir = dirname($target);
                if (!is_dir($dir)) {
                    mkdir($dir, 0775, true);
                }
                if (!copy($source, $target)) {
                    throw new \RuntimeException('Unable to backup file: ' . $relative);
                }
            }
            $items[] = [
                'path' => $relative,
                'existed' => $exists,
                'sha256' => $exists ? (hash_file('sha256', $source) ?: null) : null,
                'size_bytes' => $exists ? filesize($source) : null,
            ];
        }

        $manifest = [
            'backup_id' => $backupId,
            'job_id' => $jobId,
            'created_at' => gmdate('c'),
            'items' => $items,
        ];
        file_put_contents($backupDir . '/manifest.json', json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

        return $manifest;
    }
}
