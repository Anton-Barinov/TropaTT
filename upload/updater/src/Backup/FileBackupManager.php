<?php
declare(strict_types=1);

namespace Updater\Backup;

use Updater\Util\WorkBudget;

final class FileBackupManager extends BackupManager
{
    public function __construct(
        private readonly string $basePath,
        private readonly string $storageDir
    ) {
    }

    /**
     * Backup a list of files, optionally resuming from $cursor with a bounded
     * amount of work per call.
     *
     * A full backup may copy thousands of files; on shared hosting a single
     * request must not do all of it. With $budget/$maxFiles set, each call
     * copies at most $maxFiles files starting at $cursor and appends their
     * metadata to an incremental items.jsonl inside the backup directory.
     * When the whole list has been copied, the final manifest.json (the same
     * shape RollbackManager reads) is written and 'done' is true.
     *
     * @param array<int,string> $files
     * @return array{done:bool,cursor:int,backup_id:string,items:array<int,array<string,mixed>>,manifest?:array<string,mixed>,total?:int}
     */
    public function backup(string $jobId, array $files, int $cursor = 0, ?WorkBudget $budget = null, int $maxFiles = 150): array
    {
        $files = array_values(array_filter(array_map(
            static fn (mixed $f): string => str_replace('\\', '/', trim((string)$f)),
            $files
        ), static fn (string $f): bool => $f !== ''));

        $total = count($files);
        $startCursor = max(0, $cursor);
        if ($startCursor > $total) {
            $startCursor = $total;
        }

        // Stable backup id across steps: derive it from the job id + a
        // per-job nonce persisted in the backup dir itself, so a crash between
        // steps never spawns a second directory for the same job.
        $nonceFile = $this->storageDir . '/backups/.nonce_' . basename($jobId);
        if ($startCursor === 0 && !is_file($nonceFile)) {
            $backupId = 'backup_' . basename($jobId) . '_' . gmdate('Ymd_His');
            file_put_contents($nonceFile, $backupId);
        } else {
            $backupId = (string)@file_get_contents($nonceFile) ?: 'backup_' . basename($jobId) . '_' . gmdate('Ymd_His');
        }

        $backupDir = $this->storageDir . '/backups/' . $backupId;
        $filesDir = $backupDir . '/files';
        if (!is_dir($filesDir)) {
            mkdir($filesDir, 0775, true);
        }
        $itemsFile = $backupDir . '/items.jsonl';
        // A fresh backup pass (cursor 0) must start from an empty items
        // ledger: a previous attempt that crashed mid-backup may have left
        // partial entries, and appending to them would produce duplicate or
        // stale items in the assembled manifest.
        if ($startCursor === 0 && is_file($itemsFile)) {
            @unlink($itemsFile);
        } else {
            // A retried chunk (cursor > 0) can likewise duplicate entries when
            // the previous attempt died mid-chunk after appending a few items
            // but before the caller committed the new cursor. Trim the ledger
            // back to the committed cursor before appending the retried files.
            $this->trimJsonlToCursor($itemsFile, $startCursor);
        }

        $chunk = [];
        $position = $startCursor;
        while ($position < $total && count($chunk) < $maxFiles) {
            if ($budget !== null && $budget->exhausted()) {
                break;
            }
            $relative = $files[$position];
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
            $item = [
                'path' => $relative,
                'existed' => $exists,
                'sha256' => $exists ? (hash_file('sha256', $source) ?: null) : null,
                'size_bytes' => $exists ? filesize($source) : null,
            ];
            file_put_contents($itemsFile, json_encode($item, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . PHP_EOL, FILE_APPEND);
            $chunk[] = $item;
            $position++;
            // Per-file budget check: on slow shared hosting, a single large
            // file copy + SHA256 hash can consume most of the time budget.
            // Check after every file so we never overshoot by an entire chunk.
            if ($budget !== null && $budget->exhausted()) {
                break;
            }
        }

        if ($position >= $total) {
            // All files copied - assemble the final manifest (same shape as
            // the original single-shot backup) from the incremental items.
            $items = $this->readItems($itemsFile);
            $manifest = [
                'backup_id' => $backupId,
                'job_id' => $jobId,
                'created_at' => gmdate('c'),
                'items' => $items,
            ];
            file_put_contents($backupDir . '/manifest.json', json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
            return [
                'done' => true,
                'cursor' => $total,
                'backup_id' => $backupId,
                'total' => $total,
                'items' => $chunk,
                'manifest' => $manifest,
            ];
        }

        return [
            'done' => false,
            'cursor' => $position,
            'backup_id' => $backupId,
            'total' => $total,
            'items' => $chunk,
        ];
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    private function readItems(string $itemsFile): array
    {
        $items = [];
        $handle = @fopen($itemsFile, 'r');
        if ($handle === false) {
            return $items;
        }
        while (($line = fgets($handle)) !== false) {
            $decoded = json_decode((string)$line, true);
            if (is_array($decoded)) {
                $items[] = $decoded;
            }
        }
        fclose($handle);
        return $items;
    }

    /**
     * Keep at most $cursor lines of the items ledger. A failed chunk may have
     * appended entries for files whose cursor was never committed; trimming to
     * the committed cursor before appending the retried chunk prevents
     * duplicate entries in the final assembled manifest.
     */
    private function trimJsonlToCursor(string $path, int $cursor): void
    {
        if ($cursor <= 0 || !is_file($path)) {
            return;
        }
        $lines = file($path, FILE_IGNORE_NEW_LINES);
        if ($lines === false || count($lines) <= $cursor) {
            return;
        }
        file_put_contents($path, implode(PHP_EOL, array_slice($lines, 0, $cursor)) . PHP_EOL);
    }
}
