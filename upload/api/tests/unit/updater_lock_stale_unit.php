<?php
declare(strict_types=1);

/**
 * Regression test: LockManager must treat a STALE lock (dead PID or past TTL)
 * as NOT held, so a hard crash cannot block ALL future updates forever.
 *
 * Real incident (2026-08-01, demo.tropatt.com): a rollback died with an
 * UNCATCHABLE memory-limit fatal BEFORE $lock->release() ran (see
 * updater_db_restore_streaming_unit.php for the root cause), leaving
 * storage_api/updates/locks/update.lock in place. The old isLocked() only
 * checked file existence, so every subsequent preflight failed with
 * no_active_lock=false and the update page stayed blocked until the lock file
 * was removed by hand. On shared hosting that means: one crash = the CRM can
 * never update again.
 *
 * This test guards the fix: stale locks must not block preflight,
 * acquire() must transparently reclaim them, and a fresh lock from a live
 * process must still serialize concurrent updates.
 *
 * Run: php upload/api/tests/unit/updater_lock_stale_unit.php
 * Exit code 0 = all checks passed.
 */

$upload = dirname(__DIR__, 3); // upload/ (unit tests live under upload/api/tests/unit)

spl_autoload_register(static function (string $class) use ($upload): void {
    $prefix = 'Updater\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }
    $path = $upload . '/updater/src/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
    if (is_file($path)) {
        require_once $path;
    }
});

$passes = 0;
$failures = 0;
function check(bool $cond, string $label): void
{
    global $passes, $failures;
    if ($cond) {
        $passes++;
        echo "  OK  $label\n";
    } else {
        $failures++;
        echo "  FAIL $label\n";
    }
}

// --- 1) Source contract: stale detection must exist (TTL + PID liveness) ---
$src = (string)file_get_contents($upload . '/updater/src/State/LockManager.php');
check(str_contains($src, 'ttlSeconds'), 'LockManager has a TTL for lock age');
check(str_contains($src, 'posix_kill'), 'LockManager checks PID liveness via posix_kill');
check(str_contains($src, 'isStale'), 'LockManager exposes isStale()');

// --- 2) Behavior: stale lock (past TTL) does not block, acquire() reclaims ---
$dir = sys_get_temp_dir() . '/upd_lock_test_' . bin2hex(random_bytes(4));
if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
    fwrite(STDERR, "Unable to create temp dir\n");
    exit(1);
}
$locksDir = $dir . '/locks';
if (!is_dir($locksDir) && !mkdir($locksDir, 0775, true) && !is_dir($locksDir)) {
    fwrite(STDERR, "Unable to create locks dir\n");
    exit(1);
}
$lockPath = $locksDir . '/update.lock';

// Write a lock from 2 hours ago with the current (alive) PID: past TTL, live PID.
$oldTs = gmdate('c', time() - 7200);
file_put_contents($lockPath, json_encode([
    'job_id' => 'upd_stale_ttl',
    'created_at' => $oldTs,
    'pid' => getmypid(),
], JSON_PRETTY_PRINT));

$ttlLock = new Updater\State\LockManager($dir, 3600);
check($ttlLock->isStale() === true, 'past-TTL lock with live PID is reported stale');
check($ttlLock->isLocked() === false, 'past-TTL lock does NOT block preflight (isLocked=false)');

// acquire() must transparently reclaim the stale lock and write a fresh one.
$ttlLock->acquire('upd_reclaimed');
$reclaimed = json_decode((string)file_get_contents($lockPath), true);
check(is_array($reclaimed) && ($reclaimed['job_id'] ?? '') === 'upd_reclaimed', 'acquire() reclaims a stale lock');
check($ttlLock->isLocked() === true, 'fresh lock is held');
$ttlLock->release();
check(!is_file($lockPath), 'release() removes the lock');

// --- 3) Behavior: fresh lock from a live PID still serializes ---
$live = new Updater\State\LockManager($dir, 3600);
$live->acquire('upd_live');
check($live->isLocked() === true && $live->isStale() === false, 'fresh lock (live PID) is held and not stale');
$caught = false;
try {
    (new Updater\State\LockManager($dir, 3600))->acquire('upd_second');
} catch (RuntimeException $e) {
    $caught = str_contains($e->getMessage(), 'already running');
}
check($caught, 'acquire() on a fresh live lock throws (still serializes)');
$live->release();

// --- 4) Behavior: dead-PID lock is stale even when fresh (requires posix) ---
if (function_exists('posix_kill')) {
    // Spawn a short-lived child and wait for it to exit, then reuse its PID.
    $pid = (int)shell_exec('sh -c \'sleep 0.05 & echo $!\' 2>/dev/null');
    if ($pid > 0) {
        usleep(300000); // let the child die
        $aliveAfterWait = @posix_kill($pid, 0);
        if (!$aliveAfterWait) {
            file_put_contents($lockPath, json_encode([
                'job_id' => 'upd_dead_pid',
                'created_at' => gmdate('c'),
                'pid' => $pid,
            ], JSON_PRETTY_PRINT));
            $dead = new Updater\State\LockManager($dir, 3600);
            check($dead->isStale() === true, 'fresh lock with a dead PID is reported stale');
            check($dead->isLocked() === false, 'dead-PID lock does NOT block preflight');
        } else {
            echo "  SKIP dead-PID check: child process still reported alive\n";
        }
    } else {
        echo "  SKIP dead-PID check: could not spawn child\n";
    }
} else {
    echo "  SKIP dead-PID check: posix extension not available (TTL-only fallback)\n";
}

// --- Cleanup ---
@unlink($lockPath);
@rmdir($locksDir);
@rmdir($dir);

echo "\nResult: {$passes} passed, {$failures} failed\n";
exit($failures === 0 ? 0 : 1);
