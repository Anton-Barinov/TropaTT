<?php
declare(strict_types=1);

namespace Api\System\Library\Database\Migration;

use Api\System\Library\Database\IndexHelper;
use Api\System\Library\Support\AppLog;
use PDO;

final class AiSuggestionsOrganizationScopeMigration implements MigrationInterface
{
    public function key(): string
    {
        return '20260501_000038_ai_suggestions_organization_scope';
    }

    public function description(): string
    {
        return 'Scope AI suggestions and analytics explanations by workspace';
    }

    public function up(PDO $pdo, string $driver): void
    {
        if (!$this->tableExists($pdo, $driver, 'ai_suggestions')) {
            return;
        }

        $this->ensureColumn($pdo, $driver, 'ai_suggestions', 'organization_id', 'INTEGER NULL');
        IndexHelper::createIndexIfNotExists(
            $pdo,
            'ai_suggestions',
            'idx_ai_suggestions_organization_created',
            'organization_id, intent_code, entity_type, entity_public_id, created_at'
        );
    }

    private function ensureColumn(PDO $pdo, string $driver, string $table, string $column, string $definition): void
    {
        if ($this->columnExists($pdo, $driver, $table, $column)) {
            return;
        }

        $sql = match ($driver) {
            'sqlsrv' => sprintf('ALTER TABLE %s ADD %s %s', $table, $column, $definition),
            default => sprintf('ALTER TABLE %s ADD COLUMN %s %s', $table, $column, $definition),
        };
        $pdo->exec($sql);
    }

    private function tableExists(PDO $pdo, string $driver, string $table): bool
    {
        try {
            return match ($driver) {
                'mysql' => (bool)$pdo->query("SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = " . $pdo->quote($table))->fetchColumn(),
                'pgsql' => (bool)$pdo->query("SELECT 1 FROM information_schema.tables WHERE table_schema = current_schema() AND table_name = " . $pdo->quote($table))->fetchColumn(),
                'sqlsrv' => (bool)$pdo->query("SELECT 1 FROM sys.tables WHERE name = " . $pdo->quote($table))->fetchColumn(),
                default => (bool)$pdo->query("SELECT 1 FROM sqlite_master WHERE type = 'table' AND name = " . $pdo->quote($table))->fetchColumn(),
            };
        } catch (\Throwable $e) {
            AppLog::error('[AiSuggestionsOrganizationScopeMigration::tableExists] ' . $e->getMessage());
            return false;
        }
    }

    private function columnExists(PDO $pdo, string $driver, string $table, string $column): bool
    {
        try {
            return match ($driver) {
                'mysql' => (bool)$pdo->query("SELECT 1 FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = " . $pdo->quote($table) . " AND column_name = " . $pdo->quote($column))->fetchColumn(),
                'pgsql' => (bool)$pdo->query("SELECT 1 FROM information_schema.columns WHERE table_schema = current_schema() AND table_name = " . $pdo->quote($table) . " AND column_name = " . $pdo->quote($column))->fetchColumn(),
                'sqlsrv' => (bool)$pdo->query("SELECT 1 FROM sys.columns c JOIN sys.tables t ON t.object_id = c.object_id WHERE t.name = " . $pdo->quote($table) . " AND c.name = " . $pdo->quote($column))->fetchColumn(),
                default => $this->sqliteColumnExists($pdo, $table, $column),
            };
        } catch (\Throwable $e) {
            AppLog::error('[AiSuggestionsOrganizationScopeMigration::columnExists] ' . $e->getMessage());
            return false;
        }
    }

    private function sqliteColumnExists(PDO $pdo, string $table, string $column): bool
    {
        $statement = $pdo->query('PRAGMA table_info(' . $table . ')');
        if ($statement === false) {
            return false;
        }
        while (($row = $statement->fetch(PDO::FETCH_ASSOC)) !== false) {
            if (strcasecmp((string)($row['name'] ?? ''), $column) === 0) {
                return true;
            }
        }
        return false;
    }
}
