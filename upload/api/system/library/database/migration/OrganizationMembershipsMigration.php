<?php
declare(strict_types=1);

namespace Api\System\Library\Database\Migration;

use Api\System\Library\Database\IndexHelper;
use PDO;

final class OrganizationMembershipsMigration implements MigrationInterface
{
    public function key(): string
    {
        return '20260418_000003_organization_memberships';
    }

    public function description(): string
    {
        return 'Add organization memberships for workspace isolation baseline';
    }

    public function up(PDO $pdo, string $driver): void
    {
        $id = match ($driver) {
            'mysql' => 'INT AUTO_INCREMENT PRIMARY KEY',
            'pgsql' => 'SERIAL PRIMARY KEY',
            'sqlsrv' => 'INT IDENTITY(1,1) PRIMARY KEY',
            default => 'INTEGER PRIMARY KEY AUTOINCREMENT',
        };

        $dt = $driver === 'sqlsrv' ? 'DATETIME2' : 'DATETIME';

        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS organization_memberships ("
            . "id {$id}, "
            . "public_id VARCHAR(64) UNIQUE, "
            . "organization_id INTEGER NOT NULL, "
            . "user_id INTEGER NOT NULL, "
            . "role_code VARCHAR(32) NOT NULL, "
            . "created_at {$dt})"
        );

        try {
            IndexHelper::createIndexIfNotExists($pdo, 'organization_memberships', 'uq_org_membership_org_user', 'organization_id, user_id', true);
        } catch (\Throwable $e) {
            error_log('[OrganizationMembershipsMigration::up] CREATE UNIQUE: ' . $e->getMessage());
            // Some drivers do not support IF NOT EXISTS for index creation.
        }

        try {
            IndexHelper::createIndexIfNotExists($pdo, 'organization_memberships', 'idx_org_membership_user', 'user_id');
        } catch (\Throwable $e) {
            error_log('[OrganizationMembershipsMigration::up] CREATE INDEX: ' . $e->getMessage());
            // Some drivers do not support IF NOT EXISTS for index creation.
        }
    }
}
