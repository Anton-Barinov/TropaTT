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
 * not held) when:
 *   - a heartbeat (heartbeat_at) is present and older than the TTL, or
 *   - no heartbeat is present (legacy lock) and it is older than the TTL
 *     (mtime), or
 *   - no heartbeat is present and its recorded PID is no longer alive (POSIX
 *     pid liveness check, skipped when the posix extension is unavailable -
 *     then mtime alone decides).
 *
 * Apply/rollback run as MANY short HTTP requests (a resumable step machine,
 * see UpdaterKernel) instead of one long one, so each step's web-server PID
 * dies between requests. A fresh heartbeat_at is therefore the source of
 * truth for a live multi-step job; the PID check only applies to legacy
 * locks that predate heartbeats. Every step calls renew() to keep the job's
 * lock fresh, and renew()/acquire() transparently reclaim a stale lock.
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
            'heartbeat_at' => gmdate('c'),
            'pid' => getmypid(),
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        $this->lockFile = $path;
    }

    /**
     * Refresh the heartbeat of a lock owned by $jobId so a multi-request
     * apply/rollback job keeps its lock across steps.
     *
     * A stale lock is transparently reclaimed and re-issued to $jobId (a
     * stale owner is dead by definition - either its process died or its
     * heartbeat lapsed), mirroring acquire() semantics.
     *
     * @return bool true when the lock is now held by $jobId, false when the
     *              lock is held by a DIFFERENT, still-fresh job
     */
    public function renew(string $jobId): bool
    {
        $path = $this->storageDir . '/locks/update.lock';
        $state = $this->lockStateAt($path);
        if ($state !== null && ($state['stale'] ?? false) !== true) {
            $owner = (string)($state['owner_job_id'] ?? '');
            if ($owner !== '' && $owner !== $jobId) {
                return false;
            }
        }
        // Lock is free, stale, or already ours - take/renew it.
        if ($state !== null) {
            @unlink($path);
        }
        $this->acquire($jobId);
        return true;
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
     * Force-remove the lock file from the admin panel. Always succeeds — a
     * missing file is not an error. Returns whether a file was actually
     * deleted.
     */
    public function forceRelease(): bool
    {
        $path = $this->storageDir . '/locks/update.lock';
        if (!is_file($path)) {
            $this->lockFile = null;
            return false;
        }
        $deleted = @unlink($path);
        $this->lockFile = null;
        return $deleted;
    }

    /**
     * @return array{path:string,stale:bool,created_at:?string,heartbeat_at:?string,pid:?int,owner_job_id:?string}|null
     *         null when no lock is held (no file, or the file is stale)
     */
    private function lockState(): ?array
    {
        $state = $this->lockStateAt($this->storageDir . '/locks/update.lock');
        // A stale lock (dead PID/heartbeat or past TTL) is not a held lock: it
        // must not block preflight's no_active_lock check or the next acquire().
        if ($state !== null && ($state['stale'] ?? false) === true) {
            return null;
        }
        return $state;
    }

    /**
     * @return array{path:string,stale:bool,created_at:?string,heartbeat_at:?string,pid:?int,owner_job_id:?string}|null
     */
    private function lockStateAt(string $path): ?array
    {
        if (!is_file($path)) {
            return null;
        }
        $data = json_decode((string)file_get_contents($path), true);
        if (!is_array($data)) {
            return null;
        }
        $createdAt = (string)($data['created_at'] ?? '');
        $heartbeatAt = (string)($data['heartbeat_at'] ?? '');
        $pid = (int)($data['pid'] ?? 0);
        $jobId = (string)($data['job_id'] ?? '');

        // A multi-step job renews heartbeat_at on every request; that heartbeat
        // is the freshness source of truth. Legacy locks (no heartbeat) fall
        // back to created_at age plus the PID liveness probe.
        $hasHeartbeat = $heartbeatAt !== '';
        $reference = $hasHeartbeat ? $heartbeatAt : $createdAt;
        $age = $reference !== '' ? (time() - strtotime($reference)) : PHP_INT_MAX;

        // A lock with a dead PID cannot be renewed — no process is alive to
        // extend the heartbeat. Use a short grace period (5 min) so a crashed
        // multi-step job does not block updates for the full hour. Active
        // jobs send the next step within seconds, so the short window is safe.
        // When the posix extension is unavailable the PID is assumed alive
        // (the full TTL applies) — the lock will still expire after one hour.
        $pidIsDead = $pid > 0 && !$this->pidAlive($pid);
        if ($hasHeartbeat && $pidIsDead) {
            $pastTtl = $age > 300;
        } else {
            $pastTtl = $age > $this->ttlSeconds;
        }
        $pidDead = !$hasHeartbeat && $pidIsDead;

        return [
            'path' => $path,
            'stale' => $pastTtl || $pidDead,
            'created_at' => $createdAt !== '' ? $createdAt : null,
            'heartbeat_at' => $heartbeatAt !== '' ? $heartbeatAt : null,
            'pid' => $pid > 0 ? $pid : null,
            'owner_job_id' => $jobId !== '' ? $jobId : null,
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
