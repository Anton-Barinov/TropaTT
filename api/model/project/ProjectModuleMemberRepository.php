<?php
declare(strict_types=1);

namespace Api\Model\Project;

use Api\System\Library\Database\Builder\QueryBuilder;
use PDO;

final class ProjectModuleMemberRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function listByModuleId(int $moduleId): array
    {
        return (new QueryBuilder($this->pdo))
            ->from('project_module_members pmm')
            ->leftJoin('users u', 'u.id', '=', 'pmm.user_id')
            ->select([
                'pmm.*',
                'u.public_id AS user_public_id',
                'u.full_name AS user_name',
                'u.email AS user_email',
                'u.avatar_url AS user_avatar',
            ])
            ->where('pmm.module_id', '=', $moduleId)
            ->whereNull('pmm.deleted_at')
            ->whereNull('u.deleted_at')
            ->orderBy('pmm.added_at', 'ASC')
            ->get();
    }

    public function addMember(array $payload): array
    {
        // Clear any existing active_key to prevent duplicate
        if (!empty($payload['active_key'])) {
            (new QueryBuilder($this->pdo))
                ->from('project_module_members')
                ->where('active_key', '=', $payload['active_key'])
                ->whereNull('deleted_at')
                ->update(['active_key' => null]);
        }

        (new QueryBuilder($this->pdo))
            ->from('project_module_members')
            ->insert($payload);

        return $payload;
    }

    public function removeMember(int $moduleId, int $userId, int $actorUserId, string $now): bool
    {
        return (new QueryBuilder($this->pdo))
            ->from('project_module_members')
            ->where('module_id', '=', $moduleId)
            ->where('user_id', '=', $userId)
            ->whereNull('deleted_at')
            ->update([
                'deleted_at' => $now,
                'removed_by_user_id' => $actorUserId,
                'removed_at' => $now,
                'updated_at' => $now,
                'active_key' => null,
            ]) > 0;
    }

    public function memberAlreadyExists(int $moduleId, int $userId): bool
    {
        $row = (new QueryBuilder($this->pdo))
            ->from('project_module_members')
            ->select(['id'])
            ->where('module_id', '=', $moduleId)
            ->where('user_id', '=', $userId)
            ->whereNull('deleted_at')
            ->first();

        return $row !== false;
    }

    public function userIdByPublicId(string $userPublicId): ?int
    {
        $row = (new QueryBuilder($this->pdo))
            ->from('users')
            ->select(['id'])
            ->where('public_id', '=', $userPublicId)
            ->whereNull('deleted_at')
            ->first();

        return isset($row['id']) ? (int)$row['id'] : null;
    }
}
