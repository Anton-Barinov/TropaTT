<?php
declare(strict_types=1);

namespace Api\Model\Custom_field;

use Api\System\Library\Database\Builder\QueryBuilder;
use PDO;
use Api\System\Library\Support\LikeEscaper;

final class CustomFieldRepository
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
            ->select(['public_id', 'scope', 'code', 'title', 'type', 'options', 'is_required', 'created_at', 'updated_at'])
            ->orderBy('updated_at', 'DESC')
            ->orderBy('public_id', 'DESC')
            ->limit($limit)
            ->offset($offset)
            ->get();

        return [$items, $total, $page, $limit];
    }

    private function buildListQuery(array $filters): QueryBuilder
    {
        $query = (new QueryBuilder($this->pdo))
            ->from('custom_fields');

        if (!empty($filters['scope'])) {
            $query->where('scope', '=', (string)$filters['scope']);
        }

        if (!empty($filters['type'])) {
            $query->where('type', '=', (string)$filters['type']);
        }

        if (!empty($filters['search'])) {
            $search = '%' . LikeEscaper::escape((string)$filters['search']) . '%';
            $query->whereRaw('(code LIKE ? OR title LIKE ?)', [$search, $search]);
        }

        return $query;
    }

    public function findByPublicId(string $publicId): ?array
    {
        $row = (new QueryBuilder($this->pdo))
            ->from('custom_fields')
            ->select(['id', 'public_id', 'scope', 'code', 'title', 'type', 'options', 'is_required', 'created_at', 'updated_at'])
            ->where('public_id', '=', $publicId)
            ->first();

        return $row ?: null;
    }

    public function findByScopeCode(string $scope, string $code): ?array
    {
        $row = (new QueryBuilder($this->pdo))
            ->from('custom_fields')
            ->select(['id', 'public_id', 'scope', 'code'])
            ->where('scope', '=', $scope)
            ->where('code', '=', $code)
            ->first();

        return $row ?: null;
    }

    public function create(array $payload): void
    {
        (new QueryBuilder($this->pdo))
            ->from('custom_fields')
            ->insert($payload);
    }

    public function updateByPublicId(string $publicId, array $set): bool
    {
        if ($set === []) {
            return false;
        }

        return (new QueryBuilder($this->pdo))
            ->from('custom_fields')
            ->where('public_id', '=', $publicId)
            ->update($set) > 0;
    }

    public function deleteByPublicId(string $publicId): bool
    {
        return (new QueryBuilder($this->pdo))
            ->from('custom_fields')
            ->where('public_id', '=', $publicId)
            ->delete() > 0;
    }

    public function valuesByEntity(string $entityType, string $entityPublicId): array
    {
        return (new QueryBuilder($this->pdo))
            ->from('custom_field_values v')
            ->join('custom_fields f', 'f.id', '=', 'v.field_id')
            ->select([
                'v.public_id',
                'v.entity_type',
                'v.entity_public_id',
                'v.value',
                'v.created_at',
                'v.updated_at',
                'f.public_id AS field_public_id',
                'f.scope',
                'f.code',
                'f.title',
                'f.type',
            ])
            ->where('v.entity_type', '=', $entityType)
            ->where('v.entity_public_id', '=', $entityPublicId)
            ->orderBy('f.scope', 'ASC')
            ->orderBy('f.code', 'ASC')
            ->get();
    }

    public function valueByFieldEntity(int $fieldId, string $entityType, string $entityPublicId): ?array
    {
        return (new QueryBuilder($this->pdo))
            ->from('custom_field_values')
            ->select(['id', 'public_id'])
            ->where('field_id', '=', $fieldId)
            ->where('entity_type', '=', $entityType)
            ->where('entity_public_id', '=', $entityPublicId)
            ->first();
    }

    public function createValue(array $payload): void
    {
        (new QueryBuilder($this->pdo))
            ->from('custom_field_values')
            ->insert($payload);
    }

    public function updateValueById(int $id, array $set): bool
    {
        if ($set === []) {
            return false;
        }

        return (new QueryBuilder($this->pdo))
            ->from('custom_field_values')
            ->where('id', '=', $id)
            ->update($set) > 0;
    }
}
