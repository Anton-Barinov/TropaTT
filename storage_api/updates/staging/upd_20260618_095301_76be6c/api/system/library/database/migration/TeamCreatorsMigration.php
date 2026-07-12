<?php
declare(strict_types=1);

namespace Api\System\Library\Database\Migration;

use PDO;

final class TeamCreatorsMigration implements MigrationInterface
{
    public function key(): string
    {
        return '20260423_000015_team_creators';
    }

    public function description(): string
    {
        return 'Add created_by_user_id to teams and backfill from manager where possible';
    }

    public function up(PDO $pdo, string $driver): void
    {
        $this->ensureColumn($pdo, $driver, 'teams', 'created_by_user_id', 'INTEGER NULL');
        $this->backfillCreators($pdo);
        $this->createIndexIfMissing($pdo, 'teams', 'idx_teams_created_by', 'created_by_user_id', false);
    }

    private function backfillCreators(PDO $pdo): void
    {
        try {
            $pdo->exec('UPDATE teams SET created_by_user_id = manager_user_id WHERE created_by_user_id IS NULL AND manager_user_id IS NOT NULL');
        } catch (\Throwable) {
            // Ignore on engines with transient locking issues; column creation is the critical part.
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

    private function createIndexIfMissing(PDO $pdo, string $table, string $name, string $columns, bool $unique): void
    {
        if ($this->indexExists($pdo, $table, $name)) {
            return;
        }

        $sql = sprintf(
            'CREATE %s INDEX %s ON %s(%s)',
            $unique ? 'UNIQUE' : '',
            $name,
            $table,
            $columns
        );

        try {
            $pdo->exec(trim(preg_replace('/\s+/', ' ', $sql) ?? $sql));
        } catch (\Throwable) {
            // Ignore duplicate/race conditions.
        }
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

    private function indexExists(PDO $pdo, string $table, string $name): bool
    {
        $driver = (string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME);

        try {
            return match ($driver) {
                'mysql' => $this->mysqlIndexExists($pdo, $table, $name),
                'pgsql' => $this->pgsqlIndexExists($pdo, $name),
                'sqlsrv' => $this->sqlsrvIndexExists($pdo, $table, $name),
                default => $this->sqliteIndexExists($pdo, $name),
            };
        } catch (\Throwable) {
            return false;
        }
    }

    private function mysqlIndexExists(PDO $pdo, string $table, string $name): bool
    {
        $stmt = $pdo->prepare('SHOW INDEX FROM ' . $table . ' WHERE Key_name = :name');
        $stmt->execute(['name' => $name]);
        return $stmt->fetchColumn() !== false;
    }

    private function pgsqlIndexExists(PDO $pdo, string $name): bool
    {
        $stmt = $pdo->prepare('SELECT 1 FROM pg_indexes WHERE schemaname = current_schema() AND indexname = :name LIMIT 1');
        $stmt->execute(['name' => $name]);
        return $stmt->fetchColumn() !== false;
    }

    private function sqliteIndexExists(PDO $pdo, string $name): bool
    {
        $stmt = $pdo->prepare('SELECT 1 FROM sqlite_master WHERE type = :type AND name = :name LIMIT 1');
        $stmt->execute([
            'type' => 'index',
            'name' => $name,
        ]);
        return $stmt->fetchColumn() !== false;
    }

    private function sqlsrvIndexExists(PDO $pdo, string $table, string $name): bool
    {
        $stmt = $pdo->prepare('SELECT 1 FROM sys.indexes WHERE object_id = OBJECT_ID(:table_name) AND name = :name');
        $stmt->execute([
            'table_name' => $table,
            'name' => $name,
        ]);
        return $stmt->fetchColumn() !== false;
    }
}
