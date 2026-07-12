<?php
declare(strict_types=1);

namespace Api\System\Library\Database\Migration;

use PDO;

final class WorkCyclesMigration implements MigrationInterface
{
    public function key(): string
    {
        return '20260616_000006_work_cycles';
    }

    public function description(): string
    {
        return 'Create work cycles, cycle tasks and cycle snapshots';
    }

    public function up(PDO $pdo, string $driver): void
    {
        if ($driver === 'mysql') {
            $pdo->exec('CREATE TABLE IF NOT EXISTS work_cycles (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                public_id VARCHAR(64) NOT NULL,

                project_id BIGINT UNSIGNED NOT NULL,

                title VARCHAR(255) NOT NULL,
                description TEXT NULL,
                goal TEXT NULL,

                status VARCHAR(32) NOT NULL DEFAULT \'planned\',

                start_at DATETIME NULL,
                end_at DATETIME NULL,
                timezone VARCHAR(64) NULL,

                owner_user_id BIGINT UNSIGNED NULL,
                created_by_user_id BIGINT UNSIGNED NOT NULL,

                completed_by_user_id BIGINT UNSIGNED NULL,
                completed_at DATETIME NULL,

                archived_at DATETIME NULL,

                progress_snapshot_json JSON NULL,
                meta_json JSON NULL,

                sort_order INT NOT NULL DEFAULT 65535,
                row_version INT NOT NULL DEFAULT 1,

                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                deleted_at DATETIME NULL,

                PRIMARY KEY (id),
                UNIQUE KEY uq_work_cycles_public_id (public_id),

                KEY idx_work_cycles_project_status (project_id, status),
                KEY idx_work_cycles_project_dates (project_id, start_at, end_at),
                KEY idx_work_cycles_owner_status (owner_user_id, status),
                KEY idx_work_cycles_created_by (created_by_user_id, created_at),
                KEY idx_work_cycles_completed_at (completed_at),
                KEY idx_work_cycles_archived_at (archived_at),
                KEY idx_work_cycles_deleted_at (deleted_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');

            $pdo->exec('CREATE TABLE IF NOT EXISTS cycle_tasks (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                public_id VARCHAR(64) NOT NULL,

                cycle_id BIGINT UNSIGNED NOT NULL,
                task_id BIGINT UNSIGNED NOT NULL,

                active_key VARCHAR(191) NULL,

                added_by_user_id BIGINT UNSIGNED NOT NULL,
                added_at DATETIME NOT NULL,

                removed_by_user_id BIGINT UNSIGNED NULL,
                removed_at DATETIME NULL,

                sort_order INT NOT NULL DEFAULT 65535,

                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                deleted_at DATETIME NULL,

                PRIMARY KEY (id),
                UNIQUE KEY uq_cycle_tasks_public_id (public_id),
                UNIQUE KEY uq_cycle_tasks_active_key (active_key),

                KEY idx_cycle_tasks_cycle_active (cycle_id, deleted_at),
                KEY idx_cycle_tasks_task_active (task_id, deleted_at),
                KEY idx_cycle_tasks_added_by (added_by_user_id, added_at),
                KEY idx_cycle_tasks_removed_at (removed_at),
                KEY idx_cycle_tasks_deleted_at (deleted_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');

            $pdo->exec('CREATE TABLE IF NOT EXISTS cycle_snapshots (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                public_id VARCHAR(64) NOT NULL,

                cycle_id BIGINT UNSIGNED NOT NULL,

                snapshot_date DATE NOT NULL,

                total_tasks INT NOT NULL DEFAULT 0,
                completed_tasks INT NOT NULL DEFAULT 0,
                open_tasks INT NOT NULL DEFAULT 0,
                overdue_tasks INT NOT NULL DEFAULT 0,
                unassigned_tasks INT NOT NULL DEFAULT 0,

                payload_json JSON NULL,

                created_at DATETIME NOT NULL,

                PRIMARY KEY (id),
                UNIQUE KEY uq_cycle_snapshots_public_id (public_id),
                UNIQUE KEY uq_cycle_snapshots_cycle_date (cycle_id, snapshot_date),
                KEY idx_cycle_snapshots_cycle_created (cycle_id, created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        } else {
            // SQLite fallback
            $pdo->exec('CREATE TABLE IF NOT EXISTS work_cycles (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                public_id VARCHAR(64) NOT NULL,
                project_id INTEGER NOT NULL,
                title VARCHAR(255) NOT NULL,
                description TEXT NULL,
                goal TEXT NULL,
                status VARCHAR(32) NOT NULL DEFAULT \'planned\',
                start_at DATETIME NULL,
                end_at DATETIME NULL,
                timezone VARCHAR(64) NULL,
                owner_user_id INTEGER NULL,
                created_by_user_id INTEGER NOT NULL,
                completed_by_user_id INTEGER NULL,
                completed_at DATETIME NULL,
                archived_at DATETIME NULL,
                progress_snapshot_json TEXT NULL,
                meta_json TEXT NULL,
                sort_order INT NOT NULL DEFAULT 65535,
                row_version INT NOT NULL DEFAULT 1,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                deleted_at DATETIME NULL
            )');
            $pdo->exec('CREATE UNIQUE INDEX IF NOT EXISTS uq_work_cycles_public_id ON work_cycles(public_id)');
            $pdo->exec('CREATE INDEX IF NOT EXISTS idx_work_cycles_project_status ON work_cycles(project_id, status)');
            $pdo->exec('CREATE INDEX IF NOT EXISTS idx_work_cycles_project_dates ON work_cycles(project_id, start_at, end_at)');
            $pdo->exec('CREATE INDEX IF NOT EXISTS idx_work_cycles_owner_status ON work_cycles(owner_user_id, status)');
            $pdo->exec('CREATE INDEX IF NOT EXISTS idx_work_cycles_created_by ON work_cycles(created_by_user_id, created_at)');
            $pdo->exec('CREATE INDEX IF NOT EXISTS idx_work_cycles_deleted_at ON work_cycles(deleted_at)');

            $pdo->exec('CREATE TABLE IF NOT EXISTS cycle_tasks (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                public_id VARCHAR(64) NOT NULL,
                cycle_id INTEGER NOT NULL,
                task_id INTEGER NOT NULL,
                active_key VARCHAR(191) NULL,
                added_by_user_id INTEGER NOT NULL,
                added_at DATETIME NOT NULL,
                removed_by_user_id INTEGER NULL,
                removed_at DATETIME NULL,
                sort_order INT NOT NULL DEFAULT 65535,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                deleted_at DATETIME NULL
            )');
            $pdo->exec('CREATE UNIQUE INDEX IF NOT EXISTS uq_cycle_tasks_public_id ON cycle_tasks(public_id)');
            $pdo->exec('CREATE UNIQUE INDEX IF NOT EXISTS uq_cycle_tasks_active_key ON cycle_tasks(active_key)');
            $pdo->exec('CREATE INDEX IF NOT EXISTS idx_cycle_tasks_cycle_active ON cycle_tasks(cycle_id, deleted_at)');
            $pdo->exec('CREATE INDEX IF NOT EXISTS idx_cycle_tasks_task_active ON cycle_tasks(task_id, deleted_at)');

            $pdo->exec('CREATE TABLE IF NOT EXISTS cycle_snapshots (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                public_id VARCHAR(64) NOT NULL,
                cycle_id INTEGER NOT NULL,
                snapshot_date DATE NOT NULL,
                total_tasks INT NOT NULL DEFAULT 0,
                completed_tasks INT NOT NULL DEFAULT 0,
                open_tasks INT NOT NULL DEFAULT 0,
                overdue_tasks INT NOT NULL DEFAULT 0,
                unassigned_tasks INT NOT NULL DEFAULT 0,
                payload_json TEXT NULL,
                created_at DATETIME NOT NULL
            )');
            $pdo->exec('CREATE UNIQUE INDEX IF NOT EXISTS uq_cycle_snapshots_public_id ON cycle_snapshots(public_id)');
            $pdo->exec('CREATE UNIQUE INDEX IF NOT EXISTS uq_cycle_snapshots_cycle_date ON cycle_snapshots(cycle_id, snapshot_date)');
        }
    }
}
