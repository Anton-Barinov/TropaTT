<?php
declare(strict_types=1);

namespace Updater\Apply;

final class MaintenanceMode
{
    public function __construct(private readonly string $basePath)
    {
    }

    public function enable(string $jobId): void
    {
        $this->validateJob($jobId);
        $flag = $this->flagPath();
        $dir = dirname($flag);
        if (!is_dir($dir)) {
            if (!mkdir($dir, 0775, true) && !is_dir($dir)) {
                throw new \RuntimeException('Maintenance directory unavailable.');
            }
        }
        if (file_exists($flag)) {
            $this->assertOwner($flag, $jobId);
            return; // Resume preserves the original ownership and timestamp.
        }
        $body = json_encode([
            'job_id' => $jobId,
            'enabled_at' => gmdate('c'),
            'reason' => 'core_update_apply',
        ], JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        // Exclusive creation never replaces another operation's maintenance.
        $handle = @fopen($flag, 'xb');
        if ($handle === false) {
            $this->assertOwner($flag, $jobId);
            return;
        }
        try {
            if (fwrite($handle, $body) !== strlen($body) || !fflush($handle) || !fsync($handle)) {
                throw new \RuntimeException('Maintenance flag write incomplete.');
            }
        } finally {
            fclose($handle);
        }
        // A partial flag after a crash intentionally stays closed for recovery.
    }

    /** Caller holds the installation EX mutex for the whole state transition. */
    public function disable(string $jobId): void
    {
        $this->validateJob($jobId);
        $flag = $this->flagPath();
        if (file_exists($flag)) {
            $this->assertOwner($flag, $jobId);
            if (!unlink($flag)) {
                throw new \RuntimeException('Maintenance flag removal failed.');
            }
        }
    }

    private function validateJob(string $jobId): void
    {
        if (!preg_match('/\A[A-Za-z0-9._-]{1,128}\z/D', $jobId)) {
            throw new \InvalidArgumentException('Invalid maintenance job identity.');
        }
    }

    private function assertOwner(string $flag, string $jobId): void
    {
        clearstatcache(true, $flag);
        $info = @lstat($flag);
        if ($info === false || ($info['mode'] & 0170000) !== 0100000
            || $info['nlink'] !== 1 || $info['size'] > 4096) {
            throw new \RuntimeException('Unsafe maintenance flag.');
        }
        $body = @file_get_contents($flag);
        $owner = is_string($body) ? json_decode($body, true) : null;
        if (!is_array($owner) || ($owner['job_id'] ?? null) !== $jobId
            || ($owner['reason'] ?? null) !== 'core_update_apply') {
            throw new \RuntimeException('Maintenance belongs to another operation or needs recovery.');
        }
    }

    private function flagPath(): string
    {
        if (is_link($this->basePath . '/storage_api') || is_link($this->basePath . '/storage_api/maintenance.flag')) {
            throw new \RuntimeException('Unsafe maintenance path.');
        }
        return $this->basePath . '/storage_api/maintenance.flag';
    }
}
