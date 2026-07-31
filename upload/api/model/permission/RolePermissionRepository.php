<?php
declare(strict_types=1);

namespace Api\Model\Permission;

use Api\System\Library\Database\Builder\QueryBuilder;
use PDO;

final class RolePermissionRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function roleIdByPublicId(string $rolePublicId): ?int
    {
        $row = (new QueryBuilder($this->pdo))
            ->from('roles')
            ->select(['id'])
            ->where('public_id', '=', $rolePublicId)
            ->first();

        return isset($row['id']) ? (int)$row['id'] : null;
    }

    public function permissionIdsByCodes(array $codes): array
    {
        if ($codes === []) {
            return [];
        }

        $rows = (new QueryBuilder($this->pdo))
            ->from('permissions')
            ->select(['id'])
            ->whereIn('code', array_values($codes))
            ->get();

        return array_map(static fn(array $row): int => (int)$row['id'], $rows);
    }

    public function codesByRolePublicId(string $rolePublicId): array
    {
        $rows = (new QueryBuilder($this->pdo))
            ->from('role_permissions rp')
            ->join('roles r', 'r.id', '=', 'rp.role_id')
            ->join('permissions p', 'p.id', '=', 'rp.permission_id')
            ->select(['p.code'])
            ->where('r.public_id', '=', $rolePublicId)
            ->orderBy('p.code', 'ASC')
            ->get();

        return array_map(static fn(array $row): string => (string)$row['code'], $rows);
    }

    public function replaceByRolePublicId(string $rolePublicId, array $permissionCodes): bool
    {
        $roleId = $this->roleIdByPublicId($rolePublicId);
        if (!$roleId) {
            return false;
        }

        $permissionIds = $this->permissionIdsByCodes($permissionCodes);

        $this->pdo->beginTransaction();
        try {
            (new QueryBuilder($this->pdo))
                ->from('role_permissions')
                ->where('role_id', '=', $roleId)
                ->delete();

            if ($permissionIds !== []) {
                foreach ($permissionIds as $permissionId) {
                    (new QueryBuilder($this->pdo))
                        ->from('role_permissions')
                        ->insert([
                        'role_id' => $roleId,
                        'permission_id' => $permissionId,
                        'created_at' => gmdate('Y-m-d H:i:s'),
                    ]);
                }
            }

            $this->pdo->commit();
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }

        return true;
    }

    public function permissionCodesByUserId(int $userId): array
    {
        $rows = (new QueryBuilder($this->pdo))
            ->from('user_roles ur')
            ->join('role_permissions rp', 'rp.role_id', '=', 'ur.role_id')
            ->join('permissions p', 'p.id', '=', 'rp.permission_id')
            ->select(['DISTINCT p.code AS code'])
            ->where('ur.user_id', '=', $userId)
            ->orderBy('p.code', 'ASC')
            ->get();

        return array_map(static fn(array $row): string => (string)$row['code'], $rows);
    }
}
