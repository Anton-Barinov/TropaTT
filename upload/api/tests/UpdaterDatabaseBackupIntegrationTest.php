<?php
declare(strict_types=1);

/**
 * Round-trip integration test for Updater\DatabaseBackupManager (MySQL).
 *
 * Requires local MySQL (root, no password) and creates a throwaway database
 * `upd_test_roundtrip`. Run:
 *
 *   php upload/api/tests/UpdaterDatabaseBackupIntegrationTest.php
 *
 * Exit code 0 = all assertions passed.
 */

// Defaults target local dev MySQL (root, no password). On CI/servers where
// root uses unix_socket auth, set TROPA_TEST_DB_USER/PASSWORD/... to a
// dedicated test account that can create/drop tables in the test database.
putenv('DB_CONNECTION=mysql');
putenv('DB_HOST=' . (getenv('TROPA_TEST_DB_HOST') ?: '127.0.0.1'));
putenv('DB_DATABASE=' . (getenv('TROPA_TEST_DB_NAME') ?: 'upd_test_roundtrip'));
putenv('DB_USERNAME=' . (getenv('TROPA_TEST_DB_USER') ?: 'root'));
putenv('DB_PASSWORD=' . (getenv('TROPA_TEST_DB_PASSWORD') ?: ''));

$upload = dirname(__DIR__, 2); // upload/
$backupRoot = sys_get_temp_dir() . '/upd_db_backup_test_' . bin2hex(random_bytes(4));
$jobId = 'job_roundtrip_test';

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
function ok(bool $cond, string $label): void {
    global $passes, $failures;
    if ($cond) {
        $passes++;
        echo "  OK  $label\n";
    } else {
        $failures++;
        echo "  FAIL $label\n";
    }
}

$pdo = new PDO(
    'mysql:host=' . getenv('DB_HOST') . ';dbname=' . getenv('DB_DATABASE') . ';charset=utf8mb4',
    getenv('DB_USERNAME'),
    getenv('DB_PASSWORD'),
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);

// --- Seed fixture schema + data ---
// Clean all fixtures from any previous run. FK checks off so drop order
// does not matter.
$pdo->exec('SET FOREIGN_KEY_CHECKS=0');
$pdo->exec('DROP TRIGGER IF EXISTS upd_test_trg');
$pdo->exec('DROP VIEW IF EXISTS upd_test_items_view');
$pdo->exec('DROP TABLE IF EXISTS upd_test_tags');
$pdo->exec('DROP TABLE IF EXISTS upd_test_items');
$pdo->exec('DROP TABLE IF EXISTS upd_test_brand_new');
$pdo->exec('SET FOREIGN_KEY_CHECKS=1');

