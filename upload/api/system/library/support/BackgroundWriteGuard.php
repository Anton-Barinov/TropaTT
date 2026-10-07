<?php
declare(strict_types=1);

namespace Api\System\Library\Support;

/** Installation-local guard for CLI workers; no database or provider I/O. */
final class BackgroundWriteGuard
{
    public static function hold(string $projectRoot): ?\Updater\State\DeploymentMutex
    {
        $root = realpath($projectRoot);
        if ($root === false) {
            throw new \RuntimeException('Background guard root unavailable.');
        }
        if (is_file($root . '/storage_api/maintenance.flag')) {
            return null;
        }
        require_once $root . '/updater/src/State/DeploymentMutex.php';
        $guard = new \Updater\State\DeploymentMutex($root);
        if (!$guard->acquire(false)) {
            return null;
        }
        // An older/manual writer might have set maintenance while we acquired.
        clearstatcache(true, $root . '/storage_api/maintenance.flag');
        if (is_file($root . '/storage_api/maintenance.flag')) {
            $guard->release();
            return null;
        }
        return $guard;
    }

    /** @param list<string> $argv */
    public static function enterOrExit(string $projectRoot, array $argv = []): \Updater\State\DeploymentMutex
    {
        try {
            $guard = self::hold($projectRoot);
            if ($guard !== null) {
                return $guard;
            }
            if (in_array('--json', $argv, true)) {
                echo json_encode(['ok' => true, 'skipped' => true, 'reason' => 'DEPLOYMENT_OR_MAINTENANCE'], JSON_THROW_ON_ERROR) . PHP_EOL;
            } else {
                echo '[SKIP] Background work paused for deployment or maintenance.' . PHP_EOL;
            }
            exit(0);
        } catch (\Throwable $e) {
            fwrite(STDERR, '[FAIL] Background write guard unavailable; no work performed.' . PHP_EOL);
            exit(2);
        }
    }
}
