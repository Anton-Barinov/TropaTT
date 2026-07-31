<?php
declare(strict_types=1);

namespace Api\Model\Project;

use Api\System\Library\Database\Builder\QueryBuilder;
use PDO;

final class ProjectModuleLinkRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function listByModuleId(int $moduleId): array
    {
        return (new QueryBuilder($this->pdo))
            ->from('project_module_links')
            ->where('module_id', '=', $moduleId)
            ->whereNull('deleted_at')
            ->orderBy('sort_order', 'ASC')
            ->orderBy('created_at', 'DESC')
            ->get();
    }

    public function create(array $payload): array
    {
        (new QueryBuilder($this->pdo))
            ->from('project_module_links')
            ->insert($payload);

        return $payload;
    }

    public function updateByPublicId(string $publicId, array $set): bool
    {
        return (new QueryBuilder($this->pdo))
            ->from('project_module_links')
            ->where('public_id', '=', $publicId)
            ->whereNull('deleted_at')
            ->update($set) > 0;
    }

    public function softDeleteByPublicId(string $publicId, string $deletedAt): bool
    {
        return (new QueryBuilder($this->pdo))
            ->from('project_module_links')
            ->where('public_id', '=', $publicId)
            ->update([
                'deleted_at' => $deletedAt,
                'updated_at' => $deletedAt,
            ]) > 0;
    }

    public function findByPublicId(string $publicId): ?array
    {
        $row = (new QueryBuilder($this->pdo))
            ->from('project_module_links')
            ->where('public_id', '=', $publicId)
            ->whereNull('deleted_at')
            ->first();

        return $row;
    }
}
