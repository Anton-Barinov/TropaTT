<?php
declare(strict_types=1);

namespace Api\System\Library\Database\Migration;

use Api\System\Library\Database\IndexHelper;
use PDO;

/**
 * Migration: External Users (Client Portal)
 *
 * Adds support for external guest users (clients, freelancers, contractors)
 * who can access only projects/tasks linked to their parent counterparty.
 *
 * Changes:
 *   1. users.is_external — flag distinguishing external guests from internal users
 *   2. contacts.user_id — links a contact record to the external user account
 *   3. external_guest role — system role with limited permissions for external users
 *   4. External user permissions — task.manage, project.manage (scoped to the
 *      actor's own counterparty by RLS + the external_ok route allowlist)
 *   5. Indexes for efficient RLS filtering by counterparty_id
 */
final class ExternalUsersMigration implements MigrationInterface
{
    public function key(): string
    {
        return '20260819_000001_external_users';
    }

    public function description(): string
    {
        return 'External users (client portal): is_external flag, contacts.user_id link, external_guest role';
    }

    public function up(PDO $pdo, string $driver): void
    {
        // 1. Add is_external flag to users table
        $this->addColumnIfNotExists($pdo, $driver, 'users', 'is_external',
            $driver === 'sqlsrv' ? 'BIT NOT NULL DEFAULT 0' : 'TINYINT(1) NOT NULL DEFAULT 0');

        // 2. Add user_id to contacts table (links contact → external user)
        $this->addColumnIfNotExists($pdo, $driver, 'contacts', 'user_id',
            $driver === 'sqlsrv' ? 'INT NULL' : 'INT NULL');

        // 3. Create index for efficient lookup of contact by user_id
        try {
            IndexHelper::createIndexIfNotExists($pdo, 'contacts', 'idx_contacts_user_id', 'user_id');
        } catch (\Throwable $e) {
            error_log('[ExternalUsersMigration::up] CREATE INDEX idx_contacts_user_id: ' . $e->getMessage());
        }

        // 4. Create index for filtering users by is_external
        try {
            IndexHelper::createIndexIfNotExists($pdo, 'users', 'idx_users_is_external', 'is_external');
        } catch (\Throwable $e) {
            error_log('[ExternalUsersMigration::up] CREATE INDEX idx_users_is_external: ' . $e->getMessage());
        }

        // 5. Seed external_guest role and permissions
        $this->seedExternalGuestRole($pdo, $driver);
    }

    /**
     * Add a column to a table only if it does not already exist.
     */
    private function addColumnIfNotExists(PDO $pdo, string $driver, string $table, string $column, string $definition): void
    {
        if ($this->columnExists($pdo, $driver, $table, $column)) {
            return;
        }

        $sql = match ($driver) {
            'mysql', 'pgsql', 'sqlite' => sprintf('ALTER TABLE %s ADD COLUMN %s %s', $table, $column, $definition),
            'sqlsrv' => sprintf('ALTER TABLE %s ADD %s %s', $table, $column, $definition),
            default => sprintf('ALTER TABLE %s ADD COLUMN %s %s', $table, $column, $definition),
        };

        $pdo->exec($sql);
    }

    /**
     * Check if a column exists in a table.
     */
    private function columnExists(PDO $pdo, string $driver, string $table, string $column): bool
    {
        try {
            if ($driver === 'mysql') {
                $stmt = $pdo->prepare(
                    "SELECT COUNT(*) FROM information_schema.COLUMNS
                     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table AND COLUMN_NAME = :column"
                );
                $stmt->execute([':table' => $table, ':column' => $column]);
                return (int)$stmt->fetchColumn() > 0;
            }
            // For SQLite and others, try DESCRIBE / PRAGMA
            $result = $pdo->query("PRAGMA table_info({$table})");
            if ($result) {
                while ($row = $result->fetch(\PDO::FETCH_ASSOC)) {
                    if (($row['name'] ?? '') === $column) {
                        return true;
                    }
                }
            }
        } catch (\Throwable $e) {
            // If we can't check, assume it doesn't exist (safe for migration)
        }
        return false;
    }

