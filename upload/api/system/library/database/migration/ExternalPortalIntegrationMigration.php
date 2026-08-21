<?php
declare(strict_types=1);

namespace Api\System\Library\Database\Migration;

use PDO;

/**
 * Migration: External portal integration — knowledge base client visibility
 * and project_client chat type.
 *
 * Adds:
 *   1. knowledge_pages.client_visible TINYINT(1) DEFAULT 0 — when set, a page
 *      is visible to external (portal) users who have access to the linked project.
 *   2. No schema change for chats.type — it's already VARCHAR(32) and accepts
 *      any value; we just start using 'project_client' as a new type.
 */
final class ExternalPortalIntegrationMigration implements MigrationInterface
{
    public function key(): string
    {
        return '20260821_000003_external_portal_integration';
    }

    public function description(): string
    {
        return 'Add knowledge_pages.client_visible flag for portal visibility; no schema change needed for chats.type.';
    }

    public function up(PDO $pdo, string $driver): void
    {
        // 1. Add client_visible column to knowledge_pages (default 0 = not visible)
        $columns = $this->getColumnNames($pdo, 'knowledge_pages');
        if (!in_array('client_visible', $columns, true)) {
            $pdo->exec("ALTER TABLE knowledge_pages ADD COLUMN client_visible TINYINT(1) NOT NULL DEFAULT 0 AFTER views_count");
        }
    }

    public function down(PDO $pdo, string $driver): void
    {
        $columns = $this->getColumnNames($pdo, 'knowledge_pages');
        if (in_array('client_visible', $columns, true)) {
            $pdo->exec('ALTER TABLE knowledge_pages DROP COLUMN client_visible');
        }
    }

    /**
     * @return list<string>
     */
    private function getColumnNames(PDO $pdo, string $table): array
    {
        $driver = (string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        try {
            if ($driver === 'sqlite') {
                $result = $pdo->query("PRAGMA table_info({$table})");
                if ($result) {
                    return array_map(static fn(array $r): string => (string)($r['name'] ?? ''), $result->fetchAll(PDO::FETCH_ASSOC) ?: []);
                }
                return [];
            }
            $stmt = $pdo->prepare("SHOW COLUMNS FROM {$table}");
            $stmt->execute();
            return array_map(static fn(array $r): string => (string)($r['Field'] ?? ''), $stmt->fetchAll(PDO::FETCH_ASSOC) ?: []);
        } catch (\Throwable $e) {
            error_log('[ExternalPortalIntegrationMigration::getColumnNames] ' . $e->getMessage());
            return [];
        }
    }
}
