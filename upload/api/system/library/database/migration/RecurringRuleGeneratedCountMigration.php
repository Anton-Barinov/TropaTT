<?php
declare(strict_types=1);

namespace Api\System\Library\Database\Migration;

use PDO;

final class RecurringRuleGeneratedCountMigration implements MigrationInterface
{
    public function key(): string
    {
        return '20260920_000002_recurring_rule_generated_count';
    }

    public function description(): string
    {
        return 'Add generated_count column to recurring_rules table';
    }

    public function up(PDO $pdo, string $driver): void
    {
        if (!$this->columnExists($pdo, $driver, 'recurring_rules', 'generated_count')) {
            $sql = match ($driver) {
                'sqlsrv' => 'ALTER TABLE recurring_rules ADD generated_count INT DEFAULT 0',
                default => 'ALTER TABLE recurring_rules ADD COLUMN generated_count INT DEFAULT 0',
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
                $stmt = $pdo->prepare('SELECT 1 FROM information_schema.columns WHERE table_name = :table_name AND column_name = :column_name LIMIT 1');
                $stmt->execute(['table_name' => $table, 'column_name' => $column]);
                return $stmt->fetchColumn() !== false;
            }

            if ($driver === 'sqlite') {
                $stmt = $pdo->query("PRAGMA table_info({$table})");
                $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
                foreach ($columns as $col) {
                    if (($col['name'] ?? null) === $column) {
                        return true;
                    }
                }
                return false;
            }

            $stmt = $pdo->query("SELECT {$column} FROM {$table} WHERE 1=0");
            return $stmt !== false;
        } catch (\Throwable) {
            return false;
        }
    }
}
