<?php
declare(strict_types=1);

namespace Api\Model\File;

use Api\System\Library\Database\Builder\QueryBuilder;
use PDO;

final class FileRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function create(array $payload, ?int $organizationId = null): void
    {
        if ($organizationId !== null && $organizationId > 0) $payload['organization_id'] = $organizationId;
        (new QueryBuilder($this->pdo))
            ->from('files')
            ->insert($payload);
    }

    public function findByPublicId(string $publicId, ?int $organizationId = null): ?array
    {
        $query = (new QueryBuilder($this->pdo))
            ->from('files f')
            ->leftJoin('users u', 'u.id', '=', 'f.uploader_user_id')
            ->select([
                'f.*',
                'u.public_id AS uploader_public_id',
                'u.full_name AS uploader_name',
            ])
            ->where('f.public_id', '=', $publicId);
        if ($organizationId !== null && $organizationId > 0) $query->where('f.organization_id', '=', $organizationId);
        return $query->first();
    }

    public function listByEntity(string $entityType, string $entityPublicId, ?int $organizationId = null): array
    {
        $query = (new QueryBuilder($this->pdo))
            ->from('files f')
            ->leftJoin('users u', 'u.id', '=', 'f.uploader_user_id')
            ->select([
                'f.*',
                'u.public_id AS uploader_public_id',
                'u.full_name AS uploader_name',
            ])
            ->where('f.entity_type', '=', $entityType)
            ->where('f.entity_public_id', '=', $entityPublicId);
        if ($organizationId !== null && $organizationId > 0) $query->where('f.organization_id', '=', $organizationId);
        return $query
            ->where('f.is_deleted', '=', 0)
            ->orderBy('f.created_at', 'DESC')
            ->get();
    }

    public function softDelete(string $publicId, string $deletedAt, ?int $organizationId = null): bool
    {
        $query = (new QueryBuilder($this->pdo))
            ->from('files')
            ->where('public_id', '=', $publicId)
            ->where('is_deleted', '=', 0);
        if ($organizationId !== null && $organizationId > 0) $query->where('organization_id', '=', $organizationId);
        return $query
            ->update([
                'is_deleted' => 1,
                'deleted_at' => $deletedAt,
            ]) > 0;
    }

    public function restore(string $publicId): bool
    {
        return (new QueryBuilder($this->pdo))
            ->from('files')
            ->where('public_id', '=', $publicId)
            ->where('is_deleted', '=', 1)
            ->update([
                'is_deleted' => 0,
                'deleted_at' => null,
            ]) > 0;
    }

    public function hardDelete(string $publicId): bool
    {
        return (new QueryBuilder($this->pdo))
            ->from('files')
            ->where('public_id', '=', $publicId)
            ->delete() > 0;
    }
}
