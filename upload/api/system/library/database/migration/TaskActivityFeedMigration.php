<?php
declare(strict_types=1);

namespace Api\System\Library\Database\Migration;

use PDO;

final class TaskActivityFeedMigration implements MigrationInterface
{
    public function key(): string
    {
        return '20260616_000005_task_activity_feed';
    }

    public function description(): string
    {
        return 'Create task activity feed events table';
    }

    public function up(PDO $pdo, string $driver): void
    {
        if ($driver === 'mysql') {
            $pdo->exec('CREATE TABLE IF NOT EXISTS task_activity_events (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                public_id VARCHAR(64) NOT NULL,

                task_id BIGINT UNSIGNED NOT NULL,
                task_public_id VARCHAR(64) NOT NULL,

                actor_user_id BIGINT UNSIGNED NULL,
                actor_type VARCHAR(32) NOT NULL DEFAULT \'user\',
                actor_public_id VARCHAR(64) NULL,
                actor_display_name VARCHAR(255) NULL,

                event_type VARCHAR(96) NOT NULL,
                field_name VARCHAR(128) NULL,

                old_value TEXT NULL,
                new_value TEXT NULL,

                old_label VARCHAR(255) NULL,
                new_label VARCHAR(255) NULL,

                related_entity_type VARCHAR(64) NULL,
                related_entity_id BIGINT UNSIGNED NULL,
                related_entity_public_id VARCHAR(64) NULL,
                related_entity_label VARCHAR(255) NULL,

                message_key VARCHAR(128) NULL,
                message_text VARCHAR(1000) NULL,

                payload_json JSON NULL,

                visibility VARCHAR(32) NOT NULL DEFAULT \'default\',

                request_id VARCHAR(128) NULL,
                source_type VARCHAR(64) NULL,
                source_ref VARCHAR(255) NULL,

                created_at DATETIME NOT NULL,
                deleted_at DATETIME NULL,

                PRIMARY KEY (id),

                UNIQUE KEY uq_task_activity_events_public_id (public_id),

                KEY idx_task_activity_events_task_created (task_id, created_at),
                KEY idx_task_activity_events_task_public_created (task_public_id, created_at),
                KEY idx_task_activity_events_actor_created (actor_user_id, created_at),
                KEY idx_task_activity_events_event_type (event_type, created_at),
                KEY idx_task_activity_events_related (related_entity_type, related_entity_public_id),
                KEY idx_task_activity_events_request_id (request_id),
                KEY idx_task_activity_events_deleted_at (deleted_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        } else {
            // SQLite fallback
            $pdo->exec('CREATE TABLE IF NOT EXISTS task_activity_events (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                public_id VARCHAR(64) NOT NULL,

                task_id INTEGER NOT NULL,
                task_public_id VARCHAR(64) NOT NULL,

                actor_user_id INTEGER NULL,
                actor_type VARCHAR(32) NOT NULL DEFAULT \'user\',
                actor_public_id VARCHAR(64) NULL,
                actor_display_name VARCHAR(255) NULL,

                event_type VARCHAR(96) NOT NULL,
                field_name VARCHAR(128) NULL,

                old_value TEXT NULL,
                new_value TEXT NULL,

                old_label VARCHAR(255) NULL,
                new_label VARCHAR(255) NULL,

                related_entity_type VARCHAR(64) NULL,
                related_entity_id INTEGER NULL,
                related_entity_public_id VARCHAR(64) NULL,
                related_entity_label VARCHAR(255) NULL,

                message_key VARCHAR(128) NULL,
                message_text VARCHAR(1000) NULL,

                payload_json TEXT NULL,

                visibility VARCHAR(32) NOT NULL DEFAULT \'default\',

                request_id VARCHAR(128) NULL,
                source_type VARCHAR(64) NULL,
                source_ref VARCHAR(255) NULL,

                created_at DATETIME NOT NULL,
                deleted_at DATETIME NULL
            )');

            $pdo->exec('CREATE UNIQUE INDEX IF NOT EXISTS uq_task_activity_events_public_id ON task_activity_events(public_id)');
            $pdo->exec('CREATE INDEX IF NOT EXISTS idx_task_activity_events_task_created ON task_activity_events(task_id, created_at)');
            $pdo->exec('CREATE INDEX IF NOT EXISTS idx_task_activity_events_task_public_created ON task_activity_events(task_public_id, created_at)');
            $pdo->exec('CREATE INDEX IF NOT EXISTS idx_task_activity_events_actor_created ON task_activity_events(actor_user_id, created_at)');
            $pdo->exec('CREATE INDEX IF NOT EXISTS idx_task_activity_events_event_type ON task_activity_events(event_type, created_at)');
            $pdo->exec('CREATE INDEX IF NOT EXISTS idx_task_activity_events_related ON task_activity_events(related_entity_type, related_entity_public_id)');
            $pdo->exec('CREATE INDEX IF NOT EXISTS idx_task_activity_events_request_id ON task_activity_events(request_id)');
            $pdo->exec('CREATE INDEX IF NOT EXISTS idx_task_activity_events_deleted_at ON task_activity_events(deleted_at)');
        }
    }
}
