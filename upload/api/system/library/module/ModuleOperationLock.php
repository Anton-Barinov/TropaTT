<?php
declare(strict_types=1);

namespace Api\System\Library\Module;

use RuntimeException;

final class ModuleOperationLock
{
    private static array $held = [];
    private bool $released = false;

    private function __construct(private readonly string $key)
    {
    }

    public static function acquire(string $projectRoot, string $moduleName): self
    {
        if (!preg_match('/^[a-z0-9]+\.[a-z0-9][a-z0-9-]*$/', $moduleName)) {
            throw new RuntimeException('Invalid module operation lock name.');
        }
        $root = realpath($projectRoot);
        if ($root === false || !is_dir($root) || is_link($projectRoot)) {
            throw new RuntimeException('Unsafe project root for module operation lock.');
        }
        $key = $root . "\0" . $moduleName;
        if (isset(self::$held[$key])) {
            self::$held[$key]['depth']++;
            return new self($key);
        }

        $storageRoot = $root . '/storage_api';
        if (is_link($storageRoot)) {
            throw new RuntimeException('Unsafe module operation lock storage.');
        }
        if (!is_dir($storageRoot) && !@mkdir($storageRoot, 0700) && !is_dir($storageRoot)) {
            throw new RuntimeException('Cannot create module operation lock storage.');
        }
        clearstatcache(true, $storageRoot);
        $storageMode = @fileperms($storageRoot);
        $realStorageRoot = realpath($storageRoot);
        if (!is_int($storageMode) || ($storageMode & 0022) !== 0 || $realStorageRoot === false
            || !str_starts_with($realStorageRoot, $root . DIRECTORY_SEPARATOR)) {
            throw new RuntimeException('Module operation lock storage is unsafe or writable by other users.');
        }

        $lockDir = $storageRoot . '/locks';
        if (is_link($lockDir)) {
            throw new RuntimeException('Unsafe module operation lock directory.');
        }
        if (!is_dir($lockDir) && !@mkdir($lockDir, 0700) && !is_dir($lockDir)) {
            throw new RuntimeException('Cannot create module operation lock directory.');
        }
        $realLockDir = realpath($lockDir);
        if ($realLockDir === false || !str_starts_with($realLockDir, $realStorageRoot . DIRECTORY_SEPARATOR)) {
            throw new RuntimeException('Module operation lock directory escapes the project root.');
        }
        if (!@chmod($lockDir, 0700)) {
            throw new RuntimeException('Cannot secure module operation lock directory.');
        }
        clearstatcache(true, $lockDir);
        $lockDirMode = @fileperms($lockDir);
        if (!is_int($lockDirMode) || ($lockDirMode & 0077) !== 0) {
            throw new RuntimeException('Module operation lock directory is not private.');
        }

        $path = $lockDir . '/module-' . hash('sha256', $moduleName) . '.lock';
        if (is_link($path)) {
            throw new RuntimeException('Unsafe module operation lock file.');
        }
        $handle = @fopen($path, 'c');
        if ($handle === false) {
            throw new RuntimeException('Cannot open module operation lock file.');
        }
        $opened = fstat($handle);
        $named = @lstat($path);
        if (!is_array($opened) || !is_array($named)
            || (($opened['mode'] & 0170000) !== 0100000)
            || (($named['mode'] & 0170000) !== 0100000)
            || $opened['dev'] !== $named['dev'] || $opened['ino'] !== $named['ino']) {
            fclose($handle);
            throw new RuntimeException('Module operation lock file changed or is not a regular file.');
        }
        if (!@chmod($path, 0600)) {
            fclose($handle);
            throw new RuntimeException('Cannot secure module operation lock file.');
        }
        clearstatcache(true, $path);
        $lockMode = @fileperms($path);
        if (!is_int($lockMode) || ($lockMode & 0077) !== 0) {
            fclose($handle);
            throw new RuntimeException('Module operation lock file is not private.');
        }
        if (!flock($handle, LOCK_EX | LOCK_NB)) {
            fclose($handle);
            throw new RuntimeException('MODULE_BUSY');
        }
        self::$held[$key] = ['handle' => $handle, 'depth' => 1];
        return new self($key);
    }

    public function release(): void
    {
        if ($this->released || !isset(self::$held[$this->key])) {
            $this->released = true;
            return;
        }
        $this->released = true;
        self::$held[$this->key]['depth']--;
        if (self::$held[$this->key]['depth'] > 0) {
            return;
        }
        $handle = self::$held[$this->key]['handle'];
        unset(self::$held[$this->key]);
        flock($handle, LOCK_UN);
        fclose($handle);
    }

    public function __destruct()
    {
        $this->release();
    }
}
