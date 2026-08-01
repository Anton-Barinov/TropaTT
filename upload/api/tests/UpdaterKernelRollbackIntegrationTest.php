<?php
declare(strict_types=1);

/**
 * Kernel-level rollback integration test for Updater\UpdaterKernel (MySQL).
 *
 * Regression guard for commit c9351fe. The live E2E on demo.tropatt.com
 * caught a real bug: rollback() restored files BEFORE restoring the DB.
 * For a self-updating update package the file backup contains the
 * PRE-update updater files - including an older DatabaseBackupManager stub
 * without a restore() method. Restoring files first then made PHP autoload
 * that overwritten stub when the DB restore ran afterwards, fataling with
 * "Call to undefined method DatabaseBackupManager::restore()".
 *
 * This test drives the REAL UpdaterKernel::rollback() end-to-end in a child
 * process:
 *   - temp app root with the current updater source + api config shims
 *   - a hand-crafted file backup whose DatabaseBackupManager.php is the OLD
 *     stub (so the rollback restores the stub over the real class)
 *   - a REAL database snapshot (via DatabaseBackupManager) + DB mutation
 *     simulating a migration
 *   - a valid updater session token
 * With the fix, the DB restore runs first (autoloading the real class still
 * on disk) and succeeds; the file rollback afterwards puts the stub back.
 * Without the fix, the DB restore would autoload the stub and fail.
 *
 * Requires local MySQL (root, no password) and creates a throwaway database
 * `upd_kernel_rt_test`. Run:
 *
 *   php upload/api/tests/UpdaterKernelRollbackIntegrationTest.php
 *
 * Exit code 0 = all assertions passed. Skips gracefully (exit 0) when local
 * MySQL is unavailable, so it is safe to run anywhere.
 */

putenv('DB_CONNECTION=mysql');
putenv('DB_HOST=127.0.0.1');
putenv('DB_DATABASE=upd_kernel_rt_test');
putenv('DB_USERNAME=root');
putenv('DB_PASSWORD=');

$upload = dirname(__DIR__, 2); // upload/
$root = sys_get_temp_dir() . '/upd_kernel_rt_' . bin2hex(random_bytes(4));

$passes = 0;
$failures = 0;
function ok(bool $cond, string $label): void
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

function copyTree(string $src, string $dst): void
{
    if (!is_dir($dst)) {
        mkdir($dst, 0775, true);
    }
    foreach (scandir($src) ?: [] as $entry) {
        if ($entry === '.' || $entry === '..') {
            continue;
        }
        $s = $src . '/' . $entry;
        $d = $dst . '/' . $entry;
        if (is_dir($s)) {
            copyTree($s, $d);
        } elseif (is_file($s)) {
            copy($s, $d);
        }
    }
}

function removeTree(string $path): void
{
    if (!is_dir($path)) {
        return;
    }
    foreach (scandir($path) ?: [] as $entry) {
        if ($entry === '.' || $entry === '..') {
            continue;
        }
        $p = $path . '/' . $entry;
        is_dir($p) ? removeTree($p) : @unlink($p);
    }
    @rmdir($path);
}