    /**
     * Seed the external_guest system role with limited permissions.
     *
     * The codebase does not have separate "view"-only permission codes for
     * tasks/projects/files (see PermissionService::list() for the canonical
     * registry) — task.manage/project.manage gate both read and write API
     * routes. Granting them here is safe only because access is additionally
     * scoped two ways for every external actor:
     *   1. Row-Level Security in ProjectService/TaskService — list()/get()
     *      restrict results to the actor's own counterparty (client_public_id).
     *   2. The `external_ok` route allowlist enforced centrally in App::run()
     *      — only a small set of read/comment/upload routes are reachable by
     *      an is_external actor regardless of which permissions their role
     *      carries (see routes.php entries flagged 'external_ok' => true).
     * Permissions granted to external guests:
     *   - task.manage    — required by GET/POST /api/v1/tasks* (RLS + route
     *                      allowlist restrict this to their own tasks)
     *   - project.manage — required by GET /api/v1/projects* (RLS + route
     *                      allowlist restrict this to their own projects)
     *   - chat.use       — required by GET/POST /api/v1/chats* (external_ok
     *                      routes restrict to project_client chats only)
     */
    private function seedExternalGuestRole(PDO $pdo, string $driver): void
    {
        $now = gmdate('Y-m-d H:i:s');

        // Find or create the external_guest role
        $stmt = $pdo->prepare("SELECT id FROM roles WHERE code = 'external_guest'");
        $stmt->execute();
        $roleRow = $stmt->fetch(\PDO::FETCH_ASSOC);

        if ($roleRow) {
            $roleId = (int)$roleRow['id'];
        } else {
            $rolePublicId = 'rl_ext_guest_' . substr(md5('external_guest'), 0, 16);
            $pdo->prepare(
                "INSERT INTO roles (public_id, code, title, is_system, created_at, updated_at)
                 VALUES (:public_id, :code, :title, 1, :created_at, :updated_at)"
            )->execute([
                ':public_id' => $rolePublicId,
                ':code' => 'external_guest',
                ':title' => 'External Guest',
                ':created_at' => $now,
                ':updated_at' => $now,
            ]);
            $roleId = (int)$pdo->lastInsertId();
        }

        // Permissions to grant to external guests (real registry codes only —
        // see the class-level doc comment on why task.manage/project.manage
        // are used instead of made-up .view codes).
        // This block is idempotent: it ensures exactly these permissions are
        // linked, removing any stale/wrong ones that may have been seeded by an
        // earlier version of this migration (e.g. knowledge.view).
        $wantedPermissionCodes = [
            'task.manage',
            'project.manage',
            'chat.use',
        ];

        // Look up IDs for wanted permissions
        $wantedIds = [];
        foreach ($wantedPermissionCodes as $code) {
            $stmt = $pdo->prepare("SELECT id FROM permissions WHERE code = :code");
            $stmt->execute([':code' => $code]);
            $permRow = $stmt->fetch(\PDO::FETCH_ASSOC);
            if ($permRow) {
                $wantedIds[] = (int)$permRow['id'];
            }
        }

        if ($wantedIds === []) {
            return; // Target permissions don't exist yet — nothing to do
        }

        // Remove stale permissions that are not in the wanted set
        $placeholders = implode(',', array_fill(0, count($wantedIds), '?'));
        $pdo->prepare(
            "DELETE FROM role_permissions WHERE role_id = ? AND permission_id NOT IN ($placeholders)"
        )->execute(array_merge([$roleId], $wantedIds));

        // Ensure all wanted permissions are linked
        foreach ($wantedIds as $permId) {
            $stmt = $pdo->prepare(
                "SELECT id FROM role_permissions WHERE role_id = :role_id AND permission_id = :permission_id"
            );
            $stmt->execute([':role_id' => $roleId, ':permission_id' => $permId]);

            if (!$stmt->fetch()) {
                $pdo->prepare(
                    "INSERT INTO role_permissions (role_id, permission_id, created_at)
                     VALUES (:role_id, :permission_id, :created_at)"
                )->execute([
                    ':role_id' => $roleId,
                    ':permission_id' => $permId,
                    ':created_at' => $now,
                ]);
            }
        }
    }
}
