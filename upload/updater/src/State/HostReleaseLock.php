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