// ---------------------------------------------------------------------------
// 1. MySQL fixture (throwaway database)
// ---------------------------------------------------------------------------
try {
    $server = new PDO('mysql:host=127.0.0.1;charset=utf8mb4', 'root', '', [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);
    $server->exec('CREATE DATABASE IF NOT EXISTS upd_kernel_rt_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
} catch (Throwable $e) {
    echo "SKIP: local MySQL unavailable ({$e->getMessage()})\n";
    exit(0);
}

$pdo = new PDO('mysql:host=127.0.0.1;dbname=upd_kernel_rt_test;charset=utf8mb4', 'root', '', [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
]);
$pdo->exec('SET FOREIGN_KEY_CHECKS=0');
$pdo->exec('DROP TABLE IF EXISTS krt_tags');
$pdo->exec('DROP TABLE IF EXISTS krt_items');
$pdo->exec('DROP TABLE IF EXISTS krt_brand_new');
$pdo->exec('SET FOREIGN_KEY_CHECKS=1');

$pdo->exec('CREATE TABLE krt_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    note TEXT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
$pdo->exec('CREATE TABLE krt_tags (
    id INT AUTO_INCREMENT PRIMARY KEY,
    item_id INT NOT NULL,
    tag VARCHAR(50) NOT NULL,
    CONSTRAINT fk_krt FOREIGN KEY (item_id) REFERENCES krt_items(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
$pdo->exec("INSERT INTO krt_items (name, note) VALUES ('alpha', 'note-a'), ('beta', 'note-b')");
$pdo->exec("INSERT INTO krt_tags (item_id, tag) VALUES (1, 'x'), (1, 'y')");

// ---------------------------------------------------------------------------
// 2. Temp app root (updater source + api config shims)
// ---------------------------------------------------------------------------
$storageDir = $root . '/storage_api/updates';
mkdir($root . '/api/config', 0775, true);
mkdir($root . '/api/system', 0775, true);
mkdir($root . '/web', 0775, true);
mkdir($root . '/storage_api', 0775, true);

// Copy the current updater source so the kernel autoloads the REAL
// DatabaseBackupManager (with restore()) from the temp root on disk.
copyTree($upload . '/updater', $root . '/updater');

// Api config shims: the updater's Db\Connection::open() requires the Api
// autoloader + Config + ConnectionManager + database.php. Copy the real
// system tree so all Api classes resolve identically to production.
copy($upload . '/api/config/database.php', $root . '/api/config/database.php');
copyTree($upload . '/api/system', $root . '/api/system');

file_put_contents($root . '/api/config/update.php', <<<'PHP'
<?php
declare(strict_types=1);
$storage = __DIR__ . '/../../storage_api/updates';
return [
    'enabled' => true,
    'product' => 'tropatt-core',
    'channel' => 'stable',
    'update_center_url' => 'https://update.tropatt.com',
    'local_updater_url' => '',
    'storage_dir' => $storage,
    'public_key_path' => __DIR__ . '/../../updater/keys/update_public.pem',
    'timeouts' => ['check' => 10, 'download' => 120, 'apply_step' => 300],
    'limits' => ['max_package_bytes' => 100 * 1024 * 1024, 'min_free_space_multiplier' => 3],
    'rate_limits' => ['enabled' => true, 'max_attempts' => 20, 'window_seconds' => 300, 'lock_seconds' => 900],
    'core_paths' => ['api/**', 'web/**', 'index.php', 'updater/**'],
    'protected_paths' => ['modules/**', 'storage/**', 'storage_api/**', 'updater/keys/**', '.env', 'api/.env'],
    'db_backup' => ['enabled' => true],
];
PHP
);
foreach (['api/index.php', 'web/index.php', 'index.php'] as $phpEntry) {
    file_put_contents($root . '/' . $phpEntry, "<?php // health-check shim\n");
}

// ---------------------------------------------------------------------------
// 3. Real DB snapshot + simulated migration mutation
// ---------------------------------------------------------------------------
$jobId = 'krt_job_1';
$backupId = 'backup_krt_job_1_20260801_000000';

$storage = [
    'sessions' => $storageDir . '/sessions',
    'jobs' => $storageDir . '/jobs',
    'packages' => $storageDir . '/packages',
    'staging' => $storageDir . '/staging',
    'backups' => $storageDir . '/backups',
    'locks' => $storageDir . '/locks',
    'logs' => $storageDir . '/logs',
    'ratelimit' => $storageDir . '/ratelimit',
];
foreach ($storage as $dir) {
    if (!is_dir($dir)) {
        mkdir($dir, 0775, true);
    }
}

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

$backupDir = $storage['backups'] . '/' . $backupId;
$dbManager = new Updater\Backup\DatabaseBackupManager($root);
$dbReport = $dbManager->backup($backupDir, $jobId);
ok(($dbReport['ok'] ?? false) === true, 'real DB snapshot created (tables=' . ($dbReport['tables'] ?? '?') . ')');

// Simulate a migration + data change after the snapshot.
$pdo->exec('SET FOREIGN_KEY_CHECKS=0');
$pdo->exec('DROP TABLE krt_tags');
$pdo->exec("UPDATE krt_items SET name = 'changed' WHERE id = 1");
$pdo->exec('CREATE TABLE krt_brand_new (id INT PRIMARY KEY) ENGINE=InnoDB');
$pdo->exec('SET FOREIGN_KEY_CHECKS=1');

// ---------------------------------------------------------------------------
// 4. Hand-crafted FILE backup containing the OLD DatabaseBackupManager stub
// ---------------------------------------------------------------------------
$stub = <<<'PHP'
<?php
declare(strict_types=1);

namespace Updater\Backup;

final class DatabaseBackupManager extends BackupManager
{
}
PHP;

mkdir($backupDir . '/files/updater/src/Backup', 0775, true);
file_put_contents($backupDir . '/files/updater/src/Backup/DatabaseBackupManager.php', $stub);

$fileManifest = [
    'backup_id' => $backupId,
    'job_id' => $jobId,
    'created_at' => gmdate('c'),
    'items' => [[
        'path' => 'updater/src/Backup/DatabaseBackupManager.php',
        'existed' => true,
        'sha256' => hash('sha256', $stub),
        'size_bytes' => strlen($stub),
    ]],
];
file_put_contents($backupDir . '/manifest.json', json_encode($fileManifest, JSON_PRETTY_PRINT));

// ---------------------------------------------------------------------------
// 5. Job state + session token
// ---------------------------------------------------------------------------
$jobDir = $storage['jobs'] . '/' . $jobId;
mkdir($jobDir, 0775, true);
file_put_contents($jobDir . '/backup.json', json_encode([
    'backup_id' => $backupId,
    'job_id' => $jobId,
], JSON_PRETTY_PRINT));
file_put_contents($jobDir . '/manifest.json', json_encode([
    'product' => 'tropatt-core',
    'core_version' => '1.0.0',
    'from_build' => '20260731.001',
    'from_sha' => 'abcdef1234567890abcdef1234567890abcdef12',
    'to_build' => '20260801.001',
    'to_sha' => 'fedcba9876543210fedcba9876543210fedcba98',
], JSON_PRETTY_PRINT));
file_put_contents($jobDir . '/plan.json', json_encode([
    'current_build' => '20260731.001',
    'current_sha' => 'abcdef1234567890abcdef1234567890abcdef12',
], JSON_PRETTY_PRINT));

$token = bin2hex(random_bytes(32));
file_put_contents($storage['sessions'] . '/' . hash('sha256', $token) . '.json', json_encode([
    'token_hash' => hash('sha256', $token),
    'user_id' => 1,
    'created_at' => gmdate('c'),
    'expires_at' => gmdate('c', time() + 600),
    'allowed_actions' => ['preflight', 'download', 'apply', 'resume', 'rollback'],
    'used' => false,
], JSON_PRETTY_PRINT));

// ---------------------------------------------------------------------------
// 6. Run UpdaterKernel::rollback() in a CHILD process (fresh autoload state)
// ---------------------------------------------------------------------------
$driver = $root . '/driver.php';
file_put_contents($driver, <<<'PHP'
<?php
declare(strict_types=1);
error_reporting(E_ERROR | E_PARSE);
$root = (string)($argv[1] ?? '');
$token = (string)($argv[2] ?? '');
$jobId = (string)($argv[3] ?? '');
$backupId = (string)($argv[4] ?? '');

spl_autoload_register(static function (string $class) use ($root): void {
    $prefix = 'Updater\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }
    $path = $root . '/updater/src/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
    if (is_file($path)) {
        require_once $path;
    }
});

$_SERVER['HTTP_AUTHORIZATION'] = 'Bearer ' . $token;
$_POST = ['job_id' => $jobId, 'backup_id' => $backupId];
$_GET = ['action' => 'rollback'];

$kernel = new Updater\UpdaterKernel($root);
$kernel->handle();
PHP
);

// Redirect the child's stderr to a file so a stray deprecation/notice can
// never corrupt the JSON on stdout. The child itself already suppresses
// warnings via error_reporting(E_ERROR | E_PARSE) as a second layer.
$errFile = $root . '/child.err';
$cmd = 'php ' . escapeshellarg($driver) . ' ' . escapeshellarg($root) . ' ' . escapeshellarg($token)
    . ' ' . escapeshellarg($jobId) . ' ' . escapeshellarg($backupId) . ' 2>' . escapeshellarg($errFile);
$output = (string)shell_exec($cmd);
$response = json_decode(trim((string)$output), true);

ok(is_array($response), 'child process returned valid JSON (raw: ' . substr(trim((string)$output), 0, 160) . ')');

$data = is_array($response['data'] ?? null) ? $response['data'] : [];
ok(($response['success'] ?? false) === true, 'rollback() reports success');
ok(($data['rollback']['restored_count'] ?? 0) >= 1, 'file rollback restored at least 1 file');
ok(($data['db_restore']['ok'] ?? false) === true, 'db_restore.ok === true (DB actually restored, not skipped/fatal)');
ok(($data['health']['ok'] ?? false) === true, 'post-rollback health check passes');
ok(($data['installed_core']['core_build'] ?? null) === '20260731.001', 'installed_core reverted to previous build');
ok(($data['installed_core']['short_sha'] ?? null) === 'abcdef1', 'installed_core reverted to previous sha');

// ---------------------------------------------------------------------------
// 7. Database really restored (migration mutation undone)
// ---------------------------------------------------------------------------
$tables = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
ok(in_array('krt_items', $tables, true) && in_array('krt_tags', $tables, true), 'krt_items + krt_tags exist again');
ok(!in_array('krt_brand_new', $tables, true), 'migration-created table krt_brand_new removed');
$name = $pdo->query('SELECT name FROM krt_items WHERE id = 1')->fetchColumn();
ok($name === 'alpha', 'row data restored (name=alpha, got ' . var_export($name, true) . ')');
$tagCount = $pdo->query('SELECT COUNT(*) FROM krt_tags')->fetchColumn();
ok((int)$tagCount === 2, 'FK child rows restored (2 tags, got ' . var_export($tagCount, true) . ')');

// ---------------------------------------------------------------------------
// 8. The file rollback restored the OLD stub over the real class (proving it
//    ran AFTER the DB restore, and that a future rollback would load the stub)
// ---------------------------------------------------------------------------
$onDisk = (string)file_get_contents($root . '/updater/src/Backup/DatabaseBackupManager.php');
ok(str_contains($onDisk, 'extends BackupManager') && !str_contains($onDisk, 'function restore'),
    'file rollback restored the old stub over DatabaseBackupManager.php (order: DB first, files second)');

// ---------------------------------------------------------------------------
// 9. Cleanup
// ---------------------------------------------------------------------------
$pdo->exec('SET FOREIGN_KEY_CHECKS=0');
$pdo->exec('DROP TABLE IF EXISTS krt_tags');
$pdo->exec('DROP TABLE IF EXISTS krt_items');
$pdo->exec('DROP TABLE IF EXISTS krt_brand_new');
$pdo->exec('SET FOREIGN_KEY_CHECKS=1');
removeTree($root);

echo "\nResult: {$passes} passed, {$failures} failed\n";
exit($failures === 0 ? 0 : 1);
