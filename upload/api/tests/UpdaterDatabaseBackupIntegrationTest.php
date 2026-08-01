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

putenv('DB_CONNECTION=mysql');
putenv('DB_HOST=127.0.0.1');
putenv('DB_DATABASE=upd_test_roundtrip');
putenv('DB_USERNAME=root');
putenv('DB_PASSWORD=');

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

$pdo = new PDO('mysql:host=127.0.0.1;dbname=upd_test_roundtrip;charset=utf8mb4', 'root', '', [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
]);

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
$schemaContent = (string)file_get_contents($schemaFile);
ok(str_contains($schemaContent, 'CREATE TABLE `upd_test_items`'), 'schema has CREATE TABLE upd_test_items');
ok(str_contains($schemaContent, 'VIEW `upd_test_items_view`'), 'schema has CREATE VIEW');
ok(str_contains($schemaContent, 'FOREIGN KEY'), 'schema preserves FK (utf8mb4 fixture has FK)');
ok(is_file($backupRoot . '/db/triggers.sql'), 'triggers.sql written');
$triggersContent = (string)file_get_contents($backupRoot . '/db/triggers.sql');
ok(str_contains($triggersContent, 'TRIGGER `upd_test_trg`'), 'triggers.sql has CREATE TRIGGER');
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
foreach (glob($backupRoot . '/db/*') ?: [] as $f) {
    @unlink($f);
}
@rmdir($backupRoot . '/db');
@rmdir($backupRoot);

echo "\nResult: {$passes} passed, {$failures} failed\n";
exit($failures === 0 ? 0 : 1);
