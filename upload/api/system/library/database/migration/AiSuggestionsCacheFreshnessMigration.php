<?php
declare(strict_types=1);

namespace Api\System\Library\Database\Migration;

use PDO;

final class AiSuggestionsCacheFreshnessMigration implements MigrationInterface
{
    public function key(): string
    {
        return '20260430_000037_ai_suggestions_cache_freshness';
    }

    public function description(): string
    {
        return 'Add ai_suggestions cache/freshness columns and indexes';
    }

    public function up(PDO $pdo, string $driver): void
    {
        if (!$this->tableExists($pdo, $driver, 'ai_suggestions')) {
            return;
        }

        $this->ensureColumn($pdo, $driver, 'ai_suggestions', 'cache_key', 'VARCHAR(64) NULL');
        $this->ensureColumn($pdo, $driver, 'ai_suggestions', 'dependency_fingerprint', 'VARCHAR(64) NULL');
        $this->ensureColumn($pdo, $driver, 'ai_suggestions', 'cache_status', 'VARCHAR(32) NULL');
        $this->ensureColumn($pdo, $driver, 'ai_suggestions', 'stale_reason', 'VARCHAR(64) NULL');
        $this->ensureColumn($pdo, $driver, 'ai_suggestions', 'date_bucket', 'VARCHAR(32) NULL');
        $this->ensureColumn($pdo, $driver, 'ai_suggestions', 'provider_public_id', 'VARCHAR(64) NULL');
        $this->ensureColumn($pdo, $driver, 'ai_suggestions', 'provider_code', 'VARCHAR(64) NULL');
        $this->ensureColumn($pdo, $driver, 'ai_suggestions', 'model', 'VARCHAR(190) NULL');
        $this->ensureColumn($pdo, $driver, 'ai_suggestions', 'last_used_at', 'DATETIME NULL');
        $this->ensureColumn($pdo, $driver, 'ai_suggestions', 'usage_count', 'INTEGER DEFAULT 0');
        $this->ensureColumn($pdo, $driver, 'ai_suggestions', 'request_id', 'VARCHAR(64) NULL');
        $this->ensureColumn($pdo, $driver, 'ai_suggestions', 'invalidated_at', 'DATETIME NULL');
        $this->ensureColumn($pdo, $driver, 'ai_suggestions', 'result_meta_json', 'TEXT NULL');

        $this->createIndexIfMissing($pdo, 'ai_suggestions', 'idx_ai_suggestions_cache_lookup_v2', 'created_by_user_id, intent_code, entity_type, entity_public_id, cache_key, created_at');
        $this->createIndexIfMissing($pdo, 'ai_suggestions', 'idx_ai_suggestions_cache_status_v2', 'cache_status, created_at');
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

    private function createIndexIfMissing(PDO $pdo, string $table, string $indexName, string $columns): void
    {
        try {
            $pdo->exec(sprintf('CREATE INDEX IF NOT EXISTS %s ON %s (%s)', $indexName, $table, $columns));
            return;
        } catch (\Throwable $e) {
            error_log('[AiSuggestionsCacheFreshnessMigration::createIndexIfMissing] CREATE INDEX: ' . $e->getMessage());
        }

        try {
            $pdo->exec(sprintf('CREATE INDEX %s ON %s (%s)', $indexName, $table, $columns));
        } catch (\Throwable $e) {
            error_log('[AiSuggestionsCacheFreshnessMigration::createIndexIfMissing] CREATE INDEX: ' . $e->getMessage());
        }
    }

    private function tableExists(PDO $pdo, string $driver, string $table): bool
    {
        try {
            return match ($driver) {
                'mysql' => $this->mysqlTableExists($pdo, $table),
                'pgsql' => $this->pgsqlTableExists($pdo, $table),
                'sqlsrv' => $this->sqlsrvTableExists($pdo, $table),
                default => $this->sqliteTableExists($pdo, $table),
            };
        } catch (\Throwable $e) {
            error_log('[AiSuggestionsCacheFreshnessMigration::tableExists] ' . $e->getMessage());
            return false;
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
            error_log('[AiSuggestionsCacheFreshnessMigration::columnExists] ' . $e->getMessage());
            return false;
        }
    }

    private function mysqlTableExists(PDO $pdo, string $table): bool
    {
        $stmt = $pdo->prepare('SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = :table_name LIMIT 1');
        $stmt->execute(['table_name' => $table]);
        return $stmt->fetchColumn() !== false;
    }

    private function pgsqlTableExists(PDO $pdo, string $table): bool
    {
        $stmt = $pdo->prepare('SELECT 1 FROM information_schema.tables WHERE table_schema = current_schema() AND table_name = :table_name LIMIT 1');
        $stmt->execute(['table_name' => $table]);
        return $stmt->fetchColumn() !== false;
    }

    private function sqliteTableExists(PDO $pdo, string $table): bool
    {
        $stmt = $pdo->prepare("SELECT name FROM sqlite_master WHERE type = 'table' AND name = :table_name LIMIT 1");
        $stmt->execute(['table_name' => $table]);
        return $stmt->fetchColumn() !== false;
    }

    private function sqlsrvTableExists(PDO $pdo, string $table): bool
    {
        $stmt = $pdo->prepare('SELECT 1 FROM sys.tables WHERE name = :table_name');
        $stmt->execute(['table_name' => $table]);
        return $stmt->fetchColumn() !== false;
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
