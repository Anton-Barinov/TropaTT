<?php
declare(strict_types=1);

namespace Api\System\Library\Database\Migration;

use Api\System\Library\Database\IndexHelper;
use PDO;

/**
 * Migration: Harden core idempotency storage for workspace/body binding,
 * atomic in-flight claims and documented retention.
 */
final class IdempotencyClaimHardeningMigration implements MigrationInterface
{
    public function key(): string
    {
        return '20261013_000001_idempotency_claim_hardening';
    }

    public function description(): string
    {
        return 'Idempotency keys: actor/organization/request binding, atomic in-flight claims, retention columns';
    }

    public function up(PDO $pdo, string $driver): void
    {
        IndexHelper::addColumnIfNotExists($pdo, 'idempotency_keys', 'actor_public_id', 'VARCHAR(64) NULL', $driver);
        IndexHelper::addColumnIfNotExists($pdo, 'idempotency_keys', 'organization_public_id', 'VARCHAR(64) NULL', $driver);
        IndexHelper::addColumnIfNotExists($pdo, 'idempotency_keys', 'request_hash', 'VARCHAR(255) NULL', $driver);
        IndexHelper::addColumnIfNotExists($pdo, 'idempotency_keys', 'status', "VARCHAR(16) NOT NULL DEFAULT 'completed'", $driver);
        IndexHelper::addColumnIfNotExists($pdo, 'idempotency_keys', 'completed_at', 'DATETIME NULL', $driver);

        $this->deduplicateKeyHashes($pdo, $driver);

        IndexHelper::createIndexIfNotExists($pdo, 'idempotency_keys', 'uq_idempotency_keys_key_hash', 'key_hash', true, $driver);
        IndexHelper::createIndexIfNotExists($pdo, 'idempotency_keys', 'idx_idempotency_keys_created_at', 'created_at', false, $driver);
    }

    private function deduplicateKeyHashes(PDO $pdo, string $driver): void
    {
        if ($driver === 'sqlite') {
            $pdo->exec('DELETE FROM idempotency_keys WHERE id NOT IN (SELECT MAX(id) FROM idempotency_keys GROUP BY key_hash)');
            return;
        }

        $pdo->exec('DELETE t1 FROM idempotency_keys t1 INNER JOIN idempotency_keys t2 ON t1.key_hash = t2.key_hash AND t1.id < t2.id');
    }
}
