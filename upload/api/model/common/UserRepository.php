<?php
declare(strict_types=1);

namespace Api\Model\Common;

use Api\System\Library\Database\Builder\QueryBuilder;
use PDO;

final class UserRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function findByLogin(string $login): ?array
    {
        return (new QueryBuilder($this->pdo))
            ->from('users')
            // Explicit columns only: never SELECT * from users (AGENTS.md).
            ->select(['id', 'public_id', 'login', 'email', 'full_name', 'locale', 'is_active', 'is_root', 'password_hash', 'auth_token_hash'])
            ->where('login', '=', $login)
            ->whereNull('deleted_at')
            ->first();
    }

    public function findById(int $id): ?array
    {
        return (new QueryBuilder($this->pdo))
            ->from('users')
            // password_hash is required by TwoFactorService (current-password
            // verification) and AuthService 2FA flows. cost_rate/bill_rate stay
            // for self-service profile/me (sanitizeUser keeps them); all
            // findById callers operate on the actor's own id. Never SELECT *
            // (AGENTS.md) and never leak token/secret columns.
            ->select(['id', 'public_id', 'login', 'email', 'full_name', 'locale', 'is_active', 'is_root', 'is_external', 'password_hash', 'auth_token_hash', 'external_invitation_expires_at', 'deleted_at', 'cost_rate', 'bill_rate'])
            ->where('id', '=', $id)
            ->first();
    }

    public function findByEmail(string $email): ?array
    {
        return (new QueryBuilder($this->pdo))
            ->from('users')
            ->select(['id', 'public_id', 'login', 'email', 'full_name', 'is_active'])
            ->where('email', '=', $email)
            ->whereNull('deleted_at')
            ->first();
    }

    public function findByPublicId(string $publicId): ?array
    {
        return (new QueryBuilder($this->pdo))
            ->from('users')
            ->select(['id', 'public_id', 'login', 'full_name', 'is_active', 'is_external', 'deleted_at'])
            ->where('public_id', '=', $publicId)
            ->first();
    }

    /**
     * Find administrator users: root flag OR an admin-family role code
     * (admin/administrator/super_admin/super_administrator/root).
     * Used by the key-guard notification path; explicit columns only.
     *
     * @return array<int, array<string, mixed>>
     */
    public function findAdmins(): array
    {
        $rows = (new QueryBuilder($this->pdo))
            ->from('users')
            ->select(['id', 'public_id', 'login', 'full_name', 'email', 'is_active', 'is_root'])
            ->leftJoin('user_roles', 'users.id', '=', 'user_roles.user_id')
            ->leftJoin('roles', 'roles.id', '=', 'user_roles.role_id')
            ->whereNull('users.deleted_at')
            ->whereRaw('(users.is_root = 1 OR roles.code IN (?, ?, ?, ?, ?))', ['admin', 'administrator', 'super_admin', 'super_administrator', 'root'])
            ->get();

        // A user with several admin-family roles yields one row per role;
        // dedupe by user id so notifyAdminsOfMissingKeys never double-notifies.
        $seen = [];
        $admins = [];
        foreach ($rows as $row) {
            $id = (int)($row['id'] ?? 0);
            if ($id <= 0 || isset($seen[$id])) {
                continue;
            }
            $seen[$id] = true;
            $admins[] = $row;
        }

        return $admins;
    }

    public function create(array $payload): int
    {
        return (new QueryBuilder($this->pdo))
            ->from('users')
            ->insertGetId($payload);
    }

    public function findActiveUserIds(): array
    {
        $rows = (new QueryBuilder($this->pdo))
            ->from('users')
            ->where('is_active', '=', 1)
            ->whereNull('deleted_at')
            ->select(['id'])
            ->get();
        return array_map(fn(array $r) => (int)$r['id'], $rows);
    }

    public function findByAuthTokenHash(string $hash): ?array
    {
        return (new QueryBuilder($this->pdo))
            ->from('users')
            ->select([
                'id', 'public_id', 'login', 'email', 'full_name',
                'is_active', 'is_root', 'is_external', 'locale',
                'auth_token_hash', 'external_invitation_expires_at', 'created_at',
            ])
            ->where('auth_token_hash', '=', $hash)
            ->whereNull('deleted_at')
            ->first();
    }

    public function activateExternalInvitation(int $id, string $tokenHash, string $passwordHash, string $now): bool
    {
        if ($id <= 0 || $tokenHash === '' || $passwordHash === '') {
            return false;
        }

        return (new QueryBuilder($this->pdo))
            ->from('users')
            ->where('id', '=', $id)
            ->where('auth_token_hash', '=', $tokenHash)
            ->where('is_external', '=', 1)
            ->where('is_active', '=', 0)
            ->where('external_invitation_expires_at', '>', $now)
            ->update([
                'password_hash' => $passwordHash,
                'is_active' => 1,
                'auth_token_hash' => null,
                'external_invitation_expires_at' => null,
                'updated_at' => $now,
            ]) > 0;
    }

    public function updateById(int $id, array $set): bool
    {
        if ($set === []) {
            return false;
        }
        return (new QueryBuilder($this->pdo))
            ->from('users')
            ->where('id', '=', $id)
            ->update($set) > 0;
    }

    public function getPdo(): \PDO
    {
        return $this->pdo;
    }
}
