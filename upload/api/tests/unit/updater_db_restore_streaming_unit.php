<?php
declare(strict_types=1);

/**
 * Regression test: DatabaseBackupManager::execSqlFile() must STREAM dump
 * files instead of slurping them into memory.
 *
 * Real incident (2026-08-01, demo.tropatt.com): a live E2E apply/rollback
 * cycle with a real 1,356,449-row database produced a 338MB data.sql.
 * restore() ran execSqlFile() via file_get_contents() + preg_split(), which
 * blew the 512M memory_limit with an UNCATCHABLE fatal error mid-restore -
 * AFTER every table had already been dropped - leaving the CRM database
 * empty and maintenance mode stuck on (rollback returned 500 with an empty
 * body and never disabled maintenance).
 *
 * This test guards the fix: (1) source contract - execSqlFile must stream
 * with fgets() and never load the whole file; (2) behavior - a dump file far
 * larger than the imposed memory_limit must restore correctly.
 *
 * Run: php upload/api/tests/unit/updater_db_restore_streaming_unit.php
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

// --- 1) Source contract: streaming, never whole-file slurp ---
$sourceFile = $upload . '/updater/src/Backup/DatabaseBackupManager.php';
$src = (string)file_get_contents($sourceFile);
check(str_contains($src, 'fgets($handle'), 'execSqlFile streams with fgets()');
check(!str_contains($src, '$content = (string)file_get_contents($file);'), 'execSqlFile no longer loads the whole file');
check(!str_contains($src, "preg_split('/^-- @@TROPA_SQL@@"), 'execSqlFile no longer preg_splits the whole file');

// --- 2) Behavior: restore a dump much larger than the memory_limit ---
$dir = sys_get_temp_dir() . '/upd_stream_test_' . bin2hex(random_bytes(4));
if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
    fwrite(STDERR, "Unable to create temp dir\n");
    exit(1);
}
$file = $dir . '/data.sql';
$dbPath = $dir . '/test.sqlite';
$pdo = new PDO('sqlite:' . $dbPath);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->exec('CREATE TABLE t (id INTEGER PRIMARY KEY, payload TEXT)');

// Generate the dump file streaming (write in batches, keep PHP memory low).
$fh = fopen($file, 'w');
if ($fh === false) {
    fwrite(STDERR, "Unable to open dump file\n");
    exit(1);
}
fwrite($fh, "-- TropaTT DB data backup (streaming test)\n");
$rows = 0;
$batch = [];
$batchSize = 100;
$payload = str_repeat('x', 200); // 200 chars per row
for ($i = 0; $i < 200000; $i++) { // 200k rows ~= 40MB dump
    $batch[] = '(' . $i . ', "' . $payload . '")';
    if (count($batch) >= $batchSize) {
        fwrite($fh, "-- @@TROPA_SQL@@\nINSERT INTO t VALUES " . implode(', ', $batch) . ";\n");
        $batch = [];
    }
    $rows++;
}
if ($batch !== []) {
    fwrite($fh, "-- @@TROPA_SQL@@\nINSERT INTO t VALUES " . implode(', ', $batch) . ";\n");
}
fclose($fh);

$size = filesize($file);
check($size > 10 * 1024 * 1024, sprintf('dump file is large enough to OOM a slurper (%d bytes)', $size));

// Impose a memory limit far below the file size. A file_get_contents()+preg_split()
// implementation would fatal here with an uncatchable memory error.
$before = (string)ini_get('memory_limit');
if (!@ini_set('memory_limit', '16M')) {
    echo "  SKIP behavior: cannot lower memory_limit\n";
} else {
    gc_collect_cycles();

    $manager = new Updater\Backup\DatabaseBackupManager($upload);
    $method = new ReflectionMethod($manager, 'execSqlFile');
    $method->invoke($manager, $pdo, $file);

    $count = (int)$pdo->query('SELECT COUNT(*) FROM t')->fetchColumn();
    check($count === $rows, "all $rows rows restored via streaming under 16M limit");
    $payload1 = (string)$pdo->query('SELECT payload FROM t WHERE id = 1')->fetchColumn();
    check(strlen($payload1) === 200, 'payload round-trips intact');
    $payloadLast = (string)$pdo->query('SELECT payload FROM t WHERE id = ' . ($rows - 1))->fetchColumn();
    check(strlen($payloadLast) === 200, 'last row round-trips intact');
    @ini_set('memory_limit', $before);
}

// --- Cleanup ---
@unlink($file);
@unlink($dbPath);
@rmdir($dir);

echo "\nResult: {$passes} passed, {$failures} failed\n";
exit($failures === 0 ? 0 : 1);
