<?php
declare(strict_types=1);

namespace Updater\Rollback;

use Updater\Util\WorkBudget;

final class RollbackManager
{
    public function __construct(
        private readonly string $basePath,
        private readonly string $storageDir
    ) {
    }

    /**
     * Restore files from a backup, optionally resuming from $cursor with a
     * bounded amount of work per call. Each call restores at most $maxFiles
     * items; 'done' is false until every backup item has been processed, so a
     * large rollback runs as many small requests instead of one that a shared
     * host would cut mid-way.
     *
     * @return array{done:bool,cursor:int,backup_id:string,total:int,restored_count:int,files:array<int,array<string,mixed>>}
     */
    public function rollback(string $backupId, int $cursor = 0, ?WorkBudget $budget = null, int $maxFiles = 150): array
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

        $items = array_values(array_filter(
            is_array($manifest['items'] ?? null) ? $manifest['items'] : [],
            static fn (mixed $item): bool => is_array($item)
        ));
        $total = count($items);
        $startCursor = min(max(0, $cursor), $total);

        $restored = [];
        $position = $startCursor;
        while ($position < $total && count($restored) < $maxFiles) {
            if ($budget !== null && $budget->exhausted()) {
                break;
            }
            $item = $items[$position];
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
            } else {
                if (is_file($target) && !unlink($target)) {
                    throw new \RuntimeException('Unable to remove newly-created file: ' . $relative);
                }
                $restored[] = ['path' => $relative, 'action' => 'remove'];
            }
            $position++;
        }

        return [
            'done' => $position >= $total,
            'cursor' => $position,
            'backup_id' => $backupId,
            'total' => $total,
            'restored_count' => count($restored),
            'files' => $restored,
        ];
    }
}
