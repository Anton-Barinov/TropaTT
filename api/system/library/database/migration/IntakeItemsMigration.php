<?php
declare(strict_types=1);

namespace Api\System\Library\Database\Migration;

use PDO;

final class IntakeItemsMigration implements MigrationInterface
{
    public function key(): string
    {
        return '20260616_000001_intake_items';
    }

    public function description(): string
    {
        return 'Create intake items and intake item activities tables';
    }

    public function up(PDO $pdo, string $driver): void
    {
        if ($driver === 'mysql') {
            $pdo->exec('CREATE TABLE IF NOT EXISTS intake_items (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                public_id VARCHAR(64) NOT NULL,

                project_id BIGINT UNSIGNED NULL,
                client_id BIGINT UNSIGNED NULL,
                contact_id BIGINT UNSIGNED NULL,

                title VARCHAR(255) NOT NULL,
                description TEXT NULL,

                status VARCHAR(32) NOT NULL DEFAULT \'pending\',
                priority_code VARCHAR(64) NULL,

                source_type VARCHAR(64) NOT NULL DEFAULT \'manual\',
                source_ref VARCHAR(255) NULL,
                source_email VARCHAR(255) NULL,
                external_source VARCHAR(255) NULL,
                external_id VARCHAR(255) NULL,
                extra_json JSON NULL,

                due_at DATETIME NULL,
                snoozed_until DATETIME NULL,

                assignee_user_id BIGINT UNSIGNED NULL,
                creator_user_id BIGINT UNSIGNED NOT NULL,

                accepted_task_id BIGINT UNSIGNED NULL,
                duplicate_intake_item_id BIGINT UNSIGNED NULL,
                duplicate_task_id BIGINT UNSIGNED NULL,

                resolution_note TEXT NULL,
                resolved_by_user_id BIGINT UNSIGNED NULL,
                resolved_at DATETIME NULL,

                row_version INT NOT NULL DEFAULT 1,

                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                deleted_at DATETIME NULL,

                PRIMARY KEY (id),
                UNIQUE KEY uq_intake_items_public_id (public_id),

                KEY idx_intake_items_status (status),
                KEY idx_intake_items_project_status (project_id, status),
                KEY idx_intake_items_client_status (client_id, status),
                KEY idx_intake_items_assignee_status (assignee_user_id, status),
                KEY idx_intake_items_creator_status (creator_user_id, status),
                KEY idx_intake_items_snoozed_until (snoozed_until),
                KEY idx_intake_items_due_at (due_at),
                KEY idx_intake_items_created_at (created_at),
                KEY idx_intake_items_updated_at (updated_at),
                KEY idx_intake_items_deleted_at (deleted_at),
                KEY idx_intake_items_external (external_source, external_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');

            $pdo->exec('CREATE TABLE IF NOT EXISTS intake_item_activities (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                public_id VARCHAR(64) NOT NULL,

                intake_item_id BIGINT UNSIGNED NOT NULL,
                actor_user_id BIGINT UNSIGNED NULL,

                event_type VARCHAR(64) NOT NULL,
                field_name VARCHAR(128) NULL,
                old_value TEXT NULL,
                new_value TEXT NULL,
                comment TEXT NULL,

                created_at DATETIME NOT NULL,

                PRIMARY KEY (id),
                UNIQUE KEY uq_intake_item_activities_public_id (public_id),

                KEY idx_intake_item_activities_item_created (intake_item_id, created_at),
                KEY idx_intake_item_activities_actor_created (actor_user_id, created_at),
                KEY idx_intake_item_activities_type_created (event_type, created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
            return;
        }

        // Fallback for SQLite
        $pdo->exec('CREATE TABLE IF NOT EXISTS intake_items (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            public_id VARCHAR(64) NOT NULL UNIQUE,

            project_id INTEGER NULL,
            client_id INTEGER NULL,
            contact_id INTEGER NULL,

            title VARCHAR(255) NOT NULL,
            description TEXT NULL,

            status VARCHAR(32) NOT NULL DEFAULT \'pending\',
            priority_code VARCHAR(64) NULL,

            source_type VARCHAR(64) NOT NULL DEFAULT \'manual\',
            source_ref VARCHAR(255) NULL,
            source_email VARCHAR(255) NULL,
            external_source VARCHAR(255) NULL,
            external_id VARCHAR(255) NULL,
            extra_json TEXT NULL,

            due_at DATETIME NULL,
            snoozed_until DATETIME NULL,

            assignee_user_id INTEGER NULL,
            creator_user_id INTEGER NOT NULL,

            accepted_task_id INTEGER NULL,
            duplicate_intake_item_id INTEGER NULL,
            duplicate_task_id INTEGER NULL,

            resolution_note TEXT NULL,
            resolved_by_user_id INTEGER NULL,
            resolved_at DATETIME NULL,

            row_version INTEGER NOT NULL DEFAULT 1,

            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            deleted_at DATETIME NULL
        )');

        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_intake_items_status ON intake_items(status)');
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_intake_items_project_status ON intake_items(project_id, status)');
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_intake_items_client_status ON intake_items(client_id, status)');
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_intake_items_assignee_status ON intake_items(assignee_user_id, status)');
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_intake_items_creator_status ON intake_items(creator_user_id, status)');
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_intake_items_snoozed_until ON intake_items(snoozed_until)');
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_intake_items_due_at ON intake_items(due_at)');
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_intake_items_created_at ON intake_items(created_at)');
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_intake_items_updated_at ON intake_items(updated_at)');
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_intake_items_deleted_at ON intake_items(deleted_at)');

        $pdo->exec('CREATE TABLE IF NOT EXISTS intake_item_activities (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            public_id VARCHAR(64) NOT NULL UNIQUE,

            intake_item_id INTEGER NOT NULL,
            actor_user_id INTEGER NULL,

            event_type VARCHAR(64) NOT NULL,
            field_name VARCHAR(128) NULL,
            old_value TEXT NULL,
            new_value TEXT NULL,
            comment TEXT NULL,

            created_at DATETIME NOT NULL
        )');

        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_intake_item_activities_item_created ON intake_item_activities(intake_item_id, created_at)');
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_intake_item_activities_actor_created ON intake_item_activities(actor_user_id, created_at)');
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_intake_item_activities_type_created ON intake_item_activities(event_type, created_at)');
    }
}
