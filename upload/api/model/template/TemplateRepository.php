<?php
declare(strict_types=1);

namespace Api\Model\Template;

use Api\System\Library\Database\Builder\QueryBuilder;
use PDO;
use Api\System\Library\Support\LikeEscaper;

final class TemplateRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function list(string $kind, array $filters): array
    {
        [$table] = $this->resolve($kind);
        $page = max(1, (int)($filters['page'] ?? 1));
        $limit = min(100, max(1, (int)($filters['limit'] ?? 20)));
        $offset = ($page - 1) * $limit;

        // Fail-closed scope: keep the -1 sentinel from accessScope() so an actor
        // without a valid id (id <= 0) matches nothing instead of widening to
        // "no scope". Empty array = root (no restriction). See applyCreatorScope
        // in SearchRepository for the same convention.
        $creatorIds = is_array($filters['created_by_user_ids'] ?? null)
            ? array_values(array_unique(array_map('intval', $filters['created_by_user_ids'])))
            : [];
        $total = $this->buildListQuery($table, $filters, $creatorIds)->count();
        $items = $this->buildListQuery($table, $filters, $creatorIds)
            ->select(['public_id', 'title', 'payload', 'is_active', 'created_by_user_id', 'created_at', 'updated_at'])
            ->orderBy('updated_at', 'DESC')
            ->orderBy('public_id', 'DESC')
            ->limit($limit)
            ->offset($offset)
            ->get();

        return [$items, $total, $page, $limit];
    }

    /** @param array<int,int> $creatorIds */
    private function buildListQuery(string $table, array $filters, array $creatorIds): QueryBuilder
    {
        $query = (new QueryBuilder($this->pdo))
            ->from($table);

        if (!empty($filters['search'])) {
            $query->where('title', 'LIKE', '%' . (string)$filters['search'] . '%');
        }

        if (isset($filters['is_active']) && $filters['is_active'] !== '') {
            $query->where('is_active', '=', ((int)$filters['is_active'] === 1) ? 1 : 0);
        }

        if ($creatorIds !== []) {
            if (!empty($filters['include_unowned'])) {
                $placeholders = implode(',', array_fill(0, count($creatorIds), '?'));
                $query->whereRaw('(created_by_user_id IS NULL OR created_by_user_id IN (' . $placeholders . '))', $creatorIds);
            } else {
                $query->whereIn('created_by_user_id', $creatorIds);
            }
        }

        return $query;
    }

    public function findByPublicId(string $kind, string $publicId): ?array
    {
        [$table] = $this->resolve($kind);
        $row = (new QueryBuilder($this->pdo))
            ->from($table)
            ->select(['public_id', 'title', 'payload', 'is_active', 'created_by_user_id', 'created_at', 'updated_at'])
            ->where('public_id', '=', $publicId)
            ->first();

        return $row ?: null;
    }

    public function create(string $kind, array $payload): void
    {
        [$table] = $this->resolve($kind);
        (new QueryBuilder($this->pdo))
            ->from($table)
            ->insert($payload);
    }

    public function updateByPublicId(string $kind, string $publicId, array $set): bool
    {
        [$table] = $this->resolve($kind);
        if ($set === []) {
            return false;
        }

        return (new QueryBuilder($this->pdo))
            ->from($table)
            ->where('public_id', '=', $publicId)
            ->update($set) > 0;
    }

    public function deleteByPublicId(string $kind, string $publicId): bool
    {
        [$table] = $this->resolve($kind);
        return (new QueryBuilder($this->pdo))
            ->from($table)
            ->where('public_id', '=', $publicId)
            ->delete() > 0;
    }

    /**
     * @return array{0:string}
     */
    public function insertEntity(string $kind, array $data): int
    {
        $table = match ($kind) {
            'task' => 'tasks',
            'project' => 'projects',
        };
        return (int)(new QueryBuilder($this->pdo))->from($table)->insertGetId($data);
    }

    private function resolve(string $kind): array
    {
        return match ($kind) {
            'task' => ['task_templates'],
            'project' => ['project_templates'],
            default => throw new \InvalidArgumentException('Unsupported template kind'),
        };
    }
}
