<?php
declare(strict_types=1);

namespace Updater\Db;

final class Connection
{
    /**
     * Opens the CRM database connection the same way MigrationRunner does:
     * loads the API autoloader + env files, then connects via ConnectionManager.
     * Works on shared hosting without shell access.
     *
     * @return array{pdo:\PDO, driver:string, database:array<string,mixed>}
     */
    private static bool $registered = false;

    public static function open(string $basePath): array
    {
        $apiPath = $basePath . '/api';
        $autoloaderFile = $apiPath . '/system/library/support/Autoloader.php';
        if (!is_file($autoloaderFile)) {
            throw new \RuntimeException('api autoloader not found');
        }
        if (!self::$registered) {
            require_once $autoloaderFile;
            (new \Api\System\Library\Support\Autoloader($apiPath))->register();
            self::$registered = true;
        }

        if (class_exists(\Api\System\Library\Support\EnvLoader::class)) {
            \Api\System\Library\Support\EnvLoader::loadFiles([
                $basePath . '/.env',
                $apiPath . '/.env',
                $basePath . '/.env.local',
                $apiPath . '/.env.local',
            ]);
        }

        $config = new \Api\System\Library\Config();
        $config->load($apiPath . '/config/database.php', 'database');

        $connection = new \Api\System\Library\Database\ConnectionManager($config);
        $pdo = $connection->connect();
        $db = $connection->resolvedDatabaseConfig();

        return [
            'pdo' => $pdo,
            'driver' => (string)($db['driver'] ?? 'sqlite'),
            'database' => $db,
        ];
    }
}
