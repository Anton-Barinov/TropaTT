<?php
declare(strict_types=1);

namespace Api\System\Library\Database\Migration;

use PDO;

/**
 * Maintains a unique source-to-comment projection for idempotent imports.
 * Existing comments are preserved; duplicate legacy source rows map to their
 * lowest comment id, so rollback can drop this derived table without deleting
 * or rewriting any comment content.
 */
final class KnowledgeCommentSourcesMigration implements MigrationInterface
{
    public function key(): string
    {
        return '20261027_000001_knowledge_comment_sources';
    }

    public function description(): string
    {
        return 'Add workspace-scoped idempotency index for imported knowledge comments';
    }

    public function up(PDO $pdo, string $driver): void
    {
        // KnowledgeSourceMetadataMigration currently installs these columns only
        // on MySQL. Ensure the comment metadata schema before seeding the derived
        // source index on other supported PDO drivers as well. Keep these columns
        // on rollback: they are additive metadata and may contain imported data.
        $this->ensureCommentSourceColumns($pdo, $driver);

        if ($driver === 'mysql') {
            $pdo->exec("CREATE TABLE IF NOT EXISTS knowledge_comment_sources (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                organization_id BIGINT UNSIGNED NOT NULL,
                page_id BIGINT UNSIGNED NOT NULL,
                source_type VARCHAR(64) NOT NULL,
                source_id VARCHAR(255) NOT NULL,
                comment_id BIGINT UNSIGNED NOT NULL,
                created_at DATETIME NOT NULL,
                UNIQUE KEY uq_kb_comment_source (organization_id, page_id, source_type, source_id),
                UNIQUE KEY uq_kb_comment_source_comment (comment_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
            $seed = 'INSERT IGNORE INTO';
        } elseif ($driver === 'pgsql') {
            $pdo->exec("CREATE TABLE IF NOT EXISTS knowledge_comment_sources (
                id BIGSERIAL PRIMARY KEY, organization_id BIGINT NOT NULL, page_id BIGINT NOT NULL,
                source_type VARCHAR(64) NOT NULL, source_id VARCHAR(255) NOT NULL, comment_id BIGINT NOT NULL,
                created_at TIMESTAMP NOT NULL,
                CONSTRAINT uq_kb_comment_source UNIQUE (organization_id, page_id, source_type, source_id),
                CONSTRAINT uq_kb_comment_source_comment UNIQUE (comment_id)
            )");
            $seed = 'INSERT INTO';
        } elseif ($driver === 'sqlsrv') {
            $pdo->exec("IF OBJECT_ID('knowledge_comment_sources', 'U') IS NULL CREATE TABLE knowledge_comment_sources (
                id BIGINT IDENTITY(1,1) PRIMARY KEY, organization_id BIGINT NOT NULL, page_id BIGINT NOT NULL,
                source_type VARCHAR(64) NOT NULL, source_id VARCHAR(255) NOT NULL, comment_id BIGINT NOT NULL,
                created_at DATETIME2 NOT NULL,
                CONSTRAINT uq_kb_comment_source UNIQUE (organization_id, page_id, source_type, source_id),
                CONSTRAINT uq_kb_comment_source_comment UNIQUE (comment_id)
            )");
            $seed = '';
        } else {
            $pdo->exec("CREATE TABLE IF NOT EXISTS knowledge_comment_sources (
                id INTEGER PRIMARY KEY AUTOINCREMENT, organization_id INTEGER NOT NULL, page_id INTEGER NOT NULL,
                source_type VARCHAR(64) NOT NULL, source_id VARCHAR(255) NOT NULL, comment_id INTEGER NOT NULL,
                created_at DATETIME NOT NULL,
                CONSTRAINT uq_kb_comment_source UNIQUE (organization_id, page_id, source_type, source_id),
                CONSTRAINT uq_kb_comment_source_comment UNIQUE (comment_id)
            )");
            $seed = 'INSERT OR IGNORE INTO';
        }

        $columns = '(organization_id, page_id, source_type, source_id, comment_id, created_at)';
        $select = ' SELECT c.organization_id, c.page_id, c.source_type, c.source_id, MIN(c.id) AS comment_id, COALESCE(MIN(c.created_at), CURRENT_TIMESTAMP) AS created_at FROM knowledge_comments c WHERE c.organization_id IS NOT NULL AND c.source_type IS NOT NULL AND c.source_type <> \'\' AND c.source_id IS NOT NULL AND c.source_id <> \'\' GROUP BY c.organization_id, c.page_id, c.source_type, c.source_id';
        if ($driver === 'sqlsrv') {
            $pdo->exec("MERGE knowledge_comment_sources AS target USING ({$select}) AS source
                ON target.organization_id = source.organization_id AND target.page_id = source.page_id AND target.source_type = source.source_type AND target.source_id = source.source_id
                WHEN NOT MATCHED THEN INSERT (organization_id, page_id, source_type, source_id, comment_id, created_at)
                VALUES (source.organization_id, source.page_id, source.source_type, source.source_id, source.comment_id, source.created_at);");
            return;
        }
        $suffix = $driver === 'pgsql' ? ' ON CONFLICT DO NOTHING' : '';
        $pdo->exec($seed . ' knowledge_comment_sources ' . $columns . $select . $suffix);
    }

    private function ensureCommentSourceColumns(PDO $pdo, string $driver): void
    {
        $types = [
            'source_type' => 'VARCHAR(64) NULL',
            'source_id' => 'VARCHAR(255) NULL',
            'source_author_name' => 'VARCHAR(255) NULL',
            'source_created_at' => $driver === 'sqlsrv' ? 'DATETIME2 NULL' : 'DATETIME NULL',
            'anchor_text' => 'VARCHAR(500) NULL',
            'anchor_path' => 'VARCHAR(500) NULL',
            'is_inline' => $driver === 'mysql' ? 'TINYINT(1) NOT NULL DEFAULT 0' : 'INTEGER NOT NULL DEFAULT 0',
        ];
        $existing = [];
        if ($driver === 'sqlite') {
            foreach ($pdo->query('PRAGMA table_info(knowledge_comments)')->fetchAll(PDO::FETCH_ASSOC) ?: [] as $column) {
                $existing[strtolower((string)($column['name'] ?? ''))] = true;
            }
        } elseif ($driver === 'mysql') {
            $stmt = $pdo->prepare('SELECT COLUMN_NAME FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = :table');
            $stmt->execute(['table' => 'knowledge_comments']);
            foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) ?: [] as $column) $existing[strtolower((string)$column)] = true;
        } elseif ($driver === 'pgsql') {
            $stmt = $pdo->prepare('SELECT column_name FROM information_schema.columns WHERE table_schema = current_schema() AND table_name = :table');
            $stmt->execute(['table' => 'knowledge_comments']);
            foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) ?: [] as $column) $existing[strtolower((string)$column)] = true;
        } elseif ($driver === 'sqlsrv') {
            $stmt = $pdo->prepare('SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = SCHEMA_NAME() AND TABLE_NAME = :table');
            $stmt->execute(['table' => 'knowledge_comments']);
            foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) ?: [] as $column) $existing[strtolower((string)$column)] = true;
        } else {
            throw new \RuntimeException('Knowledge comment source metadata migration does not support this database driver');
        }
        if ($existing === []) throw new \RuntimeException('knowledge_comments table is required before comment source migration');

        foreach ($types as $column => $type) {
            if (isset($existing[$column])) continue;
            $pdo->exec('ALTER TABLE knowledge_comments ADD COLUMN ' . $column . ' ' . $type);
        }
    }

    /** Reversible because the source index is derived; comment rows remain untouched. */
    public function down(PDO $pdo, string $driver): void
    {
        $pdo->exec('DROP TABLE IF EXISTS knowledge_comment_sources');
    }
}
