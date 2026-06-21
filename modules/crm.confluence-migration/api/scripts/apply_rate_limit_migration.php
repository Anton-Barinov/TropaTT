<?php
declare(strict_types=1);

/**
 * One-time script to apply 002_rate_limits migration.
 * php modules/crm.confluence-migration/api/scripts/apply_rate_limit_migration.php
 */

$basePath = dirname(__DIR__, 4);
require_once $basePath . '/api/system/library/support/Autoloader.php';

$autoloader = new Api\System\Library\Support\Autoloader($basePath . '/api');
$autoloader->register();

if (class_exists(Api\System\Library\Support\EnvLoader::class)) {
    Api\System\Library\Support\EnvLoader::loadFiles([
        $basePath . '/.env',
        $basePath . '/.env.local',
        $basePath . '/api/.env',
        $basePath . '/api/.env.local',
    ]);
}

$config = new Api\System\Library\Config($basePath . '/api/config');
$config->load($basePath . '/api/config/database.php', 'database');

$connectionManager = new Api\System\Library\Database\ConnectionManager($config);
$pdo = $connectionManager->connect();

$moduleName = 'crm.confluence-migration';
$migrationName = '002_rate_limits';
$sqlFile = __DIR__ . '/../migration/002_rate_limits.sql';

// Check if already applied
$stmt = $pdo->prepare("SELECT COUNT(*) FROM module_migrations WHERE module_name = :mod AND migration_name = :mig");
$stmt->execute(['mod' => $moduleName, 'mig' => $migrationName]);
if ((int)$stmt->fetchColumn() > 0) {
    fwrite(STDOUT, "Migration '$migrationName' already applied.\n");
    exit(0);
}

if (!file_exists($sqlFile)) {
    fwrite(STDERR, "Migration file not found: $sqlFile\n");
    exit(1);
}

// Apply
$sql = file_get_contents($sqlFile);
try {
    $pdo->beginTransaction();
    $pdo->exec($sql);
    if (!$pdo->inTransaction()) {
        $pdo->beginTransaction();
    }
    $batch = $pdo->query("SELECT COALESCE(MAX(batch), 0) + 1 FROM module_migrations WHERE module_name = '$moduleName'")->fetchColumn();
    $ins = $pdo->prepare("INSERT INTO module_migrations (module_name, migration_name, batch, applied_at) VALUES (:mod, :mig, :batch, NOW())");
    $ins->execute(['mod' => $moduleName, 'mig' => $migrationName, 'batch' => $batch]);
    $pdo->commit();
    fwrite(STDOUT, "Migration '$migrationName' applied successfully.\n");
} catch (\Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    fwrite(STDERR, "Migration failed: " . $e->getMessage() . "\n");
    exit(1);
}

// Verify
$check = $pdo->query("SHOW TABLES LIKE 'module_confluence_rate_limits'");
if ($check->fetchColumn()) {
    fwrite(STDOUT, "Table 'module_confluence_rate_limits' created.\n");
} else {
    fwrite(STDERR, "Table NOT created.\n");
    exit(1);
}
