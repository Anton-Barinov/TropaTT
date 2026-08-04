<?php
declare(strict_types=1);

namespace Api\System\Library\Database\Migration;

use Api\System\Library\Database\IndexHelper;
use PDO;

final class TaskRelationsV2Migration implements MigrationInterface
{
    public function key(): string
    {
        return '20260616_000003_task_relations_v2';
    }

    public function description(): string
    {
        return 'Create semantic task relations table';
    }

    public function up(PDO $pdo, string $driver): void
    {
        if ($driver === 'mysql') {
            $pdo->exec('CREATE TABLE IF NOT EXISTS task_relations_v2 (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                public_id VARCHAR(64) NOT NULL,

                source_task_id BIGINT UNSIGNED NOT NULL,
                target_task_id BIGINT UNSIGNED NOT NULL,

                relation_type VARCHAR(32) NOT NULL,
                active_key VARCHAR(191) NULL,

                note TEXT NULL,

                created_by_user_id BIGINT UNSIGNED NOT NULL,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                deleted_at DATETIME NULL,

                row_version INT NOT NULL DEFAULT 1,

                PRIMARY KEY (id),
                UNIQUE KEY uq_task_relations_v2_public_id (public_id),
                UNIQUE KEY uq_task_relations_v2_active_key (active_key),

                KEY idx_task_relations_v2_source (source_task_id, deleted_at),
                KEY idx_task_relations_v2_target (target_task_id, deleted_at),
                KEY idx_task_relations_v2_type (relation_type, deleted_at),
                KEY idx_task_relations_v2_created_by (created_by_user_id, created_at),
                KEY idx_task_relations_v2_deleted_at (deleted_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        } else {
            // SQLite fallback
            $pdo->exec('CREATE TABLE IF NOT EXISTS task_relations_v2 (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                public_id VARCHAR(64) NOT NULL,

                source_task_id INTEGER NOT NULL,
                target_task_id INTEGER NOT NULL,

                relation_type VARCHAR(32) NOT NULL,
                active_key VARCHAR(191) NULL,

                note TEXT NULL,

                created_by_user_id INTEGER NOT NULL,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                deleted_at DATETIME NULL,

                row_version INTEGER NOT NULL DEFAULT 1
            )');

            IndexHelper::createIndexIfNotExists($pdo, 'task_relations_v2', 'uq_task_relations_v2_public_id', 'public_id', true);
            IndexHelper::createIndexIfNotExists($pdo, 'task_relations_v2', 'uq_task_relations_v2_active_key', 'active_key', true);
            IndexHelper::createIndexIfNotExists($pdo, 'task_relations_v2', 'idx_task_relations_v2_source', 'source_task_id, deleted_at');
            IndexHelper::createIndexIfNotExists($pdo, 'task_relations_v2', 'idx_task_relations_v2_target', 'target_task_id, deleted_at');
            IndexHelper::createIndexIfNotExists($pdo, 'task_relations_v2', 'idx_task_relations_v2_type', 'relation_type, deleted_at');
            IndexHelper::createIndexIfNotExists($pdo, 'task_relations_v2', 'idx_task_relations_v2_created_by', 'created_by_user_id, created_at');
            IndexHelper::createIndexIfNotExists($pdo, 'task_relations_v2', 'idx_task_relations_v2_deleted_at', 'deleted_at');
        }
    }
}
