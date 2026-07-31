<?php
declare(strict_types=1);

namespace Api\System\Library\Database\Migration;

use PDO;

final class CompanyClientContactOwnershipMigration implements MigrationInterface
{
    public function key(): string
    {
        return '20260418_000004_company_client_contact_ownership';
    }

    public function description(): string
    {
        return 'Add created_by_user_id ownership columns for companies/clients/contacts and indexes';
    }

    public function up(PDO $pdo, string $driver): void
    {
        $this->ensureColumn($pdo, $driver, 'companies', 'created_by_user_id', 'INTEGER NULL');
        $this->ensureColumn($pdo, $driver, 'clients', 'created_by_user_id', 'INTEGER NULL');
        $this->ensureColumn($pdo, $driver, 'contacts', 'created_by_user_id', 'INTEGER NULL');

        try {
            $pdo->exec('CREATE INDEX IF NOT EXISTS idx_companies_created_by ON companies(created_by_user_id)');
        } catch (\Throwable $e) {
            error_log('[CompanyClientContactOwnershipMigration::up] CREATE INDEX: ' . $e->getMessage());
            // ignore unsupported IF NOT EXISTS on index creation
        }

        try {
            $pdo->exec('CREATE INDEX IF NOT EXISTS idx_clients_created_by ON clients(created_by_user_id)');
        } catch (\Throwable $e) {
            error_log('[CompanyClientContactOwnershipMigration::up] CREATE INDEX: ' . $e->getMessage());
            // ignore unsupported IF NOT EXISTS on index creation
        }

        try {
            $pdo->exec('CREATE INDEX IF NOT EXISTS idx_contacts_created_by ON contacts(created_by_user_id)');
        } catch (\Throwable $e) {
            error_log('[CompanyClientContactOwnershipMigration::up] CREATE INDEX: ' . $e->getMessage());
            // ignore unsupported IF NOT EXISTS on index creation
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
            error_log('[CompanyClientContactOwnershipMigration::columnExists] ' . $e->getMessage());
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
