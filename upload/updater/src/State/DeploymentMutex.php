<?php
declare(strict_types=1);

namespace Updater\State;

/** Excludes updater writes while a deployment supervisor holds the same inode. */
final class DeploymentMutex
{
    /** @var resource|null */
    private $handle = null;

    public function __construct(private readonly string $basePath)
    {
    }

    public function acquire(): bool
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
        // Never unlink: a new inode would permit two simultaneous owners.
    }

    public function __destruct()
    {
        $this->release();
    }
}
