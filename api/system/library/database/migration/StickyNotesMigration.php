<?php
declare(strict_types=1);

namespace Api\System\Library\Database\Migration;

use PDO;

final class StickyNotesMigration implements MigrationInterface
{
    public function key(): string
    {
        return '20260616_000008_sticky_notes';
    }

    public function description(): string
    {
        return 'Create sticky_notes table';
    }

    public function up(PDO $pdo, string $driver): void
    {
        if ($driver === 'mysql') {
            $pdo->exec('CREATE TABLE IF NOT EXISTS sticky_notes (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                public_id VARCHAR(64) NOT NULL,

                owner_user_id BIGINT UNSIGNED NOT NULL,

                context_type VARCHAR(64) NOT NULL DEFAULT \'personal\',
                context_public_id VARCHAR(64) NULL,

                title VARCHAR(255) NULL,
                body TEXT NOT NULL,

                color VARCHAR(32) NOT NULL DEFAULT \'yellow\',
                background_color VARCHAR(32) NULL,

                visibility VARCHAR(32) NOT NULL DEFAULT \'private\',

                is_pinned TINYINT(1) NOT NULL DEFAULT 0,
                sort_order INT NOT NULL DEFAULT 65535,

                converted_to_entity_type VARCHAR(64) NULL,
                converted_to_entity_public_id VARCHAR(64) NULL,
                converted_at DATETIME NULL,
                converted_by_user_id BIGINT UNSIGNED NULL,

                meta_json JSON NULL,

                row_version INT NOT NULL DEFAULT 1,

                archived_at DATETIME NULL,
                deleted_at DATETIME NULL,

                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,

                PRIMARY KEY (id),

                UNIQUE KEY uq_sticky_notes_public_id (public_id),

                KEY idx_sticky_notes_owner_context (owner_user_id, context_type, context_public_id),
                KEY idx_sticky_notes_context (context_type, context_public_id),
                KEY idx_sticky_notes_owner_pinned (owner_user_id, is_pinned, sort_order),
                KEY idx_sticky_notes_visibility (visibility),
                KEY idx_sticky_notes_archived_at (archived_at),
                KEY idx_sticky_notes_deleted_at (deleted_at),
                KEY idx_sticky_notes_converted (converted_to_entity_type, converted_to_entity_public_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        } else {
            // SQLite fallback
            $pdo->exec('CREATE TABLE IF NOT EXISTS sticky_notes (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                public_id VARCHAR(64) NOT NULL,
                owner_user_id INTEGER NOT NULL,
                context_type VARCHAR(64) NOT NULL DEFAULT \'personal\',
                context_public_id VARCHAR(64) NULL,
                title VARCHAR(255) NULL,
                body TEXT NOT NULL,
                color VARCHAR(32) NOT NULL DEFAULT \'yellow\',
                background_color VARCHAR(32) NULL,
                visibility VARCHAR(32) NOT NULL DEFAULT \'private\',
                is_pinned INTEGER NOT NULL DEFAULT 0,
                sort_order INTEGER NOT NULL DEFAULT 65535,
                converted_to_entity_type VARCHAR(64) NULL,
                converted_to_entity_public_id VARCHAR(64) NULL,
                converted_at DATETIME NULL,
                converted_by_user_id INTEGER NULL,
                meta_json TEXT NULL,
                row_version INTEGER NOT NULL DEFAULT 1,
                archived_at DATETIME NULL,
                deleted_at DATETIME NULL,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL
            )');
            $pdo->exec('CREATE UNIQUE INDEX IF NOT EXISTS uq_sticky_notes_public_id ON sticky_notes(public_id)');
            $pdo->exec('CREATE INDEX IF NOT EXISTS idx_sticky_notes_owner_context ON sticky_notes(owner_user_id, context_type, context_public_id)');
            $pdo->exec('CREATE INDEX IF NOT EXISTS idx_sticky_notes_context ON sticky_notes(context_type, context_public_id)');
            $pdo->exec('CREATE INDEX IF NOT EXISTS idx_sticky_notes_owner_pinned ON sticky_notes(owner_user_id, is_pinned, sort_order)');
            $pdo->exec('CREATE INDEX IF NOT EXISTS idx_sticky_notes_visibility ON sticky_notes(visibility)');
            $pdo->exec('CREATE INDEX IF NOT EXISTS idx_sticky_notes_archived_at ON sticky_notes(archived_at)');
            $pdo->exec('CREATE INDEX IF NOT EXISTS idx_sticky_notes_deleted_at ON sticky_notes(deleted_at)');
            $pdo->exec('CREATE INDEX IF NOT EXISTS idx_sticky_notes_converted ON sticky_notes(converted_to_entity_type, converted_to_entity_public_id)');
        }
    }
}
