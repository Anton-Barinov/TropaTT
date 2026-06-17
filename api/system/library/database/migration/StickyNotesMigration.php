<?php
declare(strict_types=1);

namespace Api\System\Library\Database\Migration;

use PDO;

final class StickyNotesMigration implements MigrationInterface
{
    public function key(): string
    {
        return '20260616_000009_sticky_notes';
    }

    public function description(): string
    {
        return 'Create sticky notes';
    }

    public function up(PDO $pdo, string $driver): void
    {
        if ($driver !== 'mysql') {
            return;
        }

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
    }
}
