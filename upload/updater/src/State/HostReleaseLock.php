<?php
declare(strict_types=1);

namespace Updater\State;

/** Cross-process release lock usable from PHP CLI on shared hosting. */
final class HostReleaseLock
{
    /** @var resource|null */
    private $handle = null;

    public function __construct(private readonly string $basePath)
    {
    }

    /** Read-only proof that a protocol-1 owner currently holds its permanent inode. */
    public static function isHeld(string $basePath): bool
    {
        $base = realpath($basePath);
        if ($base === false) { return false; }
        $path = $base . '/storage_api/release-coordinator/host-release.lock';
        clearstatcache(true, $path);
        $stat = @lstat($path);
        if ($stat === false) { return false; }
        if (is_link($path) || ($stat['mode'] & 0170000) !== 0100000 || ($stat['mode'] & 0077) !== 0
            || $stat['nlink'] !== 1) { throw new \RuntimeException('Unsafe protocol-1 release guard file.'); }
        $handle = @fopen($path, 'rb');
        if ($handle === false) { throw new \RuntimeException('Protocol-1 release guard unavailable.'); }
        try {
            $opened = fstat($handle);
            if ($opened === false || $opened['dev'] !== $stat['dev'] || $opened['ino'] !== $stat['ino']) {
                throw new \RuntimeException('Protocol-1 release guard identity changed.');
            }
            if (@flock($handle, LOCK_EX | LOCK_NB)) { flock($handle, LOCK_UN); return false; }
            return true;
        } finally { fclose($handle); }
    }

    /**
     * Acquire the permanent host-release inode exclusively without waiting.
     * A false result means another release supervisor owns it.
     */
    public function acquire(): bool
    {
        if ($this->handle !== null) {
            throw new \LogicException('Host release lock already acquired.');
        }
        $base = realpath($this->basePath);
        if ($base === false) {
            throw new \RuntimeException('Host release root unavailable.');
        }
        $storage = $base . '/storage_api';
        $storageStat = @lstat($storage);
        if ($storageStat === false || ($storageStat['mode'] & 0170000) !== 0040000) {
            throw new \RuntimeException('Host release storage unavailable.');
        }
        $directory = $storage . '/release-coordinator';
        if (is_link($directory) || (!is_dir($directory) && !@mkdir($directory, 0700) && !is_dir($directory))) {
            throw new \RuntimeException('Host release lock directory unavailable.');
        }
        clearstatcache(true, $directory);
        $directoryStat = @lstat($directory);
        if ($directoryStat === false || ($directoryStat['mode'] & 0170000) !== 0040000
            || ($directoryStat['mode'] & 0077) !== 0) {
            throw new \RuntimeException('Host release lock directory must be private.');
        }

        $path = $directory . '/host-release.lock';
        if (is_link($path)) {
            throw new \RuntimeException('Unsafe host release lock file.');
        }
        $pathStatBefore = @lstat($path);
        if ($pathStatBefore !== false && ($pathStatBefore['mode'] & 0170000) !== 0100000) {
            throw new \RuntimeException('Unsafe host release lock file.');
        }
        $oldUmask = umask(0077);
        try {
            $handle = @fopen($path, 'c+b');
        } finally {
            umask($oldUmask);
        }
        if ($handle === false) {
            throw new \RuntimeException('Host release lock unavailable.');
        }
        $fileStat = fstat($handle);
        clearstatcache(true, $path);
        $pathStatAfter = @lstat($path);
        clearstatcache(true, $directory);
        $directoryStatAfter = @lstat($directory);
        if ($fileStat === false || ($fileStat['mode'] & 0170000) !== 0100000
            || ($fileStat['mode'] & 0077) !== 0 || $fileStat['nlink'] !== 1
            || $fileStat['uid'] !== $directoryStat['uid'] || $pathStatAfter === false
            || ($pathStatAfter['mode'] & 0170000) !== 0100000
            || $fileStat['dev'] !== $pathStatAfter['dev'] || $fileStat['ino'] !== $pathStatAfter['ino']
            || $directoryStatAfter === false || ($directoryStatAfter['mode'] & 0170000) !== 0040000
            || $directoryStat['dev'] !== $directoryStatAfter['dev']
            || $directoryStat['ino'] !== $directoryStatAfter['ino']) {
            fclose($handle);
            throw new \RuntimeException('Unsafe host release lock metadata.');
        }
        if (!flock($handle, LOCK_EX | LOCK_NB)) {
            fclose($handle);
            return false;
        }
        $this->handle = $handle;
        return true;
    }

