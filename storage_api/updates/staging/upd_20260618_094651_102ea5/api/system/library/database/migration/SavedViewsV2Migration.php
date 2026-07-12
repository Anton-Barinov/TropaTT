<?php
declare(strict_types=1);

namespace Api\System\Library\Database\Migration;

use PDO;

final class SavedViewsV2Migration implements MigrationInterface
{
    public function key(): string
    {
        return '20260616_000004_saved_views_v2';
    }

    public function description(): string
    {
        return 'Extend saved views with layouts, access, display properties and user preferences';
    }

    public function up(PDO $pdo, string $driver): void
    {
        if ($driver === 'mysql') {
            // Add columns to saved_views table
            $columns = [
                'ADD COLUMN IF NOT EXISTS description TEXT NULL AFTER title',
                'ADD COLUMN IF NOT EXISTS access_level VARCHAR(32) NOT NULL DEFAULT \'private\' AFTER filters',
                'ADD COLUMN IF NOT EXISTS display_filters JSON NULL AFTER access_level',
                'ADD COLUMN IF NOT EXISTS display_properties JSON NULL AFTER display_filters',
                'ADD COLUMN IF NOT EXISTS rich_filters JSON NULL AFTER display_properties',
                'ADD COLUMN IF NOT EXISTS layout VARCHAR(32) NOT NULL DEFAULT \'list\' AFTER rich_filters',
                'ADD COLUMN IF NOT EXISTS group_by VARCHAR(64) NULL AFTER layout',
                'ADD COLUMN IF NOT EXISTS order_by VARCHAR(64) NULL AFTER group_by',
                'ADD COLUMN IF NOT EXISTS order_dir VARCHAR(8) NULL AFTER order_by',
                'ADD COLUMN IF NOT EXISTS is_locked TINYINT(1) NOT NULL DEFAULT 0 AFTER order_dir',
                'ADD COLUMN IF NOT EXISTS is_system TINYINT(1) NOT NULL DEFAULT 0 AFTER is_locked',
                'ADD COLUMN IF NOT EXISTS sort_order INT NOT NULL DEFAULT 65535 AFTER is_system',
                'ADD COLUMN IF NOT EXISTS archived_at DATETIME NULL AFTER sort_order',
                'ADD COLUMN IF NOT EXISTS updated_by_user_id BIGINT UNSIGNED NULL AFTER user_id',
            ];

            foreach ($columns as $col) {
                $pdo->exec('ALTER TABLE saved_views ' . $col);
            }

            // Add indexes
            $pdo->exec('CREATE INDEX IF NOT EXISTS idx_saved_views_entity_access ON saved_views (entity_type, access_level)');
            $pdo->exec('CREATE INDEX IF NOT EXISTS idx_saved_views_user_entity ON saved_views (user_id, entity_type)');
            $pdo->exec('CREATE INDEX IF NOT EXISTS idx_saved_views_archived ON saved_views (archived_at)');
            $pdo->exec('CREATE INDEX IF NOT EXISTS idx_saved_views_sort_order ON saved_views (sort_order)');
            $pdo->exec('CREATE INDEX IF NOT EXISTS idx_saved_views_system_locked ON saved_views (is_system, is_locked)');

            // Create user preferences table
            $pdo->exec('CREATE TABLE IF NOT EXISTS saved_view_user_preferences (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                public_id VARCHAR(64) NOT NULL,

                saved_view_id BIGINT UNSIGNED NOT NULL,
                user_id BIGINT UNSIGNED NOT NULL,

                is_pinned TINYINT(1) NOT NULL DEFAULT 0,
                sort_order INT NOT NULL DEFAULT 65535,
                last_used_at DATETIME NULL,

                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,

                PRIMARY KEY (id),
                UNIQUE KEY uq_saved_view_user_preferences_public_id (public_id),
                UNIQUE KEY uq_saved_view_user_preferences_view_user (saved_view_id, user_id),
                KEY idx_saved_view_user_preferences_user_pinned (user_id, is_pinned, sort_order),
                KEY idx_saved_view_user_preferences_last_used (user_id, last_used_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        } else {
            // SQLite fallback
            $tableInfo = $pdo->query("PRAGMA table_info(saved_views)")->fetchAll(PDO::FETCH_ASSOC);
            $columns = array_map(static fn(array $row): string => (string)$row['name'], $tableInfo);

            if (!in_array('access_level', $columns, true)) {
                $pdo->exec('ALTER TABLE saved_views ADD COLUMN description TEXT NULL');
                $pdo->exec("ALTER TABLE saved_views ADD COLUMN access_level VARCHAR(32) NOT NULL DEFAULT 'private'");
                $pdo->exec('ALTER TABLE saved_views ADD COLUMN display_filters TEXT NULL');
                $pdo->exec('ALTER TABLE saved_views ADD COLUMN display_properties TEXT NULL');
                $pdo->exec('ALTER TABLE saved_views ADD COLUMN rich_filters TEXT NULL');
                $pdo->exec("ALTER TABLE saved_views ADD COLUMN layout VARCHAR(32) NOT NULL DEFAULT 'list'");
                $pdo->exec('ALTER TABLE saved_views ADD COLUMN group_by VARCHAR(64) NULL');
                $pdo->exec('ALTER TABLE saved_views ADD COLUMN order_by VARCHAR(64) NULL');
                $pdo->exec('ALTER TABLE saved_views ADD COLUMN order_dir VARCHAR(8) NULL');
                $pdo->exec('ALTER TABLE saved_views ADD COLUMN is_locked INTEGER NOT NULL DEFAULT 0');
                $pdo->exec('ALTER TABLE saved_views ADD COLUMN is_system INTEGER NOT NULL DEFAULT 0');
                $pdo->exec('ALTER TABLE saved_views ADD COLUMN sort_order INTEGER NOT NULL DEFAULT 65535');
                $pdo->exec('ALTER TABLE saved_views ADD COLUMN archived_at DATETIME NULL');
                $pdo->exec('ALTER TABLE saved_views ADD COLUMN updated_by_user_id INTEGER NULL');
            } else {
                // Add missing columns for idempotency
                $addIfMissing = [
                    'description' => 'ALTER TABLE saved_views ADD COLUMN description TEXT NULL',
                    'access_level' => "ALTER TABLE saved_views ADD COLUMN access_level VARCHAR(32) NOT NULL DEFAULT 'private'",
                    'display_filters' => 'ALTER TABLE saved_views ADD COLUMN display_filters TEXT NULL',
                    'display_properties' => 'ALTER TABLE saved_views ADD COLUMN display_properties TEXT NULL',
                    'rich_filters' => 'ALTER TABLE saved_views ADD COLUMN rich_filters TEXT NULL',
                    'layout' => "ALTER TABLE saved_views ADD COLUMN layout VARCHAR(32) NOT NULL DEFAULT 'list'",
                    'group_by' => 'ALTER TABLE saved_views ADD COLUMN group_by VARCHAR(64) NULL',
                    'order_by' => 'ALTER TABLE saved_views ADD COLUMN order_by VARCHAR(64) NULL',
                    'order_dir' => 'ALTER TABLE saved_views ADD COLUMN order_dir VARCHAR(8) NULL',
                    'is_locked' => 'ALTER TABLE saved_views ADD COLUMN is_locked INTEGER NOT NULL DEFAULT 0',
                    'is_system' => 'ALTER TABLE saved_views ADD COLUMN is_system INTEGER NOT NULL DEFAULT 0',
                    'sort_order' => 'ALTER TABLE saved_views ADD COLUMN sort_order INTEGER NOT NULL DEFAULT 65535',
                    'archived_at' => 'ALTER TABLE saved_views ADD COLUMN archived_at DATETIME NULL',
                    'updated_by_user_id' => 'ALTER TABLE saved_views ADD COLUMN updated_by_user_id INTEGER NULL',
                ];
                foreach ($addIfMissing as $colName => $sql) {
                    if (!in_array($colName, $columns, true)) {
                        $pdo->exec($sql);
                    }
                }
            }

            // Create preferences table
            $pdo->exec('CREATE TABLE IF NOT EXISTS saved_view_user_preferences (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                public_id VARCHAR(64) NOT NULL,
                saved_view_id INTEGER NOT NULL,
                user_id INTEGER NOT NULL,
                is_pinned INTEGER NOT NULL DEFAULT 0,
                sort_order INTEGER NOT NULL DEFAULT 65535,
                last_used_at DATETIME NULL,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL
            )');

            $pdo->exec('CREATE UNIQUE INDEX IF NOT EXISTS uq_saved_view_user_preferences_public_id ON saved_view_user_preferences(public_id)');
            $pdo->exec('CREATE UNIQUE INDEX IF NOT EXISTS uq_saved_view_user_preferences_view_user ON saved_view_user_preferences(saved_view_id, user_id)');
            $pdo->exec('CREATE INDEX IF NOT EXISTS idx_saved_view_user_preferences_user_pinned ON saved_view_user_preferences(user_id, is_pinned, sort_order)');
            $pdo->exec('CREATE INDEX IF NOT EXISTS idx_saved_view_user_preferences_last_used ON saved_view_user_preferences(user_id, last_used_at)');
        }
    }
}
