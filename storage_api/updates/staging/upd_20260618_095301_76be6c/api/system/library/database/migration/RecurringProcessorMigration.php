<?php
declare(strict_types=1);

namespace Api\System\Library\Database\Migration;

use PDO;

final class RecurringProcessorMigration implements MigrationInterface
{
    public function key(): string
    {
        return '20260606_000001_recurring_processor';
    }

    public function description(): string
    {
        return 'Add last_processed_at to recurring_rules and next_occurrence to recurring_instances';
    }

    public function up(PDO $pdo, string $driver): void
    {
        if (!$this->columnExists($pdo, $driver, 'recurring_rules', 'last_processed_at')) {
            $definition = $driver === 'sqlsrv' ? 'DATETIME2 NULL' : 'DATETIME NULL';
            $sql = match ($driver) {
                'sqlsrv' => 'ALTER TABLE recurring_rules ADD last_processed_at ' . $definition,
                default => 'ALTER TABLE recurring_rules ADD COLUMN last_processed_at ' . $definition,
            };
            $pdo->exec($sql);
        }

        if (!$this->columnExists($pdo, $driver, 'recurring_instances', 'next_occurrence')) {
            $definition = $driver === 'sqlsrv' ? 'DATETIME2 NULL' : 'DATETIME NULL';
            $sql = match ($driver) {
                'sqlsrv' => 'ALTER TABLE recurring_instances ADD next_occurrence ' . $definition,
                default => 'ALTER TABLE recurring_instances ADD COLUMN next_occurrence ' . $definition,
            };
            $pdo->exec($sql);
        }

        if (!$this->columnExists($pdo, $driver, 'recurring_instances', 'processed_at')) {
            $definition = $driver === 'sqlsrv' ? 'DATETIME2 NULL' : 'DATETIME NULL';
            $sql = match ($driver) {
                'sqlsrv' => 'ALTER TABLE recurring_instances ADD processed_at ' . $definition,
                default => 'ALTER TABLE recurring_instances ADD COLUMN processed_at ' . $definition,
            };
            $pdo->exec($sql);
        }
    }

    private function columnExists(PDO $pdo, string $driver, string $table, string $column): bool
    {
        try {
            if ($driver === 'mysql') {
                $stmt = $pdo->prepare('SELECT 1 FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = :table_name AND column_name = :column_name LIMIT 1');
                $stmt->execute(['table_name' => $table, 'column_name' => $column]);
                return $stmt->fetchColumn() !== false;
            }

            if ($driver === 'pgsql') {
                $stmt = $pdo->prepare('SELECT 1 FROM information_schema.columns WHERE table_schema = current_schema() AND table_name = :table_name AND column_name = :column_name LIMIT 1');
                $stmt->execute(['table_name' => $table, 'column_name' => $column]);
                return $stmt->fetchColumn() !== false;
            }

            if ($driver === 'sqlsrv') {
                $stmt = $pdo->prepare('SELECT 1 FROM sys.columns WHERE object_id = OBJECT_ID(:table_name) AND name = :column_name');
                $stmt->execute(['table_name' => $table, 'column_name' => $column]);
                return $stmt->fetchColumn() !== false;
            }

            $rows = $pdo->query("PRAGMA table_info({$table})")->fetchAll() ?: [];
            foreach ($rows as $row) {
                if ((string)($row['name'] ?? '') === $column) {
                    return true;
                }
            }
        } catch (\Throwable) {
            return false;
        }

        return false;
    }
}
