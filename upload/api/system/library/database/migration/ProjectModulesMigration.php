<?php
declare(strict_types=1);

namespace Api\System\Library\Database\Migration;

use Api\System\Library\Database\IndexHelper;
use PDO;

final class ProjectModulesMigration implements MigrationInterface
{
    public function key(): string
    {
        return '20260616_000007_project_modules';
    }

    public function description(): string
    {
        return 'Create project modules, module tasks, members and links';
    }

    public function up(PDO $pdo, string $driver): void
    {
        if ($driver === 'mysql') {
            $pdo->exec('CREATE TABLE IF NOT EXISTS project_modules (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                public_id VARCHAR(64) NOT NULL,

                project_id BIGINT UNSIGNED NOT NULL,

                title VARCHAR(255) NOT NULL,
                description TEXT NULL,

                status VARCHAR(32) NOT NULL DEFAULT \'planned\',

                lead_user_id BIGINT UNSIGNED NULL,

                start_at DATETIME NULL,
                target_at DATETIME NULL,
                completed_at DATETIME NULL,

                color VARCHAR(32) NULL,
                icon VARCHAR(64) NULL,

                sort_order INT NOT NULL DEFAULT 65535,

                meta_json JSON NULL,
                progress_snapshot_json JSON NULL,

                row_version INT NOT NULL DEFAULT 1,

                created_by_user_id BIGINT UNSIGNED NOT NULL,
                updated_by_user_id BIGINT UNSIGNED NULL,

                archived_at DATETIME NULL,
                deleted_at DATETIME NULL,

                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,

                PRIMARY KEY (id),

                UNIQUE KEY uq_project_modules_public_id (public_id),

                KEY idx_project_modules_project_status (project_id, status),
                KEY idx_project_modules_project_sort (project_id, sort_order),
                KEY idx_project_modules_lead_status (lead_user_id, status),
                KEY idx_project_modules_target_at (target_at),
                KEY idx_project_modules_archived_at (archived_at),
                KEY idx_project_modules_deleted_at (deleted_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');

            $pdo->exec('CREATE TABLE IF NOT EXISTS project_module_tasks (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                public_id VARCHAR(64) NOT NULL,

                module_id BIGINT UNSIGNED NOT NULL,
                task_id BIGINT UNSIGNED NOT NULL,

                added_by_user_id BIGINT UNSIGNED NOT NULL,
                added_at DATETIME NOT NULL,

                removed_by_user_id BIGINT UNSIGNED NULL,
                removed_at DATETIME NULL,

                sort_order INT NOT NULL DEFAULT 65535,

                active_key VARCHAR(191) NULL,

                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                deleted_at DATETIME NULL,

                PRIMARY KEY (id),

                UNIQUE KEY uq_project_module_tasks_public_id (public_id),
                UNIQUE KEY uq_project_module_tasks_active_key (active_key),

                KEY idx_project_module_tasks_module_active (module_id, deleted_at),
                KEY idx_project_module_tasks_task_active (task_id, deleted_at),
                KEY idx_project_module_tasks_added_by (added_by_user_id, added_at),
                KEY idx_project_module_tasks_deleted_at (deleted_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');

            $pdo->exec('CREATE TABLE IF NOT EXISTS project_module_members (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                public_id VARCHAR(64) NOT NULL,

                module_id BIGINT UNSIGNED NOT NULL,
                user_id BIGINT UNSIGNED NOT NULL,

                role_code VARCHAR(64) NOT NULL DEFAULT \'member\',

                added_by_user_id BIGINT UNSIGNED NOT NULL,
                added_at DATETIME NOT NULL,

                removed_by_user_id BIGINT UNSIGNED NULL,
                removed_at DATETIME NULL,

                active_key VARCHAR(191) NULL,

                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                deleted_at DATETIME NULL,

                PRIMARY KEY (id),

                UNIQUE KEY uq_project_module_members_public_id (public_id),
                UNIQUE KEY uq_project_module_members_active_key (active_key),

                KEY idx_project_module_members_module_active (module_id, deleted_at),
                KEY idx_project_module_members_user_active (user_id, deleted_at),
                KEY idx_project_module_members_role (role_code),
                KEY idx_project_module_members_deleted_at (deleted_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');

            $pdo->exec('CREATE TABLE IF NOT EXISTS project_module_links (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                public_id VARCHAR(64) NOT NULL,

                module_id BIGINT UNSIGNED NOT NULL,

                title VARCHAR(255) NOT NULL,
                url VARCHAR(2048) NOT NULL,
                link_type VARCHAR(64) NOT NULL DEFAULT \'other\',

                created_by_user_id BIGINT UNSIGNED NOT NULL,

                sort_order INT NOT NULL DEFAULT 65535,

                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                deleted_at DATETIME NULL,

                PRIMARY KEY (id),

                UNIQUE KEY uq_project_module_links_public_id (public_id),

                KEY idx_project_module_links_module_active (module_id, deleted_at),
                KEY idx_project_module_links_type (link_type),
                KEY idx_project_module_links_deleted_at (deleted_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        } else {
            // SQLite fallback
            $pdo->exec('CREATE TABLE IF NOT EXISTS project_modules (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                public_id VARCHAR(64) NOT NULL,
                project_id INTEGER NOT NULL,
                title VARCHAR(255) NOT NULL,
                description TEXT NULL,
                status VARCHAR(32) NOT NULL DEFAULT \'planned\',
                lead_user_id INTEGER NULL,
                start_at DATETIME NULL,
                target_at DATETIME NULL,
                completed_at DATETIME NULL,
                color VARCHAR(32) NULL,
                icon VARCHAR(64) NULL,
                sort_order INT NOT NULL DEFAULT 65535,
                meta_json TEXT NULL,
                progress_snapshot_json TEXT NULL,
                row_version INT NOT NULL DEFAULT 1,
                created_by_user_id INTEGER NOT NULL,
                updated_by_user_id INTEGER NULL,
                archived_at DATETIME NULL,
                deleted_at DATETIME NULL,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL
            )');
            IndexHelper::createIndexIfNotExists($pdo, 'project_modules', 'uq_project_modules_public_id', 'public_id', true);
            IndexHelper::createIndexIfNotExists($pdo, 'project_modules', 'idx_project_modules_project_status', 'project_id, status');
            IndexHelper::createIndexIfNotExists($pdo, 'project_modules', 'idx_project_modules_project_sort', 'project_id, sort_order');
            IndexHelper::createIndexIfNotExists($pdo, 'project_modules', 'idx_project_modules_lead_status', 'lead_user_id, status');
            IndexHelper::createIndexIfNotExists($pdo, 'project_modules', 'idx_project_modules_target_at', 'target_at');
            IndexHelper::createIndexIfNotExists($pdo, 'project_modules', 'idx_project_modules_archived_at', 'archived_at');
            IndexHelper::createIndexIfNotExists($pdo, 'project_modules', 'idx_project_modules_deleted_at', 'deleted_at');

            $pdo->exec('CREATE TABLE IF NOT EXISTS project_module_tasks (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                public_id VARCHAR(64) NOT NULL,
                module_id INTEGER NOT NULL,
                task_id INTEGER NOT NULL,
                added_by_user_id INTEGER NOT NULL,
                added_at DATETIME NOT NULL,
                removed_by_user_id INTEGER NULL,
                removed_at DATETIME NULL,
                sort_order INT NOT NULL DEFAULT 65535,
                active_key VARCHAR(191) NULL,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                deleted_at DATETIME NULL
            )');
            IndexHelper::createIndexIfNotExists($pdo, 'project_module_tasks', 'uq_project_module_tasks_public_id', 'public_id', true);
            IndexHelper::createIndexIfNotExists($pdo, 'project_module_tasks', 'uq_project_module_tasks_active_key', 'active_key', true);
            IndexHelper::createIndexIfNotExists($pdo, 'project_module_tasks', 'idx_project_module_tasks_module_active', 'module_id, deleted_at');
            IndexHelper::createIndexIfNotExists($pdo, 'project_module_tasks', 'idx_project_module_tasks_task_active', 'task_id, deleted_at');

            $pdo->exec('CREATE TABLE IF NOT EXISTS project_module_members (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                public_id VARCHAR(64) NOT NULL,
                module_id INTEGER NOT NULL,
                user_id INTEGER NOT NULL,
                role_code VARCHAR(64) NOT NULL DEFAULT \'member\',
                added_by_user_id INTEGER NOT NULL,
                added_at DATETIME NOT NULL,
                removed_by_user_id INTEGER NULL,
                removed_at DATETIME NULL,
                active_key VARCHAR(191) NULL,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                deleted_at DATETIME NULL
            )');
            IndexHelper::createIndexIfNotExists($pdo, 'project_module_members', 'uq_project_module_members_public_id', 'public_id', true);
            IndexHelper::createIndexIfNotExists($pdo, 'project_module_members', 'uq_project_module_members_active_key', 'active_key', true);
            IndexHelper::createIndexIfNotExists($pdo, 'project_module_members', 'idx_project_module_members_module_active', 'module_id, deleted_at');
            IndexHelper::createIndexIfNotExists($pdo, 'project_module_members', 'idx_project_module_members_user_active', 'user_id, deleted_at');

            $pdo->exec('CREATE TABLE IF NOT EXISTS project_module_links (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                public_id VARCHAR(64) NOT NULL,
                module_id INTEGER NOT NULL,
                title VARCHAR(255) NOT NULL,
                url VARCHAR(2048) NOT NULL,
                link_type VARCHAR(64) NOT NULL DEFAULT \'other\',
                created_by_user_id INTEGER NOT NULL,
                sort_order INT NOT NULL DEFAULT 65535,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                deleted_at DATETIME NULL
            )');
            IndexHelper::createIndexIfNotExists($pdo, 'project_module_links', 'uq_project_module_links_public_id', 'public_id', true);
            IndexHelper::createIndexIfNotExists($pdo, 'project_module_links', 'idx_project_module_links_module_active', 'module_id, deleted_at');
            IndexHelper::createIndexIfNotExists($pdo, 'project_module_links', 'idx_project_module_links_type', 'link_type');
        }
    }
}
