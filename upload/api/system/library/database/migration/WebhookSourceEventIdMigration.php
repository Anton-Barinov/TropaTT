<?php
declare(strict_types=1);

namespace Api\System\Library\Database\Migration;

use Api\System\Library\Database\IndexHelper;
use PDO;

/** Add an idempotency key for transactional domain-event deliveries. */
final class WebhookSourceEventIdMigration implements MigrationInterface
{
    public function key(): string
    {
        return '20261026_000001_webhook_source_event_id';
    }

    public function description(): string
    {
        return 'Make webhook outbox inserts idempotent per subscription and source event';
    }

    public function up(PDO $pdo, string $driver): void
    {
        if (!$this->tableExists($pdo, $driver, 'webhook_deliveries')) {
            return;
        }

        if (!$this->columnExists($pdo, $driver, 'webhook_deliveries', 'source_event_id')) {
            $sql = $driver === 'sqlsrv'
                ? 'ALTER TABLE webhook_deliveries ADD source_event_id VARCHAR(64) NULL'
                : 'ALTER TABLE webhook_deliveries ADD COLUMN source_event_id VARCHAR(64) NULL';
            $pdo->exec($sql);
        }

        if (!IndexHelper::indexExists($pdo, $driver, 'webhook_deliveries', 'uq_webhook_source_event')) {
            try {
                $sql = $driver === 'sqlsrv'
                    ? 'CREATE UNIQUE INDEX uq_webhook_source_event ON webhook_deliveries(webhook_id, source_event_id) WHERE source_event_id IS NOT NULL'
                    : 'CREATE UNIQUE INDEX uq_webhook_source_event ON webhook_deliveries(webhook_id, source_event_id)';
                $pdo->exec($sql);
            } catch (\Throwable $e) {
                if (!IndexHelper::indexExists($pdo, $driver, 'webhook_deliveries', 'uq_webhook_source_event')) {
                    throw $e;
                }
            }
        }
    }

    private function tableExists(PDO $pdo, string $driver, string $table): bool
    {
        if ($driver === 'sqlite') {
            $stmt = $pdo->prepare("SELECT 1 FROM sqlite_master WHERE type='table' AND name = :name LIMIT 1");
            $stmt->execute(['name' => $table]);
            return (bool)$stmt->fetchColumn();
        }
        if ($driver === 'sqlsrv') {
            $stmt = $pdo->prepare('SELECT 1 FROM sys.tables WHERE name = :name');
            $stmt->execute(['name' => $table]);
            return (bool)$stmt->fetchColumn();
        }
        $stmt = $pdo->prepare('SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = :name LIMIT 1');
        $stmt->execute(['name' => $table]);
        return (bool)$stmt->fetchColumn();
    }

    private function columnExists(PDO $pdo, string $driver, string $table, string $column): bool
    {
        if ($driver === 'sqlite') {
            foreach ($pdo->query('PRAGMA table_info(' . $table . ')')->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
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
        $stmt = $pdo->prepare('SELECT 1 FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = :table_name AND column_name = :column_name LIMIT 1');
        $stmt->execute(['table_name' => $table, 'column_name' => $column]);
        return (bool)$stmt->fetchColumn();
    }
}
