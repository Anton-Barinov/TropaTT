<?php
declare(strict_types=1);

namespace Api\System\Library\Database\Migration;

use Api\System\Library\Database\IndexHelper;
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
                ['description', 'TEXT NULL AFTER title'],
                ['access_level', "VARCHAR(32) NOT NULL DEFAULT 'private' AFTER filters"],
                ['display_filters', 'JSON NULL AFTER access_level'],
                ['display_properties', 'JSON NULL AFTER display_filters'],
                ['rich_filters', 'JSON NULL AFTER display_properties'],
                ['layout', "VARCHAR(32) NOT NULL DEFAULT 'list' AFTER rich_filters"],
                ['group_by', 'VARCHAR(64) NULL AFTER layout'],
                ['order_by', 'VARCHAR(64) NULL AFTER group_by'],
                ['order_dir', 'VARCHAR(8) NULL AFTER order_by'],
                ['is_locked', 'TINYINT(1) NOT NULL DEFAULT 0 AFTER order_dir'],
                ['is_system', 'TINYINT(1) NOT NULL DEFAULT 0 AFTER is_locked'],
                ['sort_order', 'INT NOT NULL DEFAULT 65535 AFTER is_system'],
                ['archived_at', 'DATETIME NULL AFTER sort_order'],
                ['updated_by_user_id', 'BIGINT UNSIGNED NULL AFTER user_id'],
            ];

            foreach ($columns as [$column, $definition]) {
                IndexHelper::addColumnIfNotExists($pdo, 'saved_views', $column, $definition);
            }

            // Add indexes
            IndexHelper::createIndexIfNotExists($pdo, 'saved_views', 'idx_saved_views_entity_access', 'entity_type, access_level');
            IndexHelper::createIndexIfNotExists($pdo, 'saved_views', 'idx_saved_views_user_entity', 'user_id, entity_type');
            IndexHelper::createIndexIfNotExists($pdo, 'saved_views', 'idx_saved_views_archived', 'archived_at');
            IndexHelper::createIndexIfNotExists($pdo, 'saved_views', 'idx_saved_views_sort_order', 'sort_order');
            IndexHelper::createIndexIfNotExists($pdo, 'saved_views', 'idx_saved_views_system_locked', 'is_system, is_locked');

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

            IndexHelper::createIndexIfNotExists($pdo, 'saved_view_user_preferences', 'uq_saved_view_user_preferences_public_id', 'public_id', true);
            IndexHelper::createIndexIfNotExists($pdo, 'saved_view_user_preferences', 'uq_saved_view_user_preferences_view_user', 'saved_view_id, user_id', true);
            IndexHelper::createIndexIfNotExists($pdo, 'saved_view_user_preferences', 'idx_saved_view_user_preferences_user_pinned', 'user_id, is_pinned, sort_order');
            IndexHelper::createIndexIfNotExists($pdo, 'saved_view_user_preferences', 'idx_saved_view_user_preferences_last_used', 'user_id, last_used_at');
        }
    }
}
