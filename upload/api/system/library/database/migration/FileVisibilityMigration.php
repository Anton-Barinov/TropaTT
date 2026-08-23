<?php
declare(strict_types=1);

namespace Api\System\Library\Database\Migration;

use Api\System\Library\Database\IndexHelper;
use PDO;

/**
 * Migration: File Visibility (Internal Flag)
 *
 * Adds an is_internal flag to the files table so staff can mark
 * attachments as internal-only, hiding them from external (client-portal)
 * observers and executors. External users never see internal files.
 *
 * Changes:
 *   1. files.is_internal — flag (default 0 = client-visible)
 *   2. Index on is_internal for efficient filtering
 */
final class FileVisibilityMigration implements MigrationInterface
{
    public function key(): string
    {
        return '20260823_000001_file_visibility';
    }

    public function description(): string
    {
        return 'File visibility: is_internal flag to hide internal attachments from external users';
    }

    public function up(PDO $pdo, string $driver): void
    {
        // 1. Add is_internal flag to files table
        $this->addColumnIfNotExists($pdo, $driver, 'files', 'is_internal',
            $driver === 'sqlsrv' ? 'BIT NOT NULL DEFAULT 0' : 'TINYINT(1) NOT NULL DEFAULT 0');

        // 2. Create index for filtering by is_internal
        try {
            IndexHelper::createIndexIfNotExists($pdo, 'files', 'idx_files_is_internal', 'is_internal');
        } catch (\Throwable $e) {
            error_log('[FileVisibilityMigration::up] CREATE INDEX idx_files_is_internal: ' . $e->getMessage());
        }

        // 3. Backfill: mark existing files uploaded by staff (non-external users)
        //    as internal by default. External-uploaded files stay visible.
        //    We join users to determine which uploaders are external.
        try {
            $pdo->exec("
                UPDATE files f
                INNER JOIN users u ON u.id = f.uploader_user_id
                SET f.is_internal = 1
                WHERE u.is_external = 0 AND f.is_internal = 0
            ");
        } catch (\Throwable $e) {
            error_log('[FileVisibilityMigration::up] backfill is_internal: ' . $e->getMessage());
        }
    }

    private function addColumnIfNotExists(PDO $pdo, string $driver, string $table, string $column, string $definition): void
    {
        try {
            $existing = $pdo->query("SELECT * FROM {$table} LIMIT 0");
            if ($existing !== false) {
                $colCount = $existing->columnCount();
                for ($i = 0; $i < $colCount; $i++) {
                    $meta = $existing->getColumnMeta($i);
                    if ($meta !== false && isset($meta['name']) && $meta['name'] === $column) {
                        return; // column already exists
                    }
                }
            }
        } catch (\Throwable $e) {
            error_log('[FileVisibilityMigration] column check for ' . $table . '.' . $column . ': ' . $e->getMessage());
        }

        try {
            $pdo->exec("ALTER TABLE {$table} ADD COLUMN {$column} {$definition}");
        } catch (\Throwable $e) {
            error_log('[FileVisibilityMigration] ALTER TABLE ' . $table . ' ADD ' . $column . ': ' . $e->getMessage());
        }
    }
}