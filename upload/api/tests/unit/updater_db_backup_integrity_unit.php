<?php
declare(strict_types=1);

/**
 * Unit test: DatabaseBackupManager dump-integrity gate.
 *
 * Guards the fix that restore() must NEVER replay a corrupt/truncated dump
 * over the live database. Before dropping any table/view, restore() verifies
 * the dump against the SHA-256 hashes recorded in manifest.json at backup
 * time (with a structural fallback for legacy backups created before hashing
 * existed). A bit-rotten or truncated dump must abort the restore with the
 * database left untouched.
 *
 * Covers: (1) source contract - backup() records *_sha256 hashes and
 * restore() calls verifyDumpIntegrity() BEFORE the drop-all loop;
 * (2) behavior - checksum mismatch, legacy fallback (headers/markers),
 * and happy path via the private verifyDumpIntegrity() method.
 *
 * Run: php upload/api/tests/unit/updater_db_backup_integrity_unit.php
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

// --- 1) Source contract ---
$sourceFile = $upload . '/updater/src/Backup/DatabaseBackupManager.php';
$src = (string)file_get_contents($sourceFile);

check(str_contains($src, "'schema_sha256' => hash_file('sha256', \$schemaFile)"), 'backup() records schema.sql sha256 in manifest');
check(str_contains($src, "'data_sha256' => hash_file('sha256', \$dataFile)"), 'backup() records data.sql sha256 in manifest');
check(str_contains($src, 'verifyDumpIntegrity($dbDir, is_array($manifest)'), 'restore() calls verifyDumpIntegrity()');
check(str_contains($src, '$maxBatchBytes = 512 * 1024'), 'dumpTableData flushes INSERT batches by bytes (max_allowed_packet safety)');
check(str_contains($src, 'stripDefiner('), 'backup() strips DEFINER from views/triggers');
check(str_contains($src, "'file_sha256' => hash_file('sha256', \$target)"), 'sqlite backup records file sha256');
check(str_contains($src, 'Post-restore verification failed'), 'restore() verifies replayed table/view count after replay');

// verifyDumpIntegrity must run BEFORE the drop-all loop (DROP VIEW / DROP TABLE).
$integrityPos = strpos($src, '$integrityError = $this->verifyDumpIntegrity(');
$dropViewPos = strpos($src, "DROP VIEW IF EXISTS `' . \$existingView");
$dropTablePos = strpos($src, "DROP TABLE IF EXISTS `' . \$existingTable");
check(
    $integrityPos !== false && $integrityPos < $dropViewPos && $integrityPos < $dropTablePos,
    'verifyDumpIntegrity() runs BEFORE any DROP statement in restore()'
);
check(str_contains($src, "'integrity' => 'verified'"), 'restore() reports integrity=verified on success');
check(str_contains($src, 'Restore aborted before any change'), 'restore() abort message says the DB was left untouched');

// --- 2) Behavior: private verifyDumpIntegrity() via reflection ---
$tmp = sys_get_temp_dir() . '/upd_integrity_test_' . bin2hex(random_bytes(4));
if (!is_dir($tmp) && !mkdir($tmp, 0775, true) && !is_dir($tmp)) {
    fwrite(STDERR, "Unable to create temp dir\n");
    exit(1);
}
$dbDir = $tmp . '/db';
if (!mkdir($dbDir, 0775, true) && !is_dir($dbDir)) {
    fwrite(STDERR, "Unable to create db dir\n");
    exit(1);
}

$manager = new Updater\Backup\DatabaseBackupManager($upload);
$method = new ReflectionMethod($manager, 'verifyDumpIntegrity');

$schemaFile = $dbDir . '/schema.sql';
$dataFile = $dbDir . '/data.sql';
$triggersFile = $dbDir . '/triggers.sql';

// Helper: write a small valid dump (header + 2 statement markers per table).
$writeDump = static function (string $file, string $header, int $markers) use ($tmp): void {
    $fh = fopen($file, 'w');
    if ($fh === false) {
        return;
    }
    fwrite($fh, "-- $header\n");
    for ($i = 0; $i < $markers; $i++) {
        fwrite($fh, "-- @@TROPA_SQL@@\nCREATE TABLE `t{$i}` (id INT);\n");
    }
    fclose($fh);
};
$writeDump($schemaFile, 'TropaTT DB schema backup (job)', 2);
$writeDump($dataFile, 'TropaTT DB data backup (job)', 1);

// --- 2a) Happy path: manifest hashes match the files ---
$schemaSha = (string)hash_file('sha256', $schemaFile);
$dataSha = (string)hash_file('sha256', $dataFile);
$manifestOk = [
    'driver' => 'mysql',
    'tables' => 2,
    'schema_sha256' => $schemaSha,
    'data_sha256' => $dataSha,
];
check($method->invoke($manager, $dbDir, $manifestOk) === null, 'happy path: matching hashes pass');

// --- 2b) Checksum mismatch on data.sql ---
$manifestCorrupt = $manifestOk;
$manifestCorrupt['data_sha256'] = str_repeat('0', 64);
$err = $method->invoke($manager, $dbDir, $manifestCorrupt);
check(is_string($err) && str_contains($err, 'data.sql checksum mismatch'), 'corrupt data.sql rejected by checksum');

// --- 2c) Checksum mismatch on schema.sql ---
$manifestCorruptSchema = $manifestOk;
$manifestCorruptSchema['schema_sha256'] = str_repeat('0', 64);
$err = $method->invoke($manager, $dbDir, $manifestCorruptSchema);
check(is_string($err) && str_contains($err, 'schema.sql checksum mismatch'), 'corrupt schema.sql rejected by checksum');

// --- 2d) Legacy backup (no hashes): valid headers + enough markers pass ---
$manifestLegacy = ['driver' => 'mysql', 'tables' => 2];
check($method->invoke($manager, $dbDir, $manifestLegacy) === null, 'legacy backup (no hashes) passes structural check');

// --- 2e) Legacy backup: empty data.sql rejected ---
file_put_contents($dataFile, '');
$err = $method->invoke($manager, $dbDir, $manifestLegacy);
check(is_string($err) && str_contains($err, 'data.sql is missing or empty'), 'legacy empty data.sql rejected');
$writeDump($dataFile, 'TropaTT DB data backup (job)', 1);

// --- 2f) Legacy backup: wrong header rejected ---
$backupFile = $dbDir . '/data_bad.sql';
$writeDump($backupFile, 'unrelated dump', 1);
rename($backupFile, $dataFile);
$err = $method->invoke($manager, $dbDir, $manifestLegacy);
check(is_string($err) && str_contains($err, 'unexpected header'), 'legacy wrong header rejected');
$writeDump($dataFile, 'TropaTT DB data backup (job)', 1);

// --- 2g) Legacy backup: truncated schema (fewer markers than tables) rejected ---
$writeDump($schemaFile, 'TropaTT DB schema backup (job)', 1); // 1 marker < 2 tables
$err = $method->invoke($manager, $dbDir, $manifestLegacy);
check(is_string($err) && str_contains($err, 'schema.sql is truncated'), 'legacy truncated schema rejected');
$writeDump($schemaFile, 'TropaTT DB schema backup (job)', 2);

// --- 2h) Checksum path: missing dump file rejected (hash mismatch), not fatal ---
@unlink($dataFile);
$err = $method->invoke($manager, $dbDir, $manifestOk);
check(is_string($err) && $err !== '', 'missing data.sql rejected under checksum path');
$writeDump($dataFile, 'TropaTT DB data backup (job)', 1);

// --- 2i) Checksum path: triggers.sql recorded but missing is rejected ---
$manifestWithTrig = $manifestOk;
$manifestWithTrig['triggers_sha256'] = str_repeat('a', 64);
@unlink($triggersFile);
$err = $method->invoke($manager, $dbDir, $manifestWithTrig);
check(is_string($err) && str_contains($err, 'triggers.sql is missing'), 'missing triggers.sql rejected when hash recorded');

// --- 2j) Legacy backup: data.sql with recorded rows but no INSERT markers ---
$writeDump($dataFile, 'TropaTT DB data backup (job)', 0); // header only, no statements
$manifestRows = ['driver' => 'mysql', 'tables' => 2, 'rows' => 5];
$err = $method->invoke($manager, $dbDir, $manifestRows);
check(is_string($err) && str_contains($err, 'data.sql is truncated'), 'legacy data.sql without INSERT markers rejected when rows>0');
$writeDump($dataFile, 'TropaTT DB data backup (job)', 1);

// --- Cleanup ---
foreach (glob($dbDir . '/*') ?: [] as $f) {
    @unlink($f);
}
@rmdir($dbDir);
@rmdir($tmp);

echo "\nResult: {$passes} passed, {$failures} failed\n";
exit($failures === 0 ? 0 : 1);
