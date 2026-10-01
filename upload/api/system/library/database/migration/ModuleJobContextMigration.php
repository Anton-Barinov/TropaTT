<?php
declare(strict_types=1);

namespace Api\System\Library\Database\Migration;

use Api\System\Library\Database\IndexHelper;
use PDO;
use RuntimeException;

/** Persist workspace and execution metadata for queued module jobs. */
final class ModuleJobContextMigration implements MigrationInterface
{
    public function key(): string
    {
        return '20261001_000001_module_job_contexts';
    }

    public function description(): string
    {
        return 'Create workspace context and idempotency storage for module jobs';
    }

    public function up(PDO $pdo, string $driver): void
    {
        if (!in_array($driver, ['mysql', 'sqlite'], true)) {
            throw new RuntimeException('Module job context migration supports MySQL and SQLite only');
        }

        if ($driver === 'mysql') {
            $pdo->exec("CREATE TABLE IF NOT EXISTS module_job_contexts (
                job_id INT NOT NULL,
                module_name VARCHAR(190) NOT NULL,
                organization_id INT NOT NULL,
                organization_public_id VARCHAR(64) DEFAULT NULL,
                actor_public_id VARCHAR(64) DEFAULT NULL,
                source VARCHAR(32) NOT NULL,
                correlation_id VARCHAR(64) NOT NULL,
                idempotency_key VARCHAR(190) DEFAULT NULL,
                payload_json LONGTEXT NOT NULL,
                last_error LONGTEXT DEFAULT NULL,
                claimed_at DATETIME DEFAULT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (job_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
        } else {
            $pdo->exec("CREATE TABLE IF NOT EXISTS module_job_contexts (
                job_id INTEGER PRIMARY KEY,
                module_name TEXT NOT NULL,
                organization_id INTEGER NOT NULL,
                organization_public_id VARCHAR(64) DEFAULT NULL,
                actor_public_id VARCHAR(64) DEFAULT NULL,
                source VARCHAR(32) NOT NULL,
                correlation_id VARCHAR(64) NOT NULL,
                idempotency_key TEXT DEFAULT NULL,
                payload_json TEXT NOT NULL,
                last_error TEXT DEFAULT NULL,
                claimed_at DATETIME DEFAULT NULL,
                created_at DATETIME NOT NULL DEFAULT (datetime('now'))
            )");
        }

        IndexHelper::createIndexIfNotExists($pdo, 'module_job_contexts', 'idx_module_job_scope_key', 'module_name, organization_id, idempotency_key', true, $driver);
        IndexHelper::createIndexIfNotExists($pdo, 'module_job_contexts', 'idx_module_job_scope', 'organization_id, job_id', false, $driver);

        if (!IndexHelper::indexExists($pdo, $driver, 'module_job_contexts', 'idx_module_job_scope_key')
            || !IndexHelper::indexExists($pdo, $driver, 'module_job_contexts', 'idx_module_job_scope')) {
            throw new RuntimeException('Failed to create module job context indexes');
        }
    }
}
