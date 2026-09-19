<?php
declare(strict_types=1);

namespace Api\Model\Recycle_bin;

use Api\System\Library\Database\Builder\QueryBuilder;
use PDO;

final class RecycleBinRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function list(array $filters): array
    {
        $page = max(1, (int)($filters['page'] ?? 1));
        $limit = min(100, max(1, (int)($filters['limit'] ?? 20)));
        $offset = ($page - 1) * $limit;

        $total = $this->buildListQuery($filters)->count();
        $items = $this->buildListQuery($filters)
            ->select([
                'rb.public_id',
                'rb.entity_type',
                'rb.entity_public_id',
                'rb.payload',
                'rb.deleted_at',
                'rb.restored_at',
                'u.public_id AS deleted_by_user_public_id',
                'u.login AS deleted_by_login',
                'u.full_name AS deleted_by_full_name',
            ])
            ->orderBy('rb.deleted_at', 'DESC')
            ->orderBy('rb.public_id', 'DESC')
            ->limit($limit)
            ->offset($offset)
            ->get();

        return [$items, $total, $page, $limit];
    }

    private function buildListQuery(array $filters): QueryBuilder
    {
        $query = (new QueryBuilder($this->pdo))
            ->from('recycle_bin rb')
            ->leftJoin('users u', 'u.id', '=', 'rb.deleted_by_user_id');

        if (($filters['restored'] ?? '0') !== '1') {
            $query->whereNull('rb.restored_at');
        }

        if (!empty($filters['entity_type'])) {
            $query->where('rb.entity_type', '=', (string)$filters['entity_type']);
        }

        if (!empty($filters['entity_public_id'])) {
            $query->where('rb.entity_public_id', '=', (string)$filters['entity_public_id']);
        }

        if (isset($filters['organization_id']) && (int)$filters['organization_id'] > 0) {
            $query->where('rb.organization_id', '=', (int)$filters['organization_id']);
        }

        return $query;
    }

    public function findByPublicId(string $publicId, ?int $organizationId = null): ?array
    {
        $query = (new QueryBuilder($this->pdo))
            ->from('recycle_bin rb')
            ->leftJoin('users u', 'u.id', '=', 'rb.deleted_by_user_id')
            ->select([
                'rb.id',
                'rb.public_id',
                'rb.entity_type',
                'rb.entity_public_id',
                'rb.payload',
                'rb.deleted_at',
                'rb.restored_at',
                'u.public_id AS deleted_by_user_public_id',
                'u.login AS deleted_by_login',
                'u.full_name AS deleted_by_full_name',
            ])
            ->where('rb.public_id', '=', $publicId);
        if ($organizationId !== null && $organizationId > 0) {
            $query->where('rb.organization_id', '=', $organizationId);
        }
        return $query->first();
    }

    public function findActiveByEntity(string $entityType, string $entityPublicId, ?int $organizationId = null): ?array
    {
        $query = (new QueryBuilder($this->pdo))
            ->from('recycle_bin')
            ->select(['id', 'public_id', 'entity_type', 'entity_public_id', 'payload', 'deleted_at', 'restored_at'])
            ->where('entity_type', '=', $entityType)
            ->where('entity_public_id', '=', $entityPublicId)
            ->whereNull('restored_at');
        if ($organizationId !== null && $organizationId > 0) {
            $query->where('organization_id', '=', $organizationId);
        }
        return $query->first();
    }

    public function create(array $payload, ?int $organizationId = null): void
    {
        if ($organizationId !== null && $organizationId > 0) {
            $payload['organization_id'] = $organizationId;
        }
        (new QueryBuilder($this->pdo))
            ->from('recycle_bin')
            ->insert($payload);
    }

    public function markRestoredByPublicId(string $publicId, string $restoredAt, ?int $organizationId = null): bool
    {
        $query = (new QueryBuilder($this->pdo))
            ->from('recycle_bin')
            ->where('public_id', '=', $publicId)
            ->whereNull('restored_at');
        if ($organizationId !== null && $organizationId > 0) {
            $query->where('organization_id', '=', $organizationId);
        }
        return $query->update(['restored_at' => $restoredAt]) > 0;
    }

    public function deleteByPublicId(string $publicId, ?int $organizationId = null): bool
    {
        $query = (new QueryBuilder($this->pdo))
            ->from('recycle_bin')
            ->where('public_id', '=', $publicId);
        if ($organizationId !== null && $organizationId > 0) {
            $query->where('organization_id', '=', $organizationId);
        }
        return $query->delete() > 0;
    }
}
