<?php
declare(strict_types=1);

namespace Api\System\Library\Database\Migration;

/**
 * Add deleted_at column to projects table for soft-delete support.
 * The ExternalUserService references p.deleted_at in project-access queries.
 */
final class ProjectsDeletedAtMigration implements MigrationInterface
{
    public function getDescription(): string
    {
        return 'Add deleted_at column to projects for soft-delete support';
    }

    public function up(\PDO $pdo, string $driver): void
    {
        $columns = [];
        foreach ($pdo->query("SHOW COLUMNS FROM projects")->fetchAll(\PDO::FETCH_ASSOC) as $col) {
            $columns[] = $col['Field'];
        }

        if (!in_array('deleted_at', $columns, true)) {
            $pdo->exec("ALTER TABLE projects ADD COLUMN deleted_at DATETIME DEFAULT NULL AFTER updated_at");
        }
    }

    public function down(\PDO $pdo, string $driver): void
    {
        $columns = [];
        foreach ($pdo->query("SHOW COLUMNS FROM projects")->fetchAll(\PDO::FETCH_ASSOC) as $col) {
            $columns[] = $col['Field'];
        }

        if (in_array('deleted_at', $columns, true)) {
            $pdo->exec("ALTER TABLE projects DROP COLUMN deleted_at");
        }
    }
}
