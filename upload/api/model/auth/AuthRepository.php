<?php
declare(strict_types=1);

namespace Api\Model\Auth;

use Api\System\Library\Database\Builder\QueryBuilder;
use PDO;

final class AuthRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function createSession(array $payload): void
    {
        (new QueryBuilder($this->pdo))
            ->from('user_sessions')
            ->insert($payload);
    }

    public function findSessionByTokenHash(string $hash): ?array
    {
        return (new QueryBuilder($this->pdo))
            ->from('user_sessions us')
            ->join('users u', 'u.id', '=', 'us.user_id')
            ->select([
                'us.*',
                'u.public_id AS user_public_id',
                'u.login',
                'u.email',
                'u.full_name',
                'u.locale',
                'u.is_active',
                'u.is_root',
                'u.is_external',
                'u.external_role',
                'u.created_by_user_id',
            ])
            ->where('us.token_hash', '=', $hash)
            ->whereNull('us.revoked_at')
            ->where('us.expires_at', '>', gmdate('Y-m-d H:i:s'))
            ->first();
    }

    public function extendSessionByTokenHash(string $hash, string $expiresAt): bool
    {
        return (new QueryBuilder($this->pdo))
            ->from('user_sessions')
            ->where('token_hash', '=', $hash)
            ->whereNull('revoked_at')
            ->update(['expires_at' => $expiresAt]) > 0;
    }

    /** @return string[] */
    public function roleCodesByUserId(int $userId): array
    {
        if ($userId <= 0) {
            return [];
        }

        $rows = (new QueryBuilder($this->pdo))
            ->from('user_roles ur')
            ->join('roles r', 'r.id', '=', 'ur.role_id')
            ->select(['r.code'])
            ->where('ur.user_id', '=', $userId)
            ->orderBy('r.code', 'ASC')
            ->get();

        return array_values(array_unique(array_filter(array_map(
            static fn(array $row): string => trim((string)($row['code'] ?? '')),
            $rows
        ), static fn(string $code): bool => $code !== '')));
    }

    /** @return string[] */
    public function permissionCodesByUserId(int $userId): array
    {
        if ($userId <= 0) {
            return [];
        }

        $rows = (new QueryBuilder($this->pdo))
            ->from('user_roles ur')
            ->join('role_permissions rp', 'rp.role_id', '=', 'ur.role_id')
            ->join('permissions p', 'p.id', '=', 'rp.permission_id')
            ->select(['p.code'])
            ->where('ur.user_id', '=', $userId)
            ->orderBy('p.code', 'ASC')
            ->get();

        return array_values(array_unique(array_filter(array_map(
            static fn(array $row): string => trim((string)($row['code'] ?? '')),
            $rows
        ), static fn(string $code): bool => $code !== '')));
    }

    public function revokeByTokenHash(string $hash, string $revokedAt): bool
    {
        return (new QueryBuilder($this->pdo))
            ->from('user_sessions')
            ->where('token_hash', '=', $hash)
            ->whereNull('revoked_at')
            ->update(['revoked_at' => $revokedAt]) > 0;
    }

    public function revokeAllByUserId(int $userId, string $revokedAt): int
    {
        if ($userId <= 0) {
            return 0;
        }

        return (new QueryBuilder($this->pdo))
            ->from('user_sessions')
            ->where('user_id', '=', $userId)
            ->whereNull('revoked_at')
            ->update(['revoked_at' => $revokedAt]);
    }
}
