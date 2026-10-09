<?php
declare(strict_types=1);

namespace Updater\State;

/**
 * Installation-scoped request/update mutex on shared hosting.
 *
 * Normal API/web requests and cooperating background readers use a shared
 * lock; updater and release mutations use the exclusive lock on the same
 * permanent inode. The exclusive side therefore drains in-flight PHP work
 * before changing files or the database. Never unlink the lock file.
 */
final class DeploymentMutex
{
    /** @var resource|null */
    private $handle = null;

    public function __construct(private readonly string $basePath)
    {
    }

    /** Read-only point-in-time check for a competing deployment/request writer. */
    public static function isBusy(string $basePath): bool
    {
        $base = realpath($basePath);
        if ($base === false) { return true; }
        $storage = $base . '/storage_api';
        $storageStat = @lstat($storage);
        if (!is_array($storageStat) || is_link($storage)
            || ($storageStat['mode'] & 0170000) !== 0040000) { return true; }
        $directory = $storage . '/release-coordinator';
        clearstatcache(true, $directory);
        $directoryStat = @lstat($directory);
        if ($directoryStat === false) { return false; }
        if (is_link($directory) || ($directoryStat['mode'] & 0170000) !== 0040000
            || ($directoryStat['mode'] & 0077) !== 0
            || $directoryStat['uid'] !== $storageStat['uid']) { return true; }
        $path = $directory . '/installation-release.lock';
        clearstatcache(true, $path);
        $pathStat = @lstat($path);
        if ($pathStat === false) { return false; }
        if (is_link($path) || ($pathStat['mode'] & 0170000) !== 0100000
            || ($pathStat['mode'] & 0077) !== 0 || $pathStat['nlink'] !== 1
            || $pathStat['uid'] !== $directoryStat['uid']) { return true; }
        $handle = @fopen($path, 'rb');
        if ($handle === false) { return true; }
        try {
            $opened = fstat($handle);
            if (!is_array($opened) || $opened['dev'] !== $pathStat['dev']
                || $opened['ino'] !== $pathStat['ino']) { return true; }
            if (!flock($handle, LOCK_SH | LOCK_NB)) { return true; }
            flock($handle, LOCK_UN);
            return false;
        } finally {
            fclose($handle);
        }
    }

    public function acquire(bool $exclusive = true): bool
    {
        if ($this->handle !== null) {
            throw new \LogicException('Deployment mutex already acquired.');
        }
        $base = realpath($this->basePath);
        if ($base === false) {
            throw new \RuntimeException('Deployment mutex base unavailable.');
        }
        $storage = $base . '/storage_api';
        if (is_link($storage) || !is_dir($storage)) {
            throw new \RuntimeException('Deployment mutex storage unavailable.');
        }
        $directory = $storage . '/release-coordinator';
        if (is_link($directory) || (!is_dir($directory) && !@mkdir($directory, 0700) && !is_dir($directory))) {
            throw new \RuntimeException('Deployment mutex directory unavailable.');
        }
        clearstatcache(true, $directory);
        $directoryStat = @stat($directory);
        if ($directoryStat === false || ($directoryStat['mode'] & 0077) !== 0) {
            throw new \RuntimeException('Deployment mutex directory must be private.');
        }
        $path = $directory . '/installation-release.lock';
        if (is_link($path)) {
            throw new \RuntimeException('Unsafe deployment mutex file.');
        }
        $oldUmask = umask(0077);
        try {
            $handle = @fopen($path, 'c+b');
        } finally {
            umask($oldUmask);
        }
        if ($handle === false) {
            throw new \RuntimeException('Deployment mutex unavailable.');
        }
        $fileStat = fstat($handle);
        if ($fileStat === false || ($fileStat['mode'] & 0170000) !== 0100000
            || ($fileStat['mode'] & 0077) !== 0 || $fileStat['nlink'] !== 1
            || $fileStat['uid'] !== $directoryStat['uid']) {
            fclose($handle);
            throw new \RuntimeException('Unsafe deployment mutex file.');
        }
        if (!flock($handle, ($exclusive ? LOCK_EX : LOCK_SH) | LOCK_NB)) {
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
        // Never unlink: a new inode would permit two simultaneous owners.
    }

    public function __destruct()
    {
        $this->release();
    }
}
