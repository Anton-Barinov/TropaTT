<?php
declare(strict_types=1);

namespace Updater\State;

/**
 * Single-writer lock for updater apply/rollback.
 *
 * The lock is a plain file under storage/locks/update.lock. On a hard crash
 * (uncatchable memory-limit fatal, segfault, PHP-FPM worker kill, OOM killer)
 * the release() call in the catch block never runs, which would otherwise
 * leave the lock in place and block ALL future updates forever. To survive
 * that on plain shared hosting, a lock is considered STALE (and therefore
 * not held) when either:
 *   - it is older than the TTL (mtime), or
 *   - its recorded PID is no longer alive (POSIX pid liveness check, skipped
 *     when the posix extension is unavailable - then mtime alone decides).
 * acquire() transparently clears a stale lock before taking it.
 */
final class LockManager
{
    private const DEFAULT_TTL_SECONDS = 3600; // 1 hour

    private ?string $lockFile = null;

    public function __construct(
        private readonly string $storageDir,
        private readonly int $ttlSeconds = self::DEFAULT_TTL_SECONDS
    ) {
    }

    public function isLocked(): bool
    {
        return $this->lockState() !== null;
    }

    /**
     * True when a lock file exists but belongs to a dead/old process, i.e.
     * it will not block the next acquire(). Exposed for preflight checks.
     */
    public function isStale(): bool
    {
        $state = $this->lockStateAt($this->storageDir . '/locks/update.lock');
        return $state !== null && ($state['stale'] ?? false) === true;
    }

    public function acquire(string $jobId): void
    {
        $dir = $this->storageDir . '/locks';
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        $path = $dir . '/update.lock';
        $state = $this->lockStateAt($path);
        if ($state !== null && ($state['stale'] ?? false) !== true) {
            throw new \RuntimeException('Another update is already running.');
        }
        // A stale lock (dead PID or past TTL) is safe to reclaim.
        if ($state !== null) {
            @unlink($path);
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

    /**
     * @return array{path:string,stale:bool,created_at:?string,pid:?int}|null
     *         null when no lock is held (no file, or the file is stale)
     */
    private function lockState(): ?array
    {
        $state = $this->lockStateAt($this->storageDir . '/locks/update.lock');
        // A stale lock (dead PID or past TTL) is not a held lock: it must not
        // block preflight's no_active_lock check or the next acquire().
        if ($state !== null && ($state['stale'] ?? false) === true) {
            return null;
        }
        return $state;
    }

    /**
     * @return array{path:string,stale:bool,created_at:?string,pid:?int}|null
     */
    private function lockStateAt(string $path): ?array
    {
        if (!is_file($path)) {
            return null;
        }
        $data = json_decode((string)file_get_contents($path), true);
        $createdAt = is_array($data) ? (string)($data['created_at'] ?? '') : '';
        $pid = is_array($data) ? (int)($data['pid'] ?? 0) : 0;

        $age = $createdAt !== '' ? (time() - strtotime($createdAt)) : PHP_INT_MAX;
        $pastTtl = $age > $this->ttlSeconds;
        $pidDead = $pid > 0 && !$this->pidAlive($pid);

        return [
            'path' => $path,
            'stale' => $pastTtl || $pidDead,
            'created_at' => $createdAt !== '' ? $createdAt : null,
            'pid' => $pid > 0 ? $pid : null,
        ];
    }

    private function pidAlive(int $pid): bool
    {
        // Without the posix extension we cannot probe the PID; fall back to
        // the mtime TTL only. Reused PIDs are an accepted rare edge case - a
        // fresh lock written by the new owner updates the mtime anyway.
        if (!function_exists('posix_kill')) {
            return true;
        }
        return @posix_kill($pid, 0);
    }
}
