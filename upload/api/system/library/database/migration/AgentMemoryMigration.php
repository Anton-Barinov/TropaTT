<?php
declare(strict_types=1);

namespace Api\System\Library\Database\Migration;

use Api\System\Library\Database\IndexHelper;
use PDO;

final class AgentMemoryMigration implements MigrationInterface
{
    public function key(): string
    {
        return '20260911_000001_agent_memory';
    }

    public function description(): string
    {
        return 'Create agent_memory table for autonomous agent scratchpad and persistent state';
    }

    public function up(PDO $pdo, string $driver): void
    {
        if ($driver === 'mysql') {
            $pdo->exec('CREATE TABLE IF NOT EXISTS agent_memory (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                public_id VARCHAR(64) NOT NULL,
                scope VARCHAR(64) NOT NULL DEFAULT \'global\',
                key_name VARCHAR(190) NOT NULL,
                value_text LONGTEXT NOT NULL,
                value_type VARCHAR(32) NOT NULL DEFAULT \'string\',
                owner_user_id BIGINT UNSIGNED NULL,
                metadata_json JSON NULL,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                PRIMARY KEY (id),
                UNIQUE KEY uq_agent_memory_public_id (public_id),
                UNIQUE KEY uq_agent_memory_scope_key (scope, key_name),
                KEY idx_agent_memory_scope (scope),
                KEY idx_agent_memory_owner (owner_user_id),
                KEY idx_agent_memory_updated (updated_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        } else {
            $pdo->exec('CREATE TABLE IF NOT EXISTS agent_memory (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                public_id VARCHAR(64) NOT NULL,
                scope VARCHAR(64) NOT NULL DEFAULT \'global\',
                key_name VARCHAR(190) NOT NULL,
                value_text TEXT NOT NULL,
                value_type VARCHAR(32) NOT NULL DEFAULT \'string\',
                owner_user_id INTEGER NULL,
                metadata_json TEXT NULL,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL
            )');
            IndexHelper::createIndexIfNotExists($pdo, 'agent_memory', 'uq_agent_memory_public_id', 'public_id', true);
            IndexHelper::createIndexIfNotExists($pdo, 'agent_memory', 'uq_agent_memory_scope_key', 'scope, key_name', true);
            IndexHelper::createIndexIfNotExists($pdo, 'agent_memory', 'idx_agent_memory_scope', 'scope');
            IndexHelper::createIndexIfNotExists($pdo, 'agent_memory', 'idx_agent_memory_owner', 'owner_user_id');
            IndexHelper::createIndexIfNotExists($pdo, 'agent_memory', 'idx_agent_memory_updated', 'updated_at');
        }
    }
}
