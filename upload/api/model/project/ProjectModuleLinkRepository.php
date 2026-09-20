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

    public function listByModuleId(int $moduleId, ?int $organizationId = null): array
    {
        $qb = (new QueryBuilder($this->pdo))
            ->from('project_module_links')
            ->where('module_id', '=', $moduleId)
            ->whereNull('deleted_at')
            ->orderBy('sort_order', 'ASC')
            ->orderBy('created_at', 'DESC');

        if ($organizationId !== null && $organizationId > 0) {
            $qb->where('organization_id', '=', $organizationId);
        }

        return $qb->get();
    }

    public function create(array $payload): array
    {
        (new QueryBuilder($this->pdo))
            ->from('project_module_links')
            ->insert($payload);

        return $payload;
    }

    public function updateByPublicId(string $publicId, array $set, ?int $organizationId = null): bool
    {
        $qb = (new QueryBuilder($this->pdo))
            ->from('project_module_links')
            ->where('public_id', '=', $publicId)
            ->whereNull('deleted_at');

        if ($organizationId !== null && $organizationId > 0) {
            $qb->where('organization_id', '=', $organizationId);
        }

        return $qb->update($set) > 0;
    }

    public function softDeleteByPublicId(string $publicId, string $deletedAt, ?int $organizationId = null): bool
    {
        $qb = (new QueryBuilder($this->pdo))
            ->from('project_module_links')
            ->where('public_id', '=', $publicId);

        if ($organizationId !== null && $organizationId > 0) {
            $qb->where('organization_id', '=', $organizationId);
        }

        return $qb->update([
            'deleted_at' => $deletedAt,
            'updated_at' => $deletedAt,
        ]) > 0;
    }

    public function findByPublicId(string $publicId, ?int $organizationId = null): ?array
    {
        $qb = (new QueryBuilder($this->pdo))
            ->from('project_module_links')
            ->where('public_id', '=', $publicId)
            ->whereNull('deleted_at');

        if ($organizationId !== null && $organizationId > 0) {
            $qb->where('organization_id', '=', $organizationId);
        }

        return $qb->first();
    }
}
