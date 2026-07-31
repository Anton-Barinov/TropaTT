<?php
declare(strict_types=1);

namespace Api\System\Library\Database\Migration;

use PDO;

final class RecurringRuleTitleMigration implements MigrationInterface
{
    public function key(): string
    {
        return '20260607_000001_recurring_rule_title';
    }

    public function description(): string
    {
        return 'Add human-readable titles to recurring rules';
    }

    public function up(PDO $pdo, string $driver): void
    {
        if ($this->columnExists($pdo, $driver, 'recurring_rules', 'title')) {
            return;
        }

        $definition = $driver === 'sqlsrv' ? 'NVARCHAR(255) NULL' : 'VARCHAR(255) NULL';
        $sql = match ($driver) {
            'sqlsrv' => 'ALTER TABLE recurring_rules ADD title ' . $definition,
            default => 'ALTER TABLE recurring_rules ADD COLUMN title ' . $definition,
        };
        $pdo->exec($sql);
    }

    private function columnExists(PDO $pdo, string $driver, string $table, string $column): bool
    {
        try {
            if ($driver === 'mysql') {
                $stmt = $pdo->prepare('SELECT 1 FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = :table_name AND column_name = :column_name LIMIT 1');
                $stmt->execute(['table_name' => $table, 'column_name' => $column]);
                return $stmt->fetchColumn() !== false;
            }

            $rows = $pdo->query("PRAGMA table_info({$table})")->fetchAll() ?: [];
            foreach ($rows as $row) {
                if ((string)($row['name'] ?? '') === $column) {
                    return true;
                }
            }
        } catch (\Throwable $e) {
            error_log('[RecurringRuleTitleMigration::columnExists] ' . $e->getMessage());
            return false;
        }

        return false;
    }
}
