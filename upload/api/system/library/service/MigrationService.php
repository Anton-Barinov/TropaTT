<?php
declare(strict_types=1);

namespace Api\System\Library\Service;

use Api\System\Library\Database\ConnectionManager;
use Api\System\Library\Database\Migration\MigrationManager;

final class MigrationService
{
    public function __construct(
        private readonly ConnectionManager $connections,
        private readonly MigrationManager $migrations
    ) {
    }

    public function status(): array
    {
        $db = $this->connections->resolvedDatabaseConfig();
        $pdo = $this->connections->connect();
        $driver = (string)($db['driver'] ?? 'sqlite');

        return [
            'database' => [
                'driver' => $driver,
                'database' => (string)($db['database'] ?? ''),
            ],
            'migration_status' => $this->migrations->status($pdo, $driver),
        ];
    }

    public function up(): array
    {
        $db = $this->connections->resolvedDatabaseConfig();
        $pdo = $this->connections->connect();
        $driver = (string)($db['driver'] ?? 'sqlite');

        $executed = $this->migrations->migrateUp($pdo, $driver);
        $status = $this->migrations->status($pdo, $driver);

        return [
            'database' => [
                'driver' => $driver,
                'database' => (string)($db['database'] ?? ''),
            ],
            'executed' => $executed,
            'migration_status' => $status,
        ];
    }

    public function dryRun(): array
    {
        $db = $this->connections->resolvedDatabaseConfig();
        $pdo = $this->connections->connect();
        $driver = (string)($db['driver'] ?? 'sqlite');

        return [
            'database' => [
                'driver' => $driver,
                'database' => (string)($db['database'] ?? ''),
            ],
            'dry_run' => $this->migrations->dryRun($pdo, $driver),
        ];
    }

    public function rollbackCheck(): array
    {
        $db = $this->connections->resolvedDatabaseConfig();
        $pdo = $this->connections->connect();
        $driver = (string)($db['driver'] ?? 'sqlite');

        return [
            'database' => [
                'driver' => $driver,
                'database' => (string)($db['database'] ?? ''),
            ],
            'rollback_check' => $this->migrations->rollbackCheck($pdo, $driver),
        ];
    }
}
