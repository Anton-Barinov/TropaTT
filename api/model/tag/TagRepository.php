<?php
declare(strict_types=1);

namespace Api\Model\Tag;

use Api\System\Library\Database\Builder\QueryBuilder;
use PDO;

final class TagRepository
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
            ->select(['public_id', 'code', 'title', 'color', 'description', 'created_at'])
            ->orderBy('created_at', 'ASC')
            ->limit($limit)
            ->offset($offset)
            ->get();

        return [$items, $total, $page, $limit];
    }

    private function buildListQuery(array $filters): QueryBuilder
    {
        $query = (new QueryBuilder($this->pdo))
            ->from('tags');

        if (!empty($filters['search'])) {
            $search = '%' . (string)$filters['search'] . '%';
            $query->whereRaw('(code LIKE ? OR title LIKE ?)', [$search, $search]);
        }

        return $query;
    }

    public function findByPublicId(string $publicId): ?array
    {
        return (new QueryBuilder($this->pdo))
            ->from('tags')
            ->where('public_id', '=', $publicId)
            ->first();
    }

    public function findByCode(string $code): ?array
    {
        return (new QueryBuilder($this->pdo))
            ->from('tags')
            ->where('code', '=', $code)
            ->first();
    }

    public function create(array $payload): void
    {
        (new QueryBuilder($this->pdo))
            ->from('tags')
            ->insert($payload);
    }

    public function updateByPublicId(string $publicId, array $set): bool
    {
        if ($set === []) {
            return false;
        }

        return (new QueryBuilder($this->pdo))
            ->from('tags')
            ->where('public_id', '=', $publicId)
            ->update($set) > 0;
    }

    public function deleteByPublicId(string $publicId): bool
    {
        return (new QueryBuilder($this->pdo))
            ->from('tags')
            ->where('public_id', '=', $publicId)
            ->delete() > 0;
    }

    public function assignToEntity(string $entityType, string $entityPublicId, int $tagId): void
    {
        $exists = (new QueryBuilder($this->pdo))
            ->from('entity_tags')
            ->select(['id'])
            ->where('entity_type', '=', $entityType)
            ->where('entity_public_id', '=', $entityPublicId)
            ->where('tag_id', '=', $tagId)
            ->exists();

        if ($exists) {
            return;
        }

        (new QueryBuilder($this->pdo))
            ->from('entity_tags')
            ->insert([
            'entity_type' => $entityType,
            'entity_public_id' => $entityPublicId,
            'tag_id' => $tagId,
            'created_at' => gmdate('Y-m-d H:i:s'),
        ]);
    }

    public function detachFromEntity(string $entityType, string $entityPublicId, int $tagId): bool
    {
        return (new QueryBuilder($this->pdo))
            ->from('entity_tags')
            ->where('entity_type', '=', $entityType)
            ->where('entity_public_id', '=', $entityPublicId)
            ->where('tag_id', '=', $tagId)
            ->delete() > 0;
    }

    public function listByEntity(string $entityType, string $entityPublicId): array
    {
        return (new QueryBuilder($this->pdo))
            ->from('tags t')
            ->join('entity_tags et', 'et.tag_id', '=', 't.id')
            ->select(['t.public_id', 't.code', 't.title', 't.color', 't.description', 't.created_at'])
            ->where('et.entity_type', '=', $entityType)
            ->where('et.entity_public_id', '=', $entityPublicId)
            ->orderBy('t.created_at', 'ASC')
            ->get();
    }
}
