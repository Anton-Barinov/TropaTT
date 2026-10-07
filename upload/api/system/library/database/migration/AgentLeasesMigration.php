<?php
declare(strict_types=1);

namespace Api\System\Library\Database\Migration;

use PDO;
use RuntimeException;

final class AgentLeasesMigration implements MigrationInterface
{
    public function key(): string
    {
        return '20261007_000001_agent_leases';
    }

    public function description(): string
    {
        return 'Central workspace-scoped agent leases with fencing generations';
    }

    public function up(PDO $pdo, string $driver): void
    {
        // SQLite is used only by the local unit harness, not an install target.
        if ($driver === 'sqlite') {
            $pdo->exec('CREATE TABLE IF NOT EXISTS agent_leases (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                organization_id INTEGER NOT NULL,
                resource_key TEXT COLLATE BINARY NOT NULL,
                owner_user_id INTEGER NOT NULL,
                agent_id TEXT COLLATE BINARY NOT NULL,
                run_id TEXT COLLATE BINARY NOT NULL,
                token_hash TEXT COLLATE BINARY NOT NULL,
                generation INTEGER NOT NULL,
                expires_at INTEGER NOT NULL,
                heartbeat_at INTEGER NOT NULL,
                UNIQUE (organization_id, resource_key)
            )');
            $pdo->exec('CREATE INDEX IF NOT EXISTS idx_agent_lease_expiry ON agent_leases (expires_at)');
            $pdo->exec('CREATE TABLE IF NOT EXISTS agent_lease_runs (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                organization_id INTEGER NOT NULL, resource_key TEXT COLLATE BINARY NOT NULL,
                owner_user_id INTEGER NOT NULL, agent_id TEXT COLLATE BINARY NOT NULL,
                run_id TEXT COLLATE BINARY NOT NULL, generation INTEGER NOT NULL, claimed_at INTEGER NOT NULL,
                UNIQUE (organization_id, resource_key, owner_user_id, agent_id, run_id)
            )');
            return;
        }
        if ($driver !== 'mysql') {
            throw new RuntimeException('Agent leases require MySQL');
        }
        $pdo->exec("CREATE TABLE IF NOT EXISTS agent_leases (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            organization_id BIGINT UNSIGNED NOT NULL,
            resource_key VARCHAR(96) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
            owner_user_id BIGINT UNSIGNED NOT NULL,
            agent_id VARCHAR(96) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
            run_id VARCHAR(96) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
            token_hash VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
            generation BIGINT UNSIGNED NOT NULL,
            expires_at BIGINT UNSIGNED NOT NULL,
            heartbeat_at BIGINT UNSIGNED NOT NULL,
            UNIQUE KEY uq_agent_lease_resource (organization_id, resource_key),
            KEY idx_agent_lease_expiry (expires_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        $pdo->exec("CREATE TABLE IF NOT EXISTS agent_lease_runs (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            organization_id BIGINT UNSIGNED NOT NULL,
            resource_key VARCHAR(96) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
            owner_user_id BIGINT UNSIGNED NOT NULL,
            agent_id VARCHAR(96) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
            run_id VARCHAR(96) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
            generation BIGINT UNSIGNED NOT NULL,
            claimed_at BIGINT UNSIGNED NOT NULL,
            UNIQUE KEY uq_agent_lease_run (organization_id, resource_key, owner_user_id, agent_id, run_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }
}
