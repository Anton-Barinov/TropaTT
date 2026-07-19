<?php
declare(strict_types=1);

namespace Api\System\Library\Database\Migration;

use PDO;

final class WebhookDeliveriesQueueRuntimeMigration implements MigrationInterface
{
    public function key(): string
    {
        return '20260506_000042_webhook_deliveries_queue_runtime';
    }

    public function description(): string
    {
        return 'Add queue runtime columns for async webhook deliveries';
    }

    public function up(PDO $pdo, string $driver): void
    {
        if (!$this->tableExists($pdo, $driver, 'webhook_deliveries')) {
            return;
        }

        $text = $driver === 'sqlsrv' ? 'NVARCHAR(MAX)' : 'TEXT';

        $this->ensureColumn($pdo, $driver, 'webhook_deliveries', 'payload_json', $text . ' NULL');
        $this->ensureColumn($pdo, $driver, 'webhook_deliveries', 'signature', 'VARCHAR(255) NULL');
        $this->ensureColumn($pdo, $driver, 'webhook_deliveries', 'attempts', 'INTEGER NOT NULL DEFAULT 0');
        $this->ensureColumn($pdo, $driver, 'webhook_deliveries', 'next_run_at', 'DATETIME NULL');
        $this->ensureColumn($pdo, $driver, 'webhook_deliveries', 'locked_at', 'DATETIME NULL');
        $this->ensureColumn($pdo, $driver, 'webhook_deliveries', 'last_error', $text . ' NULL');
        $this->ensureColumn($pdo, $driver, 'webhook_deliveries', 'dead_letter', 'INTEGER NOT NULL DEFAULT 0');
        $this->ensureColumn($pdo, $driver, 'webhook_deliveries', 'updated_at', 'DATETIME NULL');

        $this->createIndexIfMissing($pdo, 'webhook_deliveries', 'idx_webhook_deliveries_queue_runnable', 'status, dead_letter, next_run_at, locked_at, created_at');
        $this->createIndexIfMissing($pdo, 'webhook_deliveries', 'idx_webhook_deliveries_attempts', 'attempts, updated_at');
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
            if ($driver === 'sqlite') {
                $stmt = $pdo->prepare("SELECT name FROM sqlite_master WHERE type='table' AND name = :name LIMIT 1");
                $stmt->execute(['name' => $table]);
                return (bool)$stmt->fetchColumn();
            }

            if ($driver === 'sqlsrv') {
                $stmt = $pdo->prepare('SELECT 1 FROM sys.tables WHERE name = :name');
                $stmt->execute(['name' => $table]);
                return (bool)$stmt->fetchColumn();
            }

            $stmt = $pdo->prepare('SELECT 1 FROM information_schema.tables WHERE table_name = :name LIMIT 1');
            $stmt->execute(['name' => $table]);
            return (bool)$stmt->fetchColumn();
        } catch (\Throwable $e) {
            error_log('[WebhookDeliveriesQueueRuntimeMigration::tableExists] ' . $e->getMessage());
            return false;
        }
    }

    private function columnExists(PDO $pdo, string $driver, string $table, string $column): bool
    {
        try {
            if ($driver === 'sqlite') {
                $rows = $pdo->query('PRAGMA table_info(' . $table . ')')->fetchAll() ?: [];
                foreach ($rows as $row) {
                    if ((string)($row['name'] ?? '') === $column) {
                        return true;
                    }
                }
                return false;
            }

            if ($driver === 'sqlsrv') {
                $stmt = $pdo->prepare('SELECT 1 FROM sys.columns WHERE object_id = OBJECT_ID(:table_name) AND name = :column_name');
                $stmt->execute(['table_name' => $table, 'column_name' => $column]);
                return (bool)$stmt->fetchColumn();
            }

            $stmt = $pdo->prepare('SELECT 1 FROM information_schema.columns WHERE table_name = :table_name AND column_name = :column_name LIMIT 1');
            $stmt->execute(['table_name' => $table, 'column_name' => $column]);
            return (bool)$stmt->fetchColumn();
        } catch (\Throwable $e) {
            error_log('[WebhookDeliveriesQueueRuntimeMigration::columnExists] ' . $e->getMessage());
            return false;
        }
    }

    private function createIndexIfMissing(PDO $pdo, string $table, string $index, string $columns): void
    {
        try {
            $pdo->exec(sprintf('CREATE INDEX IF NOT EXISTS %s ON %s(%s)', $index, $table, $columns));
            return;
        } catch (\Throwable $e) {
            error_log('[WebhookDeliveriesQueueRuntimeMigration::createIndexIfMissing] CREATE INDEX: ' . $e->getMessage());
        }

        try {
            $pdo->exec(sprintf('CREATE INDEX %s ON %s(%s)', $index, $table, $columns));
        } catch (\Throwable $e) {
            error_log('[WebhookDeliveriesQueueRuntimeMigration::createIndexIfMissing] CREATE INDEX: ' . $e->getMessage());
        }
    }
}
