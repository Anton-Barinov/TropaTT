<?php
declare(strict_types=1);

namespace Api\System\Library\Database\Migration;

use PDO;

final class KnowledgePageVersionsMigration implements MigrationInterface
{
    public function key(): string
    {
        return '20260616_000008_knowledge_page_versions';
    }

    public function description(): string
    {
        return 'Add knowledge page versions and locking fields';
    }

    public function up(PDO $pdo, string $driver): void
    {
        if ($driver !== 'mysql') {
            return;
        }

        // Add lock and version fields to knowledge_pages if they don't exist
        $columns = $this->existingColumns($pdo, 'knowledge_pages');
        $alterParts = [];

        if (!in_array('row_version', $columns, true)) {
            $alterParts[] = 'ADD COLUMN row_version INT NOT NULL DEFAULT 1';
        }
        if (!in_array('locked_at', $columns, true)) {
            $alterParts[] = 'ADD COLUMN locked_at DATETIME NULL';
        }
        if (!in_array('locked_by_user_id', $columns, true)) {
            $alterParts[] = 'ADD COLUMN locked_by_user_id BIGINT UNSIGNED NULL';
        }
        if (!in_array('lock_reason', $columns, true)) {
            $alterParts[] = 'ADD COLUMN lock_reason VARCHAR(1000) NULL';
        }
        if (!in_array('last_version_number', $columns, true)) {
            $alterParts[] = 'ADD COLUMN last_version_number INT NOT NULL DEFAULT 0';
        }

        if ($alterParts !== []) {
            $sql = 'ALTER TABLE knowledge_pages ' . implode(', ', $alterParts);
            $pdo->exec($sql);
        }

        // Drop old-style table if it exists, recreate with full schema
        $pdo->exec('DROP TABLE IF EXISTS knowledge_page_versions');

        // Create knowledge_page_versions table
        $pdo->exec('CREATE TABLE knowledge_page_versions (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            public_id VARCHAR(64) NOT NULL,

            page_id BIGINT UNSIGNED NOT NULL,
            page_public_id VARCHAR(64) NOT NULL,

            version_number INT NOT NULL,

            title VARCHAR(255) NOT NULL,
            content LONGTEXT NULL,
            content_text LONGTEXT NULL,
            summary TEXT NULL,

            visibility VARCHAR(32) NULL,
            status VARCHAR(32) NULL,

            tags_json JSON NULL,
            links_json JSON NULL,
            meta_json JSON NULL,

            change_type VARCHAR(64) NOT NULL DEFAULT \'update\',
            change_note VARCHAR(1000) NULL,

            restored_from_version_number INT NULL,
            restored_from_version_public_id VARCHAR(64) NULL,

            created_by_user_id BIGINT UNSIGNED NULL,
            created_by_actor_type VARCHAR(32) NOT NULL DEFAULT \'user\',
            created_by_display_name VARCHAR(255) NULL,

            request_id VARCHAR(128) NULL,
            source_type VARCHAR(64) NULL,
            source_ref VARCHAR(255) NULL,

            content_hash CHAR(64) NULL,

            created_at DATETIME NOT NULL,
            deleted_at DATETIME NULL,

            PRIMARY KEY (id),

            UNIQUE KEY uq_knowledge_page_versions_public_id (public_id),
            UNIQUE KEY uq_knowledge_page_versions_page_number (page_id, version_number),

            KEY idx_knowledge_page_versions_page_created (page_id, created_at),
            KEY idx_knowledge_page_versions_page_public_created (page_public_id, created_at),
            KEY idx_knowledge_page_versions_created_by (created_by_user_id, created_at),
            KEY idx_knowledge_page_versions_change_type (change_type, created_at),
            KEY idx_knowledge_page_versions_hash (content_hash),
            KEY idx_knowledge_page_versions_deleted_at (deleted_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
    }

    /**
     * @return array<int,string>
     */
    private function existingColumns(PDO $pdo, string $table): array
    {
        try {
            $stmt = $pdo->prepare("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?");
            $stmt->execute([$table]);
            $result = $stmt->fetchAll(PDO::FETCH_COLUMN);
            return is_array($result) ? array_map('strval', $result) : [];
        } catch (\Throwable) {
            return [];
        }
    }
}
