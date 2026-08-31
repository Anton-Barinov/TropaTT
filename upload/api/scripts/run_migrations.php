<?php

declare(strict_types=1);
if (PHP_SAPI !== "cli") { http_response_code(404); exit; }


use Api\System\Library\Config;
use Api\System\Library\Database\ConnectionManager;
use Api\System\Library\Database\Migration\MigrationManager;
use Api\System\Library\Database\SchemaManager;

require_once __DIR__ . '/../system/library/support/Autoloader.php';

$basePath = dirname(__DIR__);
$autoloader = new Api\System\Library\Support\Autoloader($basePath);
$autoloader->register();

// ── CLI options ───────────────────────────────────────────────────────────────
$mode = 'migrate';   // migrate | status | dry-run
$limit = PHP_INT_MAX;

$argv = $_SERVER['argv'] ?? [];
$argc = count($argv);
for ($i = 1; $i < $argc; $i++) {
    $arg = (string)$argv[$i];
    if ($arg === '--status') {
        $mode = 'status';
    } elseif ($arg === '--dry-run') {
        $mode = 'dry-run';
    } elseif ($arg === '--help' || $arg === '-h') {
        usage(0);
    } elseif ($arg === '--limit') {
        $i++;
        if ($i >= $argc || !ctype_digit((string)$argv[$i])) {
            fwrite(STDERR, "Option --limit requires a positive integer.\n\n");
            usage(1);
        }
        $limit = max(1, (int)$argv[$i]);
    } elseif (preg_match('/^--limit=(\d+)$/', $arg, $m) === 1) {
        $limit = max(1, (int)$m[1]);
    } else {
        fwrite(STDERR, "Unknown option: {$arg}\n\n");
        usage(1);
    }
}

try {
    if (class_exists(Api\System\Library\Support\EnvLoader::class)) {
        Api\System\Library\Support\EnvLoader::loadFiles([
            dirname($basePath) . '/.env',
            $basePath . '/.env',
            dirname($basePath) . '/.env.local',
            $basePath . '/.env.local',
        ]);
    }

    $config = new Config();
    $config->load($basePath . '/config/database.php', 'database');
    $connectionManager = new ConnectionManager($config);
    $pdo = $connectionManager->connect();

    $dbConfig = $config->get('database.connections.' . ($config->get('database.default') ?: 'sqlite'));
    $driver = (string)($dbConfig['driver'] ?? 'sqlite');

    $schema = new SchemaManager();
    $migrations = new MigrationManager($schema);

    if ($mode === 'status') {
        $status = $migrations->status($pdo, $driver);
        echo 'Applied: ' . count($status['applied']) . ' of ' . count($status['all']) . "\n";
        echo 'Pending: ' . count($status['pending']) . "\n";
        foreach ($status['pending'] as $key) {
            echo '  - ' . $key . "\n";
        }
        if ($status['pending'] === []) {
            echo "Database schema is up to date.\n";
        }
        exit(0);
    }

    if ($mode === 'dry-run') {
        $dry = $migrations->dryRun($pdo, $driver);
        echo 'Pending: ' . count($dry['pending']) . "\n";
        foreach ($dry['pending'] as $key) {
            echo '  - ' . $key . "\n";
        }
        if ($dry['pending'] === []) {
            echo "Database schema is up to date. Nothing to run.\n";
        } else {
            echo "Run \"php api/scripts/run_migrations.php\" to apply them.\n";
        }
        exit(0);
    }

    echo 'Pending migrations: ' . count($migrations->status($pdo, $driver)['pending']) . "\n";
    echo "Running migrations...\n";
    $executed = $migrations->migrateUpLimit($pdo, $driver, $limit);
    echo 'Executed: ' . count($executed) . "\n";
    foreach ($executed as $key) {
        echo '  + ' . $key . "\n";
    }

    $status = $migrations->status($pdo, $driver);
    if ($status['pending'] === []) {
        echo "Migrations completed. Database schema is up to date.\n";
    } else {
        echo 'Remaining pending: ' . count($status['pending']) . " — run the script again (or with a higher --limit) to continue.\n";
    }
    exit(0);
} catch (Throwable $e) {
    fwrite(STDERR, 'Migration run failed: ' . $e->getMessage() . "\n");
    fwrite(
        STDERR,
        "If this is a database connection error, check the database credentials in api/.env\n"
        . "(DB_CONNECTION, DB_HOST, DB_DATABASE, DB_USERNAME, DB_PASSWORD).\n"
        . "If it is a migration error, check the CRM error log for details and contact support.\n"
    );
    exit(1);
}

function usage(int $exitCode): void
{
    $lines = [
        'TropaTT database migrations',
        '',
        'Usage:',
        '  php api/scripts/run_migrations.php [options]',
        '',
        'Options:',
        '  --status      Show applied and pending migrations, change nothing',
        '  --dry-run     Show which migrations would run, change nothing',
        '  --limit N     Apply at most N pending migrations (default: all)',
        '  --help, -h    Show this help',
        '',
        'The script reads the database credentials from api/.env — the same .env file',
        'written by the installer (DB_CONNECTION, DB_HOST, DB_DATABASE, DB_USERNAME,',
        'DB_PASSWORD). Run it from the installation root. All migrations are idempotent:',
        're-running is safe and only applies what is still missing.',
        '',
        'Examples:',
        '  php api/scripts/run_migrations.php --status',
        '  php api/scripts/run_migrations.php --dry-run',
        '  php api/scripts/run_migrations.php',
        '  php api/scripts/run_migrations.php --limit 5',
    ];
    fwrite($exitCode === 0 ? STDOUT : STDERR, implode("\n", $lines) . "\n");
    exit($exitCode);
}
