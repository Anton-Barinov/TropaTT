<?php
declare(strict_types=1);

namespace Api\Model\User;

use Api\System\Library\Database\Builder\QueryBuilder;
use PDO;

final class UserManagementRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function list(array $filters): array
    {
        $page = max(1, (int)($filters['page'] ?? 1));
        $limit = min(100, max(1, (int)($filters['limit'] ?? 20)));
        $offset = ($page - 1) * $limit;

        $items = $this->buildListQuery($filters)
            ->select(['public_id', 'login', 'email', 'full_name', 'locale', 'is_active', 'is_root', 'created_by_user_id', 'created_at', 'updated_at'])
            ->orderBy('created_at', 'DESC')
            ->limit($limit)
            ->offset($offset)
            ->get();

        $total = $this->buildListQuery($filters)->count();

        return [$items, $total, $page, $limit];
    }

    public function findByPublicId(string $publicId): ?array
    {
        return (new QueryBuilder($this->pdo))
            ->from('users')
            ->where('public_id', '=', $publicId)
            ->first();
    }

    public function findById(int $id): ?array
    {
        return (new QueryBuilder($this->pdo))
            ->from('users')
            ->where('id', '=', $id)
            ->first();
    }

    public function create(array $payload): int
    {
        return (new QueryBuilder($this->pdo))
            ->from('users')
            ->insertGetId($payload);
    }

    public function updateByPublicId(string $publicId, array $set): bool
    {
        if ($set === []) {
            return false;
        }

        return (new QueryBuilder($this->pdo))
            ->from('users')
            ->where('public_id', '=', $publicId)
            ->update($set) > 0;
    }

    public function softDelete(string $publicId, string $deletedAt): bool
    {
        return (new QueryBuilder($this->pdo))
            ->from('users')
            ->where('public_id', '=', $publicId)
            ->whereNull('deleted_at')
            ->update([
                'is_active' => 0,
                'deleted_at' => $deletedAt,
                'updated_at' => $deletedAt,
            ]) > 0;
    }

    public function roleCodesByUserId(int $userId): array
    {
        $rows = (new QueryBuilder($this->pdo))
            ->from('user_roles ur')
            ->join('roles r', 'r.id', '=', 'ur.role_id')
            ->select(['r.code'])
            ->where('ur.user_id', '=', $userId)
            ->get();

        return array_map(static fn(array $row): string => (string)$row['code'], $rows);
    }

    public function replaceRoles(int $userId, array $rolePublicIds): void
    {
        $this->pdo->beginTransaction();
        try {
            (new QueryBuilder($this->pdo))
                ->from('user_roles')
                ->where('user_id', '=', $userId)
                ->delete();

            if ($rolePublicIds !== []) {
                foreach ($rolePublicIds as $publicId) {
                    $roleId = (new QueryBuilder($this->pdo))
                        ->from('roles')
                        ->where('public_id', '=', $publicId)
                        ->value('id');
                    if ($roleId === false) {
                        continue;
                    }

                    (new QueryBuilder($this->pdo))
                        ->from('user_roles')
                        ->insert([
                        'user_id' => $userId,
                        'role_id' => (int)$roleId,
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
    }

    /** @return int[] */
    public function descendantIds(int $ancestorId): array
    {
        if ($ancestorId <= 0) {
            return [];
        }

        $rows = (new QueryBuilder($this->pdo))
            ->from('users')
            ->select(['id', 'created_by_user_id'])
            ->whereNull('deleted_at')
            ->get();
        $children = [];
        foreach ($rows as $row) {
            $id = (int)($row['id'] ?? 0);
            $parentId = $row['created_by_user_id'] !== null ? (int)$row['created_by_user_id'] : 0;
            if ($id <= 0 || $parentId <= 0) {
                continue;
            }
            $children[$parentId][] = $id;
        }

        $result = [];
        $stack = [$ancestorId];
        while ($stack !== []) {
            $current = array_pop($stack);
            if ($current === null || isset($result[$current])) {
                continue;
            }

            $result[$current] = true;
            foreach ($children[$current] ?? [] as $childId) {
                $stack[] = $childId;
            }
        }

        return array_map('intval', array_keys($result));
    }

    private function buildListQuery(array $filters): QueryBuilder
    {
        $qb = (new QueryBuilder($this->pdo))
            ->from('users')
            ->whereNull('deleted_at');

        if (!empty($filters['search'])) {
            $term = '%' . (string)$filters['search'] . '%';
            $qb->whereRaw('(login LIKE ? OR email LIKE ? OR full_name LIKE ?)', [$term, $term, $term]);
        }

        if (array_key_exists('is_active', $filters) && $filters['is_active'] !== '') {
            $qb->where('is_active', '=', (int)((string)$filters['is_active'] === '1'));
        }

        return $qb;
    }
}
