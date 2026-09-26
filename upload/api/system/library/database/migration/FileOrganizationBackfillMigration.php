<?php
declare(strict_types=1);

namespace Api\System\Library\Database\Migration;

use PDO;

/**
 * Backfill `files.organization_id` from the entity a file is attached to.
 *
 * Files uploaded through the API/MCP by a bearer token that had no active
 * workspace were written with organization_id = NULL, while the entity
 * (task/project/knowledge page) carried the real organization. Every
 * workspace-scoped file lookup then filtered the row out, so the file was
 * visible right after upload (the caller received it in the response) and
 * disappeared on the next page load, and could not be downloaded or deleted.
 *
 * Only rows whose organization_id is NULL are touched, and only from an entity
 * that itself has an organization — the migration is idempotent and never
 * overwrites an explicit value. Files with no linked entity (or a linked entity
 * that no longer exists) are left as-is; they stay reachable for their uploader.
 */
final class FileOrganizationBackfillMigration implements MigrationInterface
{
    public function key(): string
    {
        return '20260926_000001_file_organization_backfill';
    }

    public function description(): string
    {
        return 'Backfill files.organization_id from the linked task, project or knowledge page';
    }

    public function up(PDO $pdo, string $driver): void
    {
        $entities = [
            'task' => 'tasks',
            'project' => 'projects',
            'knowledge_page' => 'knowledge_pages',
        ];

        foreach ($entities as $entityType => $table) {
            if ($this->tableHasColumn($pdo, $table, 'organization_id') === false) {
                continue;
            }

            if ($driver === 'mysql') {
                // Reads a lot faster on big tables than the correlated subquery
                // used for the portable path below.
                $pdo->exec(
                    "UPDATE files f JOIN {$table} e ON e.public_id = f.entity_public_id "
                    . "SET f.organization_id = e.organization_id "
                    . "WHERE f.entity_type = " . $pdo->quote($entityType) . " "
                    . "AND f.organization_id IS NULL AND e.organization_id IS NOT NULL"
                );
                continue;
            }

            $stmt = $pdo->prepare(
                "UPDATE files SET organization_id = ("
                . "SELECT e.organization_id FROM {$table} e WHERE e.public_id = files.entity_public_id"
                . ") WHERE entity_type = :type AND organization_id IS NULL "
                . "AND EXISTS (SELECT 1 FROM {$table} e WHERE e.public_id = files.entity_public_id "
                . "AND e.organization_id IS NOT NULL)"
            );
            $stmt->execute(['type' => $entityType]);
        }
    }

    private function tableHasColumn(PDO $pdo, string $table, string $column): bool
    {
        try {
            $pdo->query("SELECT {$column} FROM {$table} LIMIT 0");
            return true;
        } catch (\Throwable) {
            return false;
        }
    }
}
