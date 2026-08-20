<?php
declare(strict_types=1);

namespace Api\System\Library\Database\Migration;

use Api\System\Library\Database\IndexHelper;
use PDO;

/**
 * Adds server-side expiry metadata for one-time external portal invitations.
 */
final class ExternalInvitationLifecycleMigration implements MigrationInterface
{
    public function key(): string
    {
        return '20260820_000002_external_invitation_lifecycle';
    }

    public function description(): string
    {
        return 'External users: invitation expiry metadata and lookup index';
    }

    public function up(PDO $pdo, string $driver): void
    {
        $definition = match ($driver) {
            'sqlsrv' => 'DATETIME2 NULL',
            default => 'DATETIME NULL',
        };

        IndexHelper::addColumnIfNotExists(
            $pdo,
            'users',
            'external_invitation_expires_at',
            $definition,
            $driver
        );

        IndexHelper::createIndexIfNotExists(
            $pdo,
            'users',
            'idx_users_external_invitation_expiry',
            'is_external, external_invitation_expires_at',
            false,
            $driver
        );
    }
}
