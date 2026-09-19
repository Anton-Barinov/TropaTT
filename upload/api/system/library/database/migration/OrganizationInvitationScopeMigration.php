<?php
declare(strict_types=1);

namespace Api\System\Library\Database\Migration;

use Api\System\Library\Database\IndexHelper;
use PDO;

/** Adds an optional organization boundary to the new invitation lifecycle. */
final class OrganizationInvitationScopeMigration implements MigrationInterface
{
    public function key(): string
    {
        return '20260918_000002_organization_invitation_scope';
    }

    public function description(): string
    {
        return 'Add organization scope, role and revocation metadata to invitations';
    }

    public function up(PDO $pdo, string $driver): void
    {
        if (!$this->tableExists($pdo, $driver, 'invitations')) {
            return;
        }

        $dt = $driver === 'sqlsrv' ? 'DATETIME2 NULL' : 'DATETIME NULL';
        IndexHelper::addColumnIfNotExists($pdo, 'invitations', 'organization_id', 'INTEGER NULL', $driver);
        IndexHelper::addColumnIfNotExists($pdo, 'invitations', 'role_code', "VARCHAR(32) NULL", $driver);
        IndexHelper::addColumnIfNotExists($pdo, 'invitations', 'revoked_at', $dt, $driver);

        // Existing security invitations remain valid.  Where an inviter has a
        // membership, associate the row with that workspace; otherwise use the
        // oldest workspace as the deterministic legacy fallback.
        if ($this->columnExists($pdo, $driver, 'invitations', 'organization_id')) {
            // MIN avoids driver-specific UPDATE-alias/LIMIT syntax and gives
            // repeatable results when an inviter belongs to several workspaces.
            if ($this->columnExists($pdo, $driver, 'invitations', 'invited_by_user_id')) {
                $pdo->exec(
                    'UPDATE invitations SET organization_id = ('
                    . 'SELECT MIN(om.organization_id) FROM organization_memberships om '
                    . 'WHERE om.user_id = invitations.invited_by_user_id'
                    . ') WHERE organization_id IS NULL'
                );
            }
            $default = $pdo->query('SELECT id FROM organizations ORDER BY id ASC LIMIT 1')->fetchColumn();
            if ($default !== false && $default !== null) {
                $stmt = $pdo->prepare('UPDATE invitations SET organization_id = :organization_id WHERE organization_id IS NULL');
                $stmt->execute(['organization_id' => (int)$default]);
            }
        }
        if ($this->columnExists($pdo, $driver, 'invitations', 'role_code')) {
            $pdo->exec("UPDATE invitations SET role_code = 'member' WHERE role_code IS NULL OR role_code = ''");
        }
        IndexHelper::createIndexIfNotExists($pdo, 'invitations', 'idx_invitations_organization_status', 'organization_id, accepted_at, revoked_at', false, $driver);
        IndexHelper::createIndexIfNotExists($pdo, 'invitations', 'idx_invitations_token_hash', 'token_hash', false, $driver);
    }

    private function tableExists(PDO $pdo, string $driver, string $table): bool
    {
        if ($driver === 'sqlite') {
            $stmt = $pdo->prepare("SELECT 1 FROM sqlite_master WHERE type = 'table' AND name = :table LIMIT 1");
            $stmt->execute(['table' => $table]);
            return (bool)$stmt->fetchColumn();
        }
        $stmt = $pdo->prepare('SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = :table LIMIT 1');
        if ($driver === 'pgsql') {
            $stmt = $pdo->prepare("SELECT 1 FROM information_schema.tables WHERE table_schema = current_schema() AND table_name = :table LIMIT 1");
        } elseif ($driver === 'sqlsrv') {
            $stmt = $pdo->prepare("SELECT TOP 1 1 FROM sys.objects WHERE name = :table AND type = 'U'");
        }
        $stmt->execute(['table' => $table]);
        return (bool)$stmt->fetchColumn();
    }

    private function columnExists(PDO $pdo, string $driver, string $table, string $column): bool
    {
        return IndexHelper::columnExists($pdo, $driver, $table, $column);
    }
}
