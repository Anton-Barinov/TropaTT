<?php
declare(strict_types=1);

namespace Api\Model\Feature_flag;

use Api\System\Library\Database\Builder\QueryBuilder;
use Api\System\Library\Support\Ulid;
use PDO;

final class FeatureFlagRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function list(array $filters): array
    {
        $page = max(1, (int)($filters['page'] ?? 1));
        $limit = min(200, max(1, (int)($filters['limit'] ?? 50)));
        $offset = ($page - 1) * $limit;

        $total = $this->buildListQuery($filters)->count();
        $items = $this->buildListQuery($filters)
            ->select(['public_id', 'code', 'is_enabled', 'payload', 'created_at', 'updated_at'])
            ->orderBy('code', 'ASC')
            ->limit($limit)
            ->offset($offset)
            ->get();

        return [$items, $total, $page, $limit];
    }

    private function buildListQuery(array $filters): QueryBuilder
    {
        $query = (new QueryBuilder($this->pdo))
            ->from('feature_flags');

        if (!empty($filters['search'])) {
            $query->where('code', 'LIKE', '%' . trim((string)$filters['search']) . '%');
        }
        if (array_key_exists('is_enabled', $filters) && $filters['is_enabled'] !== '') {
            $query->where('is_enabled', '=', (int)((string)$filters['is_enabled'] === '1'));
        }

        return $query;
    }

    public function findByPublicId(string $publicId): ?array
    {
        return (new QueryBuilder($this->pdo))
            ->from('feature_flags')
            ->where('public_id', '=', $publicId)
            ->first();
    }

    public function findByCode(string $code): ?array
    {
        return (new QueryBuilder($this->pdo))
            ->from('feature_flags')
            ->where('code', '=', $code)
            ->first();
    }

    public function create(string $code, bool $enabled, array $payload, string $now): array
    {
        (new QueryBuilder($this->pdo))
            ->from('feature_flags')
            ->insert([
            'public_id' => Ulid::generate('ffl'),
            'code' => $code,
            'is_enabled' => $enabled ? 1 : 0,
            'payload' => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return (array)$this->findByCode($code);
    }

    public function updateByPublicId(string $publicId, array $set): bool
    {
        if ($set === []) {
            return false;
        }

        return (new QueryBuilder($this->pdo))
            ->from('feature_flags')
            ->where('public_id', '=', $publicId)
            ->update($set) > 0;
    }
}
