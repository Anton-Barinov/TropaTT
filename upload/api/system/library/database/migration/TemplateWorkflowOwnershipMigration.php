<?php
declare(strict_types=1);

namespace Api\System\Library\Database\Migration;

use Api\System\Library\Database\IndexHelper;
use PDO;

final class TemplateWorkflowOwnershipMigration implements MigrationInterface
{
    public function key(): string
    {
        return '20260418_000005_template_workflow_ownership';
    }

    public function description(): string
    {
        return 'Add created_by_user_id ownership columns for templates and workflow rules';
    }

    public function up(PDO $pdo, string $driver): void
    {
        $this->ensureColumn($pdo, $driver, 'task_templates', 'created_by_user_id', 'INTEGER NULL');
        $this->ensureColumn($pdo, $driver, 'project_templates', 'created_by_user_id', 'INTEGER NULL');
        $this->ensureColumn($pdo, $driver, 'automation_rules', 'created_by_user_id', 'INTEGER NULL');

        foreach ([
            ['task_templates', 'idx_task_templates_created_by', 'created_by_user_id'],
            ['project_templates', 'idx_project_templates_created_by', 'created_by_user_id'],
            ['automation_rules', 'idx_automation_rules_created_by', 'created_by_user_id'],
        ] as [$table, $index, $columns]) {
            IndexHelper::createIndexIfNotExists($pdo, $table, $index, $columns);
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
        } catch (\Throwable $e) {
            error_log('[TemplateWorkflowOwnershipMigration::columnExists] ' . $e->getMessage());
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
