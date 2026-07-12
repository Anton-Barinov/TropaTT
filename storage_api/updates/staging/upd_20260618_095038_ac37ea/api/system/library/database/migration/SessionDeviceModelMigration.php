<?php
declare(strict_types=1);

namespace Api\System\Library\Database\Migration;

use PDO;

final class SessionDeviceModelMigration implements MigrationInterface
{
    public function key(): string
    {
        return '20260419_000008_session_device_model';
    }

    public function description(): string
    {
        return 'Add device fingerprint/name fields on user sessions and device index';
    }

    public function up(PDO $pdo, string $driver): void
    {
        $deviceNameDefinition = $driver === 'sqlsrv' ? 'NVARCHAR(190) NULL' : 'VARCHAR(190) NULL';
        $this->ensureColumn($pdo, $driver, 'user_sessions', 'device_fingerprint', 'VARCHAR(64) NULL');
        $this->ensureColumn($pdo, $driver, 'user_sessions', 'device_name', $deviceNameDefinition);

        foreach ([
            'CREATE INDEX IF NOT EXISTS idx_sessions_user_device ON user_sessions(user_id, device_fingerprint)',
        ] as $sql) {
            try {
                $pdo->exec($sql);
            } catch (\Throwable) {
                // Ignore unsupported IF NOT EXISTS syntax or duplicate index races.
            }
        }
    }

    private function ensureColumn(PDO $pdo, string $driver, string $table, string $column, string $definition): void
    {
        if ($this->columnExists($pdo, $driver, $table, $column)) {
            return;
        }

        $sql = match ($driver) {
            'mysql', 'pgsql', 'sqlite' => sprintf('ALTER TABLE %s ADD COLUMN %s %s', $table, $column, $definition),
            'sqlsrv' => sprintf('ALTER TABLE %s ADD %s %s', $table, $column, $definition),
            default => sprintf('ALTER TABLE %s ADD COLUMN %s %s', $table, $column, $definition),
        };

        $pdo->exec($sql);
    }

    private function columnExists(PDO $pdo, string $driver, string $table, string $column): bool
    {
        try {
            return match ($driver) {
                'mysql' => $this->mysqlColumnExists($pdo, $table, $column),
                'pgsql' => $this->pgsqlColumnExists($pdo, $table, $column),
                'sqlsrv' => $this->sqlsrvColumnExists($pdo, $table, $column),
                default => $this->sqliteColumnExists($pdo, $table, $column),
            };
        } catch (\Throwable) {
            return false;
        }
    }

    private function mysqlColumnExists(PDO $pdo, string $table, string $column): bool
    {
        $stmt = $pdo->prepare('SELECT 1 FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = :table_name AND column_name = :column_name LIMIT 1');
        $stmt->execute([
            'table_name' => $table,
            'column_name' => $column,
        ]);

        return $stmt->fetchColumn() !== false;
    }

    private function pgsqlColumnExists(PDO $pdo, string $table, string $column): bool
    {
        $stmt = $pdo->prepare('SELECT 1 FROM information_schema.columns WHERE table_schema = current_schema() AND table_name = :table_name AND column_name = :column_name LIMIT 1');
        $stmt->execute([
            'table_name' => $table,
            'column_name' => $column,
        ]);

        return $stmt->fetchColumn() !== false;
    }

    private function sqliteColumnExists(PDO $pdo, string $table, string $column): bool
    {
        $rows = $pdo->query('PRAGMA table_info(' . $table . ')')->fetchAll() ?: [];
        foreach ($rows as $row) {
            if ((string)($row['name'] ?? '') === $column) {
                return true;
            }
        }

        return false;
    }

    private function sqlsrvColumnExists(PDO $pdo, string $table, string $column): bool
    {
        $stmt = $pdo->prepare('SELECT 1 FROM sys.columns WHERE object_id = OBJECT_ID(:table_name) AND name = :column_name');
        $stmt->execute([
            'table_name' => $table,
            'column_name' => $column,
        ]);

        return $stmt->fetchColumn() !== false;
    }
}

