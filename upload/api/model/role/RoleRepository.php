<?php
declare(strict_types=1);

namespace Api\Model\Role;

use Api\System\Library\Database\Builder\QueryBuilder;
use PDO;
use Api\System\Library\Support\LikeEscaper;

final class RoleRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function list(array $filters): array
    {
        $query = (new QueryBuilder($this->pdo))
            ->from('roles')
            ->select(['public_id', 'code', 'title', 'is_system', 'created_at', 'updated_at']);

        if (!empty($filters['search'])) {
            $needle = '%' . LikeEscaper::escape((string)$filters['search']) . '%';
            $query->whereRaw('(code LIKE ? OR title LIKE ?)', [$needle, $needle]);
        }

        return $query
            ->orderBy('is_system', 'DESC')
            ->orderBy('code', 'ASC')
            ->get();
    }

    public function findByPublicId(string $publicId): ?array
    {
        return (new QueryBuilder($this->pdo))
            ->from('roles')
            ->where('public_id', '=', $publicId)
            ->first();
    }

    public function findByCode(string $code): ?array
    {
        return (new QueryBuilder($this->pdo))
            ->from('roles')
            ->where('code', '=', $code)
            ->first();
    }

    public function create(array $payload): void
    {
        (new QueryBuilder($this->pdo))
            ->from('roles')
            ->insert($payload);
    }

    public function updateByPublicId(string $publicId, array $set): bool
    {
        if ($set === []) {
            return false;
        }

        return (new QueryBuilder($this->pdo))
            ->from('roles')
            ->where('public_id', '=', $publicId)
            ->update($set) > 0;
    }

    public function deleteByPublicId(string $publicId): bool
    {
        return (new QueryBuilder($this->pdo))
            ->from('roles')
            ->where('public_id', '=', $publicId)
            ->delete() > 0;
    }

    public function roleHasUsers(int $roleId): bool
    {
        return (new QueryBuilder($this->pdo))
            ->from('user_roles')
            ->where('role_id', '=', $roleId)
            ->count() > 0;
    }

    public function assignToUser(int $userId, int $roleId): void
    {
        // Check if already assigned
        $existing = (new QueryBuilder($this->pdo))
            ->from('user_roles')
            ->where('user_id', '=', $userId)
            ->where('role_id', '=', $roleId)
            ->first();
        if ($existing) {
            return;
        }
        $now = gmdate('Y-m-d H:i:s');
        (new QueryBuilder($this->pdo))
            ->from('user_roles')
            ->insert([
                'user_id' => $userId,
                'role_id' => $roleId,
                'created_at' => $now,
            ]);
    }

    /**
     * INST-3 fix: find a suitable default role for newly created users.
     * Returns the first non-system role that has at least one permission,
     * or null if no such role exists.
     */
    public function findDefaultRole(): ?array
    {
        return (new QueryBuilder($this->pdo))
            ->from('roles r')
            ->select(['r.public_id', 'r.code', 'r.title'])
            ->where('r.is_system', '=', 0)
            ->whereRaw('EXISTS (SELECT 1 FROM role_permissions rp WHERE rp.role_id = r.id)')
            ->orderBy('r.code', 'ASC')
            ->first();
    }
}
