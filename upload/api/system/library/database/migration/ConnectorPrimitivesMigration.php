<?php
declare(strict_types=1);

namespace Api\System\Library\Database\Migration;

use Api\System\Library\Database\IndexHelper;
use PDO;
use RuntimeException;

/**
 * Migration for safe connector primitives:
 * - connector_credentials (encrypted server-side secrets scoped by module and workspace)
 * - connector_idempotency (re-delivery deduplication and receipt store)
 */
final class ConnectorPrimitivesMigration implements MigrationInterface
{
    public function key(): string
    {
        return '20261002_000001_connector_primitives';
    }

    public function description(): string
    {
        return 'Create connector credentials and idempotency tables';
    }

    public function up(PDO $pdo, string $driver): void
    {
        if (!in_array($driver, ['mysql', 'sqlite'], true)) {
            throw new RuntimeException('Connector primitives migration supports MySQL and SQLite only');
        }

        if ($driver === 'mysql') {
            $pdo->exec("CREATE TABLE IF NOT EXISTS connector_credentials (
                id INT NOT NULL AUTO_INCREMENT,
                module_name VARCHAR(190) NOT NULL,
                organization_id INT NOT NULL,
                key_name VARCHAR(64) NOT NULL,
                encrypted_value LONGTEXT NOT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

            $pdo->exec("CREATE TABLE IF NOT EXISTS connector_idempotency (
                id INT NOT NULL AUTO_INCREMENT,
                module_name VARCHAR(190) NOT NULL,
                organization_id INT NOT NULL,
                source VARCHAR(64) NOT NULL,
                idempotency_key VARCHAR(190) NOT NULL,
                status VARCHAR(32) NOT NULL DEFAULT 'processing',
                request_hash VARCHAR(64) DEFAULT NULL,
                response_code INT NOT NULL DEFAULT 200,
                response_payload LONGTEXT DEFAULT NULL,
                resource_type VARCHAR(64) DEFAULT NULL,
                resource_public_id VARCHAR(64) DEFAULT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                expires_at DATETIME DEFAULT NULL,
                PRIMARY KEY (id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
        } else {
            $pdo->exec("CREATE TABLE IF NOT EXISTS connector_credentials (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                module_name TEXT NOT NULL,
                organization_id INTEGER NOT NULL,
                key_name TEXT NOT NULL,
                encrypted_value TEXT NOT NULL,
                created_at DATETIME NOT NULL DEFAULT (datetime('now')),
                updated_at DATETIME NOT NULL DEFAULT (datetime('now'))
            )");

            $pdo->exec("CREATE TABLE IF NOT EXISTS connector_idempotency (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                module_name TEXT NOT NULL,
                organization_id INTEGER NOT NULL,
                source TEXT NOT NULL,
                idempotency_key TEXT NOT NULL,
                status TEXT NOT NULL DEFAULT 'processing',
                request_hash TEXT DEFAULT NULL,
                response_code INTEGER NOT NULL DEFAULT 200,
                response_payload TEXT DEFAULT NULL,
                resource_type TEXT DEFAULT NULL,
                resource_public_id TEXT DEFAULT NULL,
                created_at DATETIME NOT NULL DEFAULT (datetime('now')),
                updated_at DATETIME NOT NULL DEFAULT (datetime('now')),
                expires_at DATETIME DEFAULT NULL
            )");
        }

        IndexHelper::createIndexIfNotExists($pdo, 'connector_credentials', 'idx_connector_cred_unique', 'module_name, organization_id, key_name', true, $driver);
        IndexHelper::createIndexIfNotExists($pdo, 'connector_credentials', 'idx_connector_cred_org', 'organization_id, module_name', false, $driver);

        IndexHelper::createIndexIfNotExists($pdo, 'connector_idempotency', 'idx_connector_idemp_unique', 'module_name, organization_id, source, idempotency_key', true, $driver);
        IndexHelper::createIndexIfNotExists($pdo, 'connector_idempotency', 'idx_connector_idemp_org', 'organization_id, status', false, $driver);

        if (!IndexHelper::indexExists($pdo, $driver, 'connector_credentials', 'idx_connector_cred_unique')
            || !IndexHelper::indexExists($pdo, $driver, 'connector_idempotency', 'idx_connector_idemp_unique')) {
            throw new RuntimeException('Failed to create connector primitives indexes');
        }
    }
}
