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

echo "Running migrations...\n";
$migrations->migrateUp($pdo, $driver);
echo "Migrations completed.\n";