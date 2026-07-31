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

    public function create(array $payload): void
    {
        (new QueryBuilder($this->pdo))
            ->from('files')
            ->insert($payload);
    }

    public function findByPublicId(string $publicId): ?array
    {
        return (new QueryBuilder($this->pdo))
            ->from('files f')
            ->leftJoin('users u', 'u.id', '=', 'f.uploader_user_id')
            ->select([
                'f.*',
                'u.public_id AS uploader_public_id',
                'u.full_name AS uploader_name',
            ])
            ->where('f.public_id', '=', $publicId)
            ->first();
    }

    public function listByEntity(string $entityType, string $entityPublicId): array
    {
        return (new QueryBuilder($this->pdo))
            ->from('files f')
            ->leftJoin('users u', 'u.id', '=', 'f.uploader_user_id')
            ->select([
                'f.*',
                'u.public_id AS uploader_public_id',
                'u.full_name AS uploader_name',
            ])
            ->where('f.entity_type', '=', $entityType)
            ->where('f.entity_public_id', '=', $entityPublicId)
            ->where('f.is_deleted', '=', 0)
            ->orderBy('f.created_at', 'DESC')
            ->get();
    }

    public function softDelete(string $publicId, string $deletedAt): bool
    {
        return (new QueryBuilder($this->pdo))
            ->from('files')
            ->where('public_id', '=', $publicId)
            ->where('is_deleted', '=', 0)
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
