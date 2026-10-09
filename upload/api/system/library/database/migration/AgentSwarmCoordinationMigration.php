<?php
declare(strict_types=1);

namespace Api\System\Library\Database\Migration;

use PDO;
use RuntimeException;

/** Run-scoped source coordination. It does not fence ordinary CRM task writes. */
final class AgentSwarmCoordinationMigration implements MigrationInterface
{
    public function key(): string { return '20261009_000001_agent_swarm_coordination'; }
    public function description(): string { return 'Run-scoped agent coordination, source path claims and events'; }

    public function up(PDO $pdo, string $driver): void
    {
        if ($driver === 'sqlite') {
            $pdo->exec('CREATE TABLE IF NOT EXISTS agent_swarm_runs (
                id INTEGER PRIMARY KEY AUTOINCREMENT, organization_id INTEGER NOT NULL,
                swarm_run_id TEXT COLLATE BINARY NOT NULL, parent_task_public_id TEXT COLLATE BINARY NOT NULL,
                project_public_id TEXT COLLATE BINARY NULL, base_sha TEXT COLLATE BINARY NOT NULL,
                created_by_user_id INTEGER NOT NULL, parent_agent_id TEXT COLLATE BINARY NOT NULL,
                parent_lease_run_id TEXT COLLATE BINARY NOT NULL, parent_generation INTEGER NOT NULL,
                payload_hash TEXT COLLATE BINARY NOT NULL, status TEXT NOT NULL, created_at DATETIME NOT NULL,
                UNIQUE (organization_id, swarm_run_id)
            )');
            $pdo->exec('CREATE TABLE IF NOT EXISTS agent_swarm_participants (
                organization_id INTEGER NOT NULL, swarm_run_id TEXT COLLATE BINARY NOT NULL,
                task_public_id TEXT COLLATE BINARY NOT NULL, agent_id TEXT COLLATE BINARY NOT NULL,
                registered_by_user_id INTEGER NOT NULL, created_at DATETIME NOT NULL,
                PRIMARY KEY (organization_id, swarm_run_id, task_public_id),
                UNIQUE (organization_id, swarm_run_id, agent_id)
            )');
            $pdo->exec('CREATE TABLE IF NOT EXISTS agent_source_claim_scopes (
                organization_id INTEGER PRIMARY KEY, created_at DATETIME NOT NULL
            )');
            $pdo->exec('CREATE TABLE IF NOT EXISTS agent_source_path_claims (
                id INTEGER PRIMARY KEY AUTOINCREMENT, organization_id INTEGER NOT NULL,
                path_hash TEXT COLLATE BINARY NOT NULL, normalized_path TEXT NOT NULL,
                swarm_run_id TEXT COLLATE BINARY NOT NULL, base_sha TEXT COLLATE BINARY NOT NULL,
                task_public_id TEXT COLLATE BINARY NOT NULL, agent_id TEXT COLLATE BINARY NOT NULL,
                lease_run_id TEXT COLLATE BINARY NOT NULL, lease_generation INTEGER NOT NULL,
                owner_user_id INTEGER NOT NULL, created_at DATETIME NOT NULL,
                UNIQUE (organization_id, path_hash)
            )');
            $pdo->exec('CREATE INDEX IF NOT EXISTS idx_agent_source_claim_owner ON agent_source_path_claims (organization_id, swarm_run_id, task_public_id)');
            $pdo->exec('CREATE TABLE IF NOT EXISTS agent_swarm_events (
                id INTEGER PRIMARY KEY AUTOINCREMENT, organization_id INTEGER NOT NULL,
                swarm_run_id TEXT COLLATE BINARY NOT NULL, event_id TEXT COLLATE BINARY NOT NULL,
                operation_id TEXT COLLATE BINARY NOT NULL, event_kind TEXT COLLATE BINARY NOT NULL,
                stage TEXT COLLATE BINARY NOT NULL, owner_user_id INTEGER NOT NULL,
                agent_id TEXT COLLATE BINARY NOT NULL, task_public_id TEXT COLLATE BINARY NOT NULL,
                lease_run_id TEXT COLLATE BINARY NOT NULL, lease_generation INTEGER NOT NULL,
                body TEXT NOT NULL, payload_hash TEXT COLLATE BINARY NOT NULL, created_at DATETIME NOT NULL,
                UNIQUE (organization_id, swarm_run_id, event_id)
            )');
            return;
        }
        if ($driver !== 'mysql') { throw new RuntimeException('Agent swarm coordination requires MySQL'); }
        $pdo->exec("CREATE TABLE IF NOT EXISTS agent_swarm_runs (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            organization_id BIGINT UNSIGNED NOT NULL,
            swarm_run_id CHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
            parent_task_public_id VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
            project_public_id VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NULL,
            base_sha VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
            created_by_user_id BIGINT UNSIGNED NOT NULL,
            parent_agent_id VARCHAR(96) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
            parent_lease_run_id VARCHAR(96) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
            parent_generation BIGINT UNSIGNED NOT NULL,
            payload_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
            status VARCHAR(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
            created_at DATETIME NOT NULL,
            UNIQUE KEY uq_agent_swarm_run (organization_id, swarm_run_id),
            KEY idx_agent_swarm_parent (organization_id, parent_task_public_id, created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        $pdo->exec("CREATE TABLE IF NOT EXISTS agent_swarm_participants (
            organization_id BIGINT UNSIGNED NOT NULL,
            swarm_run_id CHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
            task_public_id VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
            agent_id VARCHAR(96) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
            registered_by_user_id BIGINT UNSIGNED NOT NULL,
            created_at DATETIME NOT NULL,
            PRIMARY KEY (organization_id, swarm_run_id, task_public_id),
            UNIQUE KEY uq_agent_swarm_participant_agent (organization_id, swarm_run_id, agent_id),
            KEY idx_agent_swarm_participant_task (organization_id, task_public_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        // Short-lived organization mutex for atomic overlap checks; it does not require one SHA across runs.
        $pdo->exec("CREATE TABLE IF NOT EXISTS agent_source_claim_scopes (
            organization_id BIGINT UNSIGNED NOT NULL PRIMARY KEY,
            created_at DATETIME NOT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        $pdo->exec("CREATE TABLE IF NOT EXISTS agent_source_path_claims (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            organization_id BIGINT UNSIGNED NOT NULL,
            path_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
            normalized_path VARCHAR(512) NOT NULL,
            swarm_run_id CHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
            base_sha VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
            task_public_id VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
            agent_id VARCHAR(96) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
            lease_run_id VARCHAR(96) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
            lease_generation BIGINT UNSIGNED NOT NULL,
            owner_user_id BIGINT UNSIGNED NOT NULL,
            created_at DATETIME NOT NULL,
            UNIQUE KEY uq_agent_source_path (organization_id, path_hash),
            KEY idx_agent_source_claim_run (organization_id, swarm_run_id, task_public_id),
            KEY idx_agent_source_claim_lease (organization_id, task_public_id, lease_generation)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        $pdo->exec("CREATE TABLE IF NOT EXISTS agent_swarm_events (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            organization_id BIGINT UNSIGNED NOT NULL,
            swarm_run_id CHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
            event_id CHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
            operation_id VARCHAR(96) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
            event_kind VARCHAR(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
            stage VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
            owner_user_id BIGINT UNSIGNED NOT NULL,
            agent_id VARCHAR(96) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
            task_public_id VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
            lease_run_id VARCHAR(96) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
            lease_generation BIGINT UNSIGNED NOT NULL,
            body TEXT NOT NULL,
            payload_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
            created_at DATETIME NOT NULL,
            UNIQUE KEY uq_agent_swarm_event (organization_id, swarm_run_id, event_id),
            KEY idx_agent_swarm_event_cursor (organization_id, swarm_run_id, id),
            KEY idx_agent_swarm_event_task (organization_id, swarm_run_id, task_public_id, id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }
}
