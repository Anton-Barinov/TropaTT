<?php
declare(strict_types=1);

namespace Api\System\Library\Database\Migration;

use PDO;

final class NotificationEventPayloadMigration implements MigrationInterface
{
    public function key(): string
    {
        return '20260423_000020_notification_event_payload';
    }

    public function description(): string
    {
        return 'Expand notifications with entity/action/actor/link metadata and indexes';
    }

    public function up(PDO $pdo, string $driver): void
    {
        $textDefinition = $driver === 'sqlsrv' ? 'NVARCHAR(MAX) NULL' : 'TEXT NULL';
        $varchar = static fn(int $length): string => $driver === 'sqlsrv' ? 'NVARCHAR(' . $length . ') NULL' : 'VARCHAR(' . $length . ') NULL';

        $columns = [
            'entity_type' => $varchar(64),
            'entity_public_id' => $varchar(64),
            'action_code' => $varchar(64),
            'actor_user_id' => 'INTEGER NULL',
            'actor_public_id' => $varchar(64),
            'actor_name' => $varchar(255),
            'link' => $varchar(1024),
            'payload_json' => $textDefinition,
        ];

        foreach ($columns as $column => $definition) {
            $this->ensureColumn($pdo, $driver, 'notifications', $column, $definition);
        }

        $this->createIndexIfMissing($pdo, 'notifications', 'idx_notifications_user_created', 'user_id, created_at', false);
        $this->createIndexIfMissing($pdo, 'notifications', 'idx_notifications_user_unread_created', 'user_id, is_read, created_at', false);
        $this->createIndexIfMissing($pdo, 'notifications', 'idx_notifications_user_category_unread', 'user_id, category, is_read', false);
        $this->createIndexIfMissing($pdo, 'notifications', 'idx_notifications_entity', 'entity_type, entity_public_id', false);
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
        } catch (\Throwable $e) {
            error_log('[NotificationEventPayloadMigration::createIndexIfMissing] ' . $e->getMessage());
            // Ignore unsupported IF NOT EXISTS semantics and concurrent duplicate creation.
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
        } catch (\Throwable $e) {
            error_log('[NotificationEventPayloadMigration::columnExists] ' . $e->getMessage());
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
        } catch (\Throwable $e) {
            error_log('[NotificationEventPayloadMigration::indexExists] ' . $e->getMessage());
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