    /**
     * Persist a one-time protocol-2 claim authorization while this exact
     * protocol-1 process still owns the v1 inode. The random token is returned
     * only over the live SSH pipe; only its hash is stored on disk. The separate
     * protocol-2 lease inode can then be claimed before this v1 lock is released.
     *
     * @return array<string,mixed>
     */
    public function prepareBootstrapIntent(string $runId, string $claimId, string $targetSha,
                                           string $manifestSha256, string $token): array
    {
        if (!is_resource($this->handle)) {
            throw new \RuntimeException('BOOTSTRAP_INTENT_REQUIRES_V1_OWNER');
        }
        foreach ([[$runId, '/\\A[a-f0-9]{32}\\z/D'], [$claimId, '/\\A[a-f0-9]{32}\\z/D'],
                  [$targetSha, '/\\A[a-f0-9]{40}\\z/iD'], [$manifestSha256, '/\\A[a-f0-9]{64}\\z/D'],
                  [$token, '/\\A[a-f0-9]{64}\\z/D']] as [$value, $pattern]) {
            if (preg_match($pattern, $value) !== 1) {
                throw new \InvalidArgumentException('BOOTSTRAP_INTENT_BINDING_INVALID');
            }
        }
        $base = realpath($this->basePath);
        if ($base === false) { throw new \RuntimeException('BOOTSTRAP_INTENT_ROOT_UNAVAILABLE'); }
        $directory = $base . '/storage_api/release-coordinator';
        clearstatcache(true, $directory);
        $dir = @lstat($directory);
        if ($dir === false || is_link($directory) || ($dir['mode'] & 0170000) !== 0040000
            || ($dir['mode'] & 0077) !== 0 || $dir['uid'] !== fileowner($this->basePath)) {
            throw new \RuntimeException('BOOTSTRAP_INTENT_DIRECTORY_UNSAFE');
        }
        $leaseRecord = $directory . '/host-release.lease.json';
        if (file_exists($leaseRecord) || is_link($leaseRecord)) {
            throw new \RuntimeException('BOOTSTRAP_INTENT_AFTER_V2_CLAIM');
        }
        $path = $directory . '/bootstrap-intent.json';
        $intent = ['schema_version' => 1, 'purpose' => 'bootstrap-demo', 'run_id' => $runId,
            'claim_id' => $claimId, 'target_sha' => strtolower($targetSha),
            'manifest_sha256' => $manifestSha256, 'token_hash' => hash('sha256', $token),
            'created_at' => time(), 'expires_at' => time() + 900];
        $existing = @lstat($path);
        if ($existing !== false) {
            if (is_link($path) || ($existing['mode'] & 0170000) !== 0100000 || ($existing['mode'] & 0077) !== 0
                || $existing['nlink'] !== 1 || $existing['uid'] !== $dir['uid'] || $existing['size'] > 4096) {
                throw new \RuntimeException('BOOTSTRAP_INTENT_UNSAFE');
            }
            $prior = json_decode((string)@file_get_contents($path), true);
            foreach (['schema_version', 'purpose', 'run_id', 'claim_id', 'target_sha', 'manifest_sha256', 'token_hash'] as $key) {
                if (($prior[$key] ?? null) !== $intent[$key]) { throw new \RuntimeException('BOOTSTRAP_INTENT_ALREADY_EXISTS'); }
            }
            return ['accepted' => true, 'protocol' => 1, 'purpose' => 'bootstrap-demo',
                'run_id' => $runId, 'claim_id' => $claimId, 'target_sha' => strtolower($targetSha),
                'manifest_sha256' => $manifestSha256, 'intent_token' => $token,
                'expires_at' => $prior['expires_at'] ?? 0];
        }
        $handle = @fopen($path, 'x+b');
        if ($handle === false) { throw new \RuntimeException('BOOTSTRAP_INTENT_ALREADY_EXISTS'); }
        try {
            if (!@chmod($path, 0600)) { throw new \RuntimeException('BOOTSTRAP_INTENT_WRITE_FAILED'); }
            $encoded = json_encode($intent, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n";
            if (fwrite($handle, $encoded) !== strlen($encoded) || !fflush($handle)) {
                throw new \RuntimeException('BOOTSTRAP_INTENT_WRITE_FAILED');
            }
            if (function_exists('fsync') && !fsync($handle)) {
                throw new \RuntimeException('BOOTSTRAP_INTENT_SYNC_FAILED');
            }
        } catch (\Throwable $error) {
            fclose($handle); @unlink($path); throw $error;
        }
        fclose($handle);
        return ['accepted' => true, 'protocol' => 1, 'purpose' => 'bootstrap-demo',
            'run_id' => $runId, 'claim_id' => $claimId, 'target_sha' => strtolower($targetSha),
            'manifest_sha256' => $manifestSha256, 'intent_token' => $token,
            'expires_at' => $intent['expires_at']];
    }

    public function release(): void
    {
        if ($this->handle !== null) {
            fclose($this->handle);
            $this->handle = null;
        }
        // Keep the inode: unlinking it could let another process lock a new one.
    }

    public function __destruct()
    {
        $this->release();
    }
}
