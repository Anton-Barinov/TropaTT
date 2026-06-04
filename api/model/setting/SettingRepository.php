<?php
declare(strict_types=1);

namespace Api\Model\Setting;

use Api\System\Library\Database\Builder\QueryBuilder;
use Api\System\Library\Support\Ulid;
use PDO;

final class SettingRepository
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
            ->select(['public_id', 'scope', 'name', 'value', 'created_at', 'updated_at'])
            ->orderBy('scope', 'ASC')
            ->orderBy('name', 'ASC')
            ->limit($limit)
            ->offset($offset)
            ->get();

        return [$items, $total, $page, $limit];
    }

    private function buildListQuery(array $filters): QueryBuilder
    {
        $query = (new QueryBuilder($this->pdo))
            ->from('settings');

        if (!empty($filters['scope'])) {
            $query->where('scope', '=', (string)$filters['scope']);
        }

        if (!empty($filters['search'])) {
            $query->where('name', 'LIKE', '%' . (string)$filters['search'] . '%');
        }

        return $query;
    }

    public function findByScopeAndName(string $scope, string $name): ?array
    {
        return (new QueryBuilder($this->pdo))
            ->from('settings')
            ->select(['public_id', 'scope', 'name', 'value', 'created_at', 'updated_at'])
            ->where('scope', '=', $scope)
            ->where('name', '=', $name)
            ->first();
    }

    public function upsert(string $scope, string $name, string $value, string $now): array
    {
        $existing = $this->findByScopeAndName($scope, $name);
        if ($existing) {
            (new QueryBuilder($this->pdo))
                ->from('settings')
                ->where('scope', '=', $scope)
                ->where('name', '=', $name)
                ->update([
                    'value' => $value,
                    'updated_at' => $now,
                ]);

            return $this->findByScopeAndName($scope, $name) ?? $existing;
        }

        (new QueryBuilder($this->pdo))
            ->from('settings')
            ->insert([
            'public_id' => Ulid::generate('set'),
            'scope' => $scope,
            'name' => $name,
            'value' => $value,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return $this->findByScopeAndName($scope, $name) ?? [];
    }
}
