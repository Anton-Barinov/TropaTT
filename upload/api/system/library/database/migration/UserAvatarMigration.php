<?php
declare(strict_types=1);

namespace Api\System\Library\Database\Migration;

use PDO;

/**
 * Add avatar columns to `users`.
 *
 * Avatars are stored as a file under storage_api/avatars/ (outside the web
 * root) and served only through an authenticated API endpoint; the database
 * keeps the absolute path, the validated image MIME and the update time so a
 * cache-busting URL can be derived. Idempotent: each column is added only when
 * missing.
 */
final class UserAvatarMigration implements MigrationInterface
{
    public function key(): string
    {
        return '20260926_000002_user_avatar';
    }

    public function description(): string
    {
        return 'Add avatar_path, avatar_mime and avatar_updated_at columns to users';
    }

    public function up(PDO $pdo, string $driver): void
    {
        $columns = [
            'avatar_path' => $driver === 'mysql' ? 'VARCHAR(512) NULL' : 'TEXT NULL',
            'avatar_mime' => $driver === 'mysql' ? 'VARCHAR(64) NULL' : 'TEXT NULL',
            'avatar_updated_at' => 'DATETIME NULL',
        ];

        foreach ($columns as $name => $definition) {
            if ($this->tableHasColumn($pdo, 'users', $name)) {
                continue;
            }
            try {
                $pdo->exec("ALTER TABLE users ADD COLUMN {$name} {$definition}");
            } catch (\Throwable) {
                // A concurrent request may have added it first; ignore.
            }
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
