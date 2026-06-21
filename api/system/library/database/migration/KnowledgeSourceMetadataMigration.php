<?php
declare(strict_types=1);

namespace Api\System\Library\Database\Migration;

use PDO;

final class KnowledgeSourceMetadataMigration implements MigrationInterface
{
    public function key(): string
    {
        return '20260621_000001_knowledge_source_metadata';
    }

    public function description(): string
    {
        return 'Add source metadata fields and page properties for external imports (Confluence migration)';
    }

    public function up(PDO $pdo, string $driver): void
    {
        if ($driver !== 'mysql') {
            return;
        }

        $this->addColumnsToKnowledgePages($pdo);
        $this->addColumnsToKnowledgeSpaces($pdo);
        $this->addColumnsToFiles($pdo);
        $this->addColumnsToKnowledgeComments($pdo);
        $this->createKnowledgePagePropertiesTable($pdo);
        $this->addSourceIndexes($pdo);
    }

    private function addColumnsToKnowledgePages(PDO $pdo): void
    {
        $columns = $this->existingColumns($pdo, 'knowledge_pages');
        $alter = [];

        if (!in_array('source_type', $columns, true)) {
            $alter[] = 'ADD COLUMN source_type VARCHAR(64) NULL AFTER deleted_at';
        }
        if (!in_array('source_id', $columns, true)) {
            $alter[] = 'ADD COLUMN source_id VARCHAR(255) NULL AFTER source_type';
        }
        if (!in_array('source_url', $columns, true)) {
            $alter[] = 'ADD COLUMN source_url VARCHAR(2048) NULL AFTER source_id';
        }
        if (!in_array('source_payload_json', $columns, true)) {
            $alter[] = 'ADD COLUMN source_payload_json JSON NULL AFTER source_url';
        }

        if ($alter !== []) {
            $pdo->exec('ALTER TABLE knowledge_pages ' . implode(', ', $alter));
        }
    }

    private function addColumnsToKnowledgeSpaces(PDO $pdo): void
    {
        $columns = $this->existingColumns($pdo, 'knowledge_spaces');
        $alter = [];

        if (!in_array('source_type', $columns, true)) {
            $alter[] = 'ADD COLUMN source_type VARCHAR(64) NULL AFTER updated_at';
        }
        if (!in_array('source_id', $columns, true)) {
            $alter[] = 'ADD COLUMN source_id VARCHAR(255) NULL AFTER source_type';
        }
        if (!in_array('source_url', $columns, true)) {
            $alter[] = 'ADD COLUMN source_url VARCHAR(2048) NULL AFTER source_id';
        }
        if (!in_array('source_payload_json', $columns, true)) {
            $alter[] = 'ADD COLUMN source_payload_json JSON NULL AFTER source_url';
        }

        if ($alter !== []) {
            $pdo->exec('ALTER TABLE knowledge_spaces ' . implode(', ', $alter));
        }
    }

    private function addColumnsToFiles(PDO $pdo): void
    {
        $columns = $this->existingColumns($pdo, 'files');
        $alter = [];

        if (!in_array('source_type', $columns, true)) {
            $alter[] = 'ADD COLUMN source_type VARCHAR(64) NULL AFTER created_at';
        }
        if (!in_array('source_id', $columns, true)) {
            $alter[] = 'ADD COLUMN source_id VARCHAR(255) NULL AFTER source_type';
        }
        if (!in_array('source_url', $columns, true)) {
            $alter[] = 'ADD COLUMN source_url VARCHAR(2048) NULL AFTER source_id';
        }
        if (!in_array('checksum', $columns, true)) {
            $alter[] = 'ADD COLUMN checksum CHAR(64) NULL AFTER source_url';
        }
        if (!in_array('source_payload_json', $columns, true)) {
            $alter[] = 'ADD COLUMN source_payload_json JSON NULL AFTER checksum';
        }

        if ($alter !== []) {
            $pdo->exec('ALTER TABLE files ' . implode(', ', $alter));
        }
    }

    private function addColumnsToKnowledgeComments(PDO $pdo): void
    {
        $columns = $this->existingColumns($pdo, 'knowledge_comments');
        $alter = [];

        if (!in_array('source_type', $columns, true)) {
            $alter[] = 'ADD COLUMN source_type VARCHAR(64) NULL AFTER updated_at';
        }
        if (!in_array('source_id', $columns, true)) {
            $alter[] = 'ADD COLUMN source_id VARCHAR(255) NULL AFTER source_type';
        }
        if (!in_array('source_author_name', $columns, true)) {
            $alter[] = 'ADD COLUMN source_author_name VARCHAR(255) NULL AFTER source_id';
        }
        if (!in_array('source_created_at', $columns, true)) {
            $alter[] = 'ADD COLUMN source_created_at DATETIME NULL AFTER source_author_name';
        }
        if (!in_array('anchor_text', $columns, true)) {
            $alter[] = 'ADD COLUMN anchor_text VARCHAR(500) NULL AFTER source_created_at';
        }
        if (!in_array('anchor_path', $columns, true)) {
            $alter[] = 'ADD COLUMN anchor_path VARCHAR(500) NULL AFTER anchor_text';
        }
        if (!in_array('is_inline', $columns, true)) {
            $alter[] = 'ADD COLUMN is_inline TINYINT(1) NOT NULL DEFAULT 0 AFTER anchor_path';
        }

        if ($alter !== []) {
            $pdo->exec('ALTER TABLE knowledge_comments ' . implode(', ', $alter));
        }
    }

    private function createKnowledgePagePropertiesTable(PDO $pdo): void
    {
        $pdo->exec('CREATE TABLE IF NOT EXISTS knowledge_page_properties (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            page_id BIGINT UNSIGNED NOT NULL,
            property_key VARCHAR(190) NOT NULL,
            property_value LONGTEXT NULL,
            property_type VARCHAR(32) NOT NULL DEFAULT \'string\',
            source_type VARCHAR(64) NULL,
            source_id VARCHAR(255) NULL,
            sort_order INT NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY uq_knowledge_page_property (page_id, property_key),
            KEY idx_knowledge_page_properties_source (source_type, source_id),
            KEY idx_knowledge_page_properties_key (property_key)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
    }

    private function addSourceIndexes(PDO $pdo): void
    {
        try {
            $pdo->exec('CREATE INDEX idx_knowledge_pages_source ON knowledge_pages(source_type, source_id)');
        } catch (\Throwable) {
        }

        try {
            $pdo->exec('CREATE INDEX idx_knowledge_spaces_source ON knowledge_spaces(source_type, source_id)');
        } catch (\Throwable) {
        }

        try {
            $pdo->exec('CREATE INDEX idx_files_source ON files(source_type, source_id)');
        } catch (\Throwable) {
        }

        try {
            $pdo->exec('CREATE INDEX idx_knowledge_comments_source ON knowledge_comments(source_type, source_id)');
        } catch (\Throwable) {
        }
    }

    /** @return array<int,string> */
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
