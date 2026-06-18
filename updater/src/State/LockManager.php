<?php
declare(strict_types=1);

namespace Updater\State;

final class LockManager
{
    private ?string $lockFile = null;

    public function __construct(private readonly string $storageDir)
    {
    }

    public function isLocked(): bool
    {
        return is_file($this->storageDir . '/locks/update.lock');
    }

    public function acquire(string $jobId): void
    {
        $dir = $this->storageDir . '/locks';
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        $path = $dir . '/update.lock';
        if (is_file($path)) {
            throw new \RuntimeException('Another update is already running.');
        }

        file_put_contents($path, json_encode([
            'job_id' => $jobId,
            'created_at' => gmdate('c'),
            'pid' => getmypid(),
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        $this->lockFile = $path;
    }

    public function release(): void
    {
        $path = $this->lockFile ?: $this->storageDir . '/locks/update.lock';
        if (is_file($path)) {
            @unlink($path);
        }
        $this->lockFile = null;
    }
}