$pdo->exec('CREATE TABLE upd_test_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(190) NOT NULL,
    note TEXT NULL,
    price DECIMAL(10,2) NOT NULL DEFAULT 0,
    active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
$pdo->exec('CREATE TABLE upd_test_tags (
    id INT AUTO_INCREMENT PRIMARY KEY,
    item_id INT NOT NULL,
    tag VARCHAR(50) NOT NULL,
    CONSTRAINT fk_item FOREIGN KEY (item_id) REFERENCES upd_test_items(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
$pdo->exec('CREATE VIEW upd_test_items_view AS SELECT id, name FROM upd_test_items');
// Compound trigger (BEGIN...END with internal semicolons + newlines) - must
// round-trip through the marker-based statement splitter and NOT fire during
// data restore.
$pdo->exec("CREATE TRIGGER upd_test_trg BEFORE UPDATE ON upd_test_items
    FOR EACH ROW
    BEGIN
        SET NEW.created_at = NOW();
        IF NEW.name = 'touched' THEN
            SET NEW.name = CONCAT(NEW.name, '!');
        END IF;
    END");

$stmt = $pdo->prepare('INSERT INTO upd_test_items (name, note, price, active, created_at) VALUES (?, ?, ?, ?, ?)');
$items = [
    ["first", "plain note", 10.5, 1, '2026-01-02 03:04:05'],
    ["second with 'quotes' and \"double\"", "Unicode: привет мир 🚀; emoji ✓", 0, 0, '2026-07-31 23:59:59'],
    ["third <script>alert(1)</script>", null, 999.99, 1, '2026-01-01 00:00:00'],
];
foreach ($items as $i) {
    $stmt->execute($i);
}
$tagStmt = $pdo->prepare('INSERT INTO upd_test_tags (item_id, tag) VALUES (?, ?)');
$tagStmt->execute([1, 'alpha']);
$tagStmt->execute([1, 'beta']);
$tagStmt->execute([2, 'gamma']);

// --- Backup ---
$manager = new Updater\Backup\DatabaseBackupManager($upload);
$report = $manager->backup($backupRoot, $jobId);
ok(($report['ok'] ?? false) === true, 'backup ok');
ok(($report['driver'] ?? '') === 'mysql', 'driver mysql');
ok(($report['tables'] ?? 0) === 2, '2 tables dumped (got ' . ($report['tables'] ?? 'null') . ')');
ok(($report['views'] ?? 0) === 1, '1 view dumped (got ' . ($report['views'] ?? 'null') . ')');
ok(($report['triggers'] ?? 0) === 1, '1 trigger dumped (got ' . ($report['triggers'] ?? 'null') . ')');
ok(($report['rows'] ?? 0) === 6, '6 rows dumped (got ' . ($report['rows'] ?? 'null') . ')');

$manifestFile = $backupRoot . '/db/manifest.json';
ok(is_file($manifestFile), 'manifest.json written');
$schemaFile = $backupRoot . '/db/schema.sql';
$dataFile = $backupRoot . '/db/data.sql';
ok(is_file($schemaFile), 'schema.sql written');
ok(is_file($dataFile), 'data.sql written');

// --- Integrity metadata: manifest must record SHA-256 of every dump file ---
$manifest = json_decode((string)file_get_contents($manifestFile), true);
ok(is_array($manifest), 'manifest.json is valid JSON');
ok(($manifest['schema_sha256'] ?? '') !== '', 'manifest records schema.sql sha256');
ok(($manifest['data_sha256'] ?? '') !== '', 'manifest records data.sql sha256');
ok(($manifest['triggers_sha256'] ?? '') !== '', 'manifest records triggers.sql sha256');
ok((string)($manifest['schema_sha256'] ?? '') === (string)hash_file('sha256', $schemaFile), 'schema sha256 matches file');
ok((string)($manifest['data_sha256'] ?? '') === (string)hash_file('sha256', $dataFile), 'data sha256 matches file');
ok((string)($manifest['triggers_sha256'] ?? '') === (string)hash_file('sha256', $backupRoot . '/db/triggers.sql'), 'triggers sha256 matches file');

// --- Corrupt backup must be rejected BEFORE any table is dropped ---
// Copy the backup dir, corrupt data.sql, and restore from the copy: the
// integrity gate must abort without touching the live database (the seeded
// fixture tables must still exist with all rows intact afterwards).
$corruptRoot = $backupRoot . '_corrupt';
$copyDir = static function (string $from, string $to) use (&$copyDir): void {
    if (!is_dir($to) && !mkdir($to, 0775, true)) {
        return;
    }
    foreach (scandir($from) ?: [] as $entry) {
        if ($entry === '.' || $entry === '..') {
            continue;
        }
        $src = $from . '/' . $entry;
        $dst = $to . '/' . $entry;
        if (is_dir($src)) {
            $copyDir($src, $dst);
        } else {
            copy($src, $dst);
        }
    }
};
$copyDir($backupRoot, $corruptRoot);
file_put_contents($corruptRoot . '/db/data.sql', "-- TropaTT DB data backup (job)\n-- @@TROPA_SQL@@\nINSERT INTO `upd_test_items` VALUES (999, 'corrupt', NULL, 0, 1, NOW());\n");
$corruptRestore = $manager->restore($corruptRoot);
ok(($corruptRestore['ok'] ?? false) === false, 'corrupt backup restore rejected');
ok(is_string($corruptRestore['error'] ?? null) && str_contains($corruptRestore['error'], 'integrity'), 'rejection mentions integrity check');
$tablesAfterReject = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
ok(in_array('upd_test_items', $tablesAfterReject, true), 'corrupt restore left upd_test_items intact');
ok(in_array('upd_test_tags', $tablesAfterReject, true), 'corrupt restore left upd_test_tags intact');
$itemsAfterReject = (int)$pdo->query('SELECT COUNT(*) FROM upd_test_items')->fetchColumn();
ok($itemsAfterReject === 3, 'corrupt restore did not touch data (3 items remain)');

// --- Legacy backup (no hashes) with corrupt schema must also be rejected ---
$legacyRoot = $backupRoot . '_legacy';
$copyDir($backupRoot, $legacyRoot);
$legacyManifest = json_decode((string)file_get_contents($legacyRoot . '/db/manifest.json'), true);
unset($legacyManifest['schema_sha256'], $legacyManifest['data_sha256'], $legacyManifest['triggers_sha256']);
file_put_contents($legacyRoot . '/db/manifest.json', json_encode($legacyManifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
file_put_contents($legacyRoot . '/db/schema.sql', ''); // truncated to zero bytes
$legacyRestore = $manager->restore($legacyRoot);
ok(($legacyRestore['ok'] ?? false) === false, 'legacy corrupt backup restore rejected');
$tablesAfterLegacyReject = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
ok(in_array('upd_test_items', $tablesAfterLegacyReject, true), 'legacy corrupt restore left upd_test_items intact');
$itemsAfterLegacyReject = (int)$pdo->query('SELECT COUNT(*) FROM upd_test_items')->fetchColumn();
ok($itemsAfterLegacyReject === 3, 'legacy corrupt restore did not touch data (3 items remain)');
$schemaContent = (string)file_get_contents($schemaFile);
ok(str_contains($schemaContent, 'CREATE TABLE `upd_test_items`'), 'schema has CREATE TABLE upd_test_items');
ok(str_contains($schemaContent, 'VIEW `upd_test_items_view`'), 'schema has CREATE VIEW');
ok(str_contains($schemaContent, 'FOREIGN KEY'), 'schema preserves FK (utf8mb4 fixture has FK)');
ok(is_file($backupRoot . '/db/triggers.sql'), 'triggers.sql written');
$triggersContent = (string)file_get_contents($backupRoot . '/db/triggers.sql');
ok(str_contains($triggersContent, 'upd_test_trg'), 'triggers.sql has CREATE TRIGGER for upd_test_trg');
ok(str_contains($triggersContent, 'SET NEW.created_at = NOW()'), 'compound trigger body dumped intact');

// --- Mutate the DB (simulate a migration + data change) ---
$pdo->exec('ALTER TABLE upd_test_items DROP COLUMN note');
$pdo->exec("UPDATE upd_test_items SET name = 'changed' WHERE id = 1");
$pdo->exec('DELETE FROM upd_test_tags WHERE tag = "gamma"');
$pdo->exec('DROP TABLE upd_test_tags');
$pdo->exec('CREATE TABLE upd_test_brand_new (id INT PRIMARY KEY) ENGINE=InnoDB');

// --- Restore ---
$restore = $manager->restore($backupRoot);
ok(($restore['ok'] ?? false) === true, 'restore ok');
ok(($restore['driver'] ?? '') === 'mysql', 'restore driver mysql');
ok(($restore['integrity'] ?? '') === 'verified', 'restore reports integrity=verified');
ok(($restore['tables'] ?? 0) === 2, 'restore reports 2 tables');

// --- Verify restored schema + data ---
$tables = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
ok(in_array('upd_test_items', $tables, true), 'upd_test_items exists again');
ok(in_array('upd_test_tags', $tables, true), 'upd_test_tags exists again');
ok(!in_array('upd_test_brand_new', $tables, true), 'brand-new table from migration removed');
$views = $pdo->query("SHOW FULL TABLES WHERE Table_type = 'VIEW'")->fetchAll(PDO::FETCH_COLUMN);
ok(in_array('upd_test_items_view', $views, true), 'view recreated after restore');
$triggers = $pdo->query('SHOW TRIGGERS')->fetchAll(PDO::FETCH_COLUMN);
ok(in_array('upd_test_trg', $triggers, true), 'trigger recreated after restore');

// Trigger must NOT have fired during restore: created_at values are intact.
$created = $pdo->query('SELECT created_at FROM upd_test_items WHERE id = 2')->fetchColumn();
ok($created === '2026-07-31 23:59:59', 'trigger did not fire during restore (created_at intact)');

// Data checks BEFORE firing the restored trigger (it rewrites row 1's name).
$cols = $pdo->query('SHOW COLUMNS FROM upd_test_items')->fetchAll(PDO::FETCH_COLUMN);
ok(in_array('note', $cols, true), 'note column restored');
$name1 = $pdo->query('SELECT name FROM upd_test_items WHERE id = 1')->fetchColumn();
ok($name1 === 'first', 'row 1 name restored ("first")');

// Compound trigger body survived: firing it now must apply BOTH statements.
$pdo->exec("UPDATE upd_test_items SET name = 'touched' WHERE id = 1");
$name1 = $pdo->query('SELECT name FROM upd_test_items WHERE id = 1')->fetchColumn();
ok($name1 === 'touched!', 'compound trigger body restored (fires correctly)');

$note2 = $pdo->query('SELECT note FROM upd_test_items WHERE id = 2')->fetchColumn();
ok($note2 === "Unicode: привет мир 🚀; emoji ✓", 'unicode note restored');
$nullNote = $pdo->query('SELECT note FROM upd_test_items WHERE id = 3')->fetchColumn();
ok($nullNote === false || $nullNote === null, 'null note restored');
$price = $pdo->query('SELECT price FROM upd_test_items WHERE id = 3')->fetchColumn();
ok(abs((float)$price - 999.99) < 0.001, 'decimal price restored');
$tagCount = $pdo->query('SELECT COUNT(*) FROM upd_test_tags')->fetchColumn();
ok((int)$tagCount === 3, 'FK child rows restored (3 tags)');

// --- Cleanup ---
$pdo->exec('SET FOREIGN_KEY_CHECKS=0');
$pdo->exec('DROP TRIGGER IF EXISTS upd_test_trg');
$pdo->exec('DROP VIEW IF EXISTS upd_test_items_view');
$pdo->exec('DROP TABLE IF EXISTS upd_test_tags');
$pdo->exec('DROP TABLE IF EXISTS upd_test_items');
$pdo->exec('DROP TABLE IF EXISTS upd_test_brand_new');
$pdo->exec('SET FOREIGN_KEY_CHECKS=1');
foreach ([$backupRoot, $corruptRoot, $legacyRoot] as $root) {
    $removeDir = static function (string $dir) use (&$removeDir): void {
        if (!is_dir($dir)) {
            return;
        }
        foreach (scandir($dir) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            $path = $dir . '/' . $entry;
            if (is_dir($path)) {
                $removeDir($path);
            } else {
                @unlink($path);
            }
        }
        @rmdir($dir);
    };
    $removeDir($root);
}

echo "\nResult: {$passes} passed, {$failures} failed\n";
exit($failures === 0 ? 0 : 1);
