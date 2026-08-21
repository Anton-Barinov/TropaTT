<?php
declare(strict_types=1);

namespace Api\System\Library\Database\Migration;

use PDO;

/**
 * Add deleted_at column to projects table for soft-delete support.
 * The ExternalUserService references p.deleted_at in project-access queries.
 */
final class ProjectsDeletedAtMigration implements MigrationInterface
{
    public function key(): string
    {
        return '20260821_000004_projects_deleted_at';
    }

    public function description(): string
    {
        return 'Add deleted_at column to projects for soft-delete support';
    }

    public function up(PDO $pdo, string $driver): void
    {
        $columns = [];
        foreach ($pdo->query("SHOW COLUMNS FROM projects")->fetchAll(PDO::FETCH_ASSOC) as $col) {
            $columns[] = $col['Field'];
        }

        if (!in_array('deleted_at', $columns, true)) {
            $pdo->exec("ALTER TABLE projects ADD COLUMN deleted_at DATETIME DEFAULT NULL AFTER updated_at");
        }
    }
}
