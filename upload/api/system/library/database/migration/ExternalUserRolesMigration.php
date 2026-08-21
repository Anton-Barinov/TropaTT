<?php
declare(strict_types=1);

namespace Api\System\Library\Database\Migration;

use Api\System\Library\Database\IndexHelper;
use PDO;

/**
 * Migration: External user roles (observer / executor) + per-project access grants.
 *
 * Extends the client-portal external-user model (see ExternalUsersMigration) with:
 *   1. users.external_role — 'observer' (default; existing counterparty-wide RLS,
 *      unchanged behaviour) or 'executor' (a freelancer/contractor who is scoped
 *      to explicit per-project grants instead of an entire counterparty).
 *   2. external_user_project_access — explicit, revocable grants of a single
 *      project to a single executor user. This is the safe mechanism for a
 *      freelancer who works across multiple projects that may belong to
 *      different counterparties: each grant is auditable and narrow (one
 *      project), never widening to "everything for counterparty X" the way the
 *      observer's client_public_id scoping intentionally does.
 */
final class ExternalUserRolesMigration implements MigrationInterface
{
    public function key(): string
    {
        return '20260821_000001_external_user_roles';
    }

    public function description(): string
    {
        return 'External users: observer/executor role + per-project access grants for executors';
    }

    public function up(PDO $pdo, string $driver): void
    {
        $this->addColumnIfNotExists($pdo, $driver, 'users', 'external_role',
            "VARCHAR(20) NOT NULL DEFAULT 'observer'");

        try {
            IndexHelper::createIndexIfNotExists($pdo, 'users', 'idx_users_external_role', 'external_role');
        } catch (\Throwable $e) {
            error_log('[ExternalUserRolesMigration::up] CREATE INDEX idx_users_external_role: ' . $e->getMessage());
        }

        if ($driver === 'mysql') {
            $pdo->exec('CREATE TABLE IF NOT EXISTS external_user_project_access (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                user_id BIGINT UNSIGNED NOT NULL,
                project_id BIGINT UNSIGNED NOT NULL,
                granted_by_user_id BIGINT UNSIGNED NULL,
                created_at DATETIME NOT NULL,

                PRIMARY KEY (id),
                UNIQUE KEY uq_ext_user_project_access (user_id, project_id),
                KEY idx_ext_user_project_access_user (user_id),
                KEY idx_ext_user_project_access_project (project_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        } else {
            $pdo->exec('CREATE TABLE IF NOT EXISTS external_user_project_access (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL,
                project_id INTEGER NOT NULL,
                granted_by_user_id INTEGER NULL,
                created_at DATETIME NOT NULL
            )');
            IndexHelper::createIndexIfNotExists($pdo, 'external_user_project_access', 'uq_ext_user_project_access', 'user_id, project_id', true);
            IndexHelper::createIndexIfNotExists($pdo, 'external_user_project_access', 'idx_ext_user_project_access_user', 'user_id');
            IndexHelper::createIndexIfNotExists($pdo, 'external_user_project_access', 'idx_ext_user_project_access_project', 'project_id');
        }
    }

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
}
