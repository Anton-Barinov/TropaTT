<?php
declare(strict_types=1);

namespace Api\System\Library\Database\Migration;

use Api\System\Library\Database\IndexHelper;
use PDO;

final class KnowledgeEntityLinkUniquenessMigration implements MigrationInterface
{
    public function key(): string
    {
        return '20260806_000001_knowledge_entity_link_uniqueness';
    }

    public function description(): string
    {
        return 'Prevent duplicate knowledge entity links per page and entity';
    }

    public function up(PDO $pdo, string $driver): void
    {
        try {
            $groups = $pdo->query(
                'SELECT page_id, entity_type, entity_public_id, MIN(id) AS keep_id, COUNT(*) AS link_count
                 FROM knowledge_entity_links
                 GROUP BY page_id, entity_type, entity_public_id
                 HAVING COUNT(*) > 1'
            )->fetchAll(PDO::FETCH_ASSOC) ?: [];

            $delete = $pdo->prepare(
                'DELETE FROM knowledge_entity_links
                 WHERE page_id = :page_id
                   AND entity_type = :entity_type
                   AND entity_public_id = :entity_public_id
                   AND id <> :keep_id'
            );
            foreach ($groups as $group) {
                $delete->execute([
                    'page_id' => (int)$group['page_id'],
                    'entity_type' => (string)$group['entity_type'],
                    'entity_public_id' => (string)$group['entity_public_id'],
                    'keep_id' => (int)$group['keep_id'],
                ]);
            }

            IndexHelper::createIndexIfNotExists(
                $pdo,
                'knowledge_entity_links',
                'uq_knowledge_links_page_entity',
                'page_id, entity_type, entity_public_id',
                true,
                $driver
            );
            if (!$this->indexExists($pdo, $driver, 'knowledge_entity_links', 'uq_knowledge_links_page_entity')) {
                throw new \RuntimeException('Knowledge entity link uniqueness index was not created');
            }
        } catch (\Throwable $e) {
            error_log('[KnowledgeEntityLinkUniquenessMigration] ' . $e->getMessage());
            throw $e;
        }
    }

    private function indexExists(PDO $pdo, string $driver, string $table, string $index): bool
    {
        try {
            if ($driver === 'mysql') {
                $stmt = $pdo->prepare('SELECT 1 FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = :table AND index_name = :index LIMIT 1');
                $stmt->execute(['table' => $table, 'index' => $index]);
                return $stmt->fetchColumn() !== false;
            }
            if ($driver === 'pgsql') {
                $stmt = $pdo->prepare('SELECT 1 FROM pg_indexes WHERE schemaname = current_schema() AND tablename = :table AND indexname = :index LIMIT 1');
                $stmt->execute(['table' => $table, 'index' => $index]);
                return $stmt->fetchColumn() !== false;
            }
            $stmt = $pdo->query('PRAGMA index_list(' . $table . ')');
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
                if ((string)($row['name'] ?? '') === $index) {
                    return true;
                }
            }
        } catch (\Throwable $e) {
            error_log('[KnowledgeEntityLinkUniquenessMigration::indexExists] ' . $e->getMessage());
        }
        return false;
    }
}
