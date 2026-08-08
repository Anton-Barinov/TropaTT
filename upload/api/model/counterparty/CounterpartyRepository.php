<?php
declare(strict_types=1);

namespace Api\Model\Counterparty;

use Api\System\Library\Database\Builder\QueryBuilder;
use PDO;

final class CounterpartyRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function list(array $filters, ?array $typeFilter = null): array
    {
        // Fail-closed scope: keep the -1 sentinel from accessScope() so an actor
        // without a valid id (id <= 0) matches nothing instead of widening to
        // "no scope". Empty array = root (no restriction). See applyCreatorScope
        // in SearchRepository for the same convention.
        $creatorIds = is_array($filters['created_by_user_ids'] ?? null)
            ? array_values(array_unique(array_map('intval', $filters['created_by_user_ids'])))
            : [];

        $page = max(1, (int)($filters['page'] ?? 1));
        $limit = min(100, max(1, (int)($filters['limit'] ?? 20)));
        $offset = ($page - 1) * $limit;

        $total = $this->buildListQuery($filters, $creatorIds, $typeFilter)->count();
        $items = $this->buildListQuery($filters, $creatorIds, $typeFilter)
            ->orderBy(...$this->resolveSorting($filters))
            ->orderBy('cp.id', 'DESC')
            ->limit($limit)
            ->offset($offset)
            ->get();

        return [$items, $total, $page, $limit];
    }

    /** @param array<int,int> $creatorIds @param string[]|null $typeFilter */
    private function buildListQuery(array $filters, array $creatorIds, ?array $typeFilter = null): QueryBuilder
    {
        $query = (new QueryBuilder($this->pdo))
            ->from('counterparties cp');

        if (!empty($filters['search'])) {
            $search = '%' . $this->escapeLikeValue((string)$filters['search']) . '%';
            // ТЗ 6.5: кастомные поля (extra_attributes) участвуют в поиске.
            // User input is a literal substring, not a SQL LIKE pattern.
            $query->whereRaw('(cp.title LIKE ? OR cp.legal_name LIKE ? OR cp.tax_inn LIKE ? OR cp.email LIKE ? OR cp.phone LIKE ? OR cp.website LIKE ? OR cp.extra_attributes LIKE ?) ESCAPE \'\\\'', [$search, $search, $search, $search, $search, $search, $search]);
        }

        if (!empty($filters['extra_search'])) {
            $extraSearch = '%' . $this->escapeLikeValue((string)$filters['extra_search']) . '%';
            $query->whereRaw('cp.extra_attributes LIKE ? ESCAPE \'\\\'', [$extraSearch]);
        }

        if (!empty($filters['status'])) {
            $query->where('cp.status', '=', (string)$filters['status']);
        }

        if ($typeFilter !== null && $typeFilter !== []) {
            $placeholders = implode(',', array_fill(0, count($typeFilter), '?'));
            $query->whereRaw('cp.counterparty_type IN (' . $placeholders . ')', $typeFilter);
        } elseif (!empty($filters['counterparty_type'])) {
            $query->where('cp.counterparty_type', '=', (string)$filters['counterparty_type']);
        }

        if (!empty($filters['tax_inn'])) {
            $query->where('cp.tax_inn', '=', trim((string)$filters['tax_inn']));
        }

        $hasWebsite = $this->parseBoolFilter($filters['has_website'] ?? null);
        if ($hasWebsite === true) {
            $query->whereRaw("(cp.website IS NOT NULL AND TRIM(cp.website) <> '')");
        } elseif ($hasWebsite === false) {
            $query->whereRaw("(cp.website IS NULL OR TRIM(cp.website) = '')");
        }

        $createdFrom = $this->normalizeDateFilter($filters['created_from'] ?? null, false);
        if ($createdFrom !== null) {
            $query->whereRaw('cp.created_at >= ?', [$createdFrom]);
        }

        $createdTo = $this->normalizeDateFilter($filters['created_to'] ?? null, true);
        if ($createdTo !== null) {
            $query->whereRaw('cp.created_at <= ?', [$createdTo]);
        }

        if ($creatorIds !== []) {
            if (!empty($filters['include_unowned'])) {
                $placeholders = implode(',', array_fill(0, count($creatorIds), '?'));
                $query->whereRaw('(cp.created_by_user_id IS NULL OR cp.created_by_user_id IN (' . $placeholders . '))', $creatorIds);
            } else {
                $query->whereIn('cp.created_by_user_id', $creatorIds);
            }
        }

        return $query;
    }

    /**
     * @return array{0:string,1:string}
     */
    private function resolveSorting(array $filters): array
    {
        $sortBy = trim((string)($filters['sort_by'] ?? ''));
        $sortDir = strtoupper(trim((string)($filters['sort_dir'] ?? 'DESC')));
        if ($sortDir !== 'ASC') {
            $sortDir = 'DESC';
        }

        $allowed = [
            'title' => 'cp.title',
            'created_at' => 'cp.created_at',
            'updated_at' => 'cp.updated_at',
        ];
        $column = $allowed[$sortBy] ?? 'cp.created_at';

        return [$column, $sortDir];
    }

    private function escapeLikeValue(string $value): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value);
    }

    private function parseBoolFilter(mixed $value): ?bool
    {
        if ($value === null) {
            return null;
        }

        $normalized = strtolower(trim((string)$value));
        if ($normalized === '') {
            return null;
        }

        if (in_array($normalized, ['1', 'true', 'yes', 'y', 'on'], true)) {
            return true;
        }

        if (in_array($normalized, ['0', 'false', 'no', 'n', 'off'], true)) {
            return false;
        }

        return null;
    }

    private function normalizeDateFilter(mixed $value, bool $endOfDay): ?string
    {
        $raw = trim((string)$value);
        if ($raw === '') {
            return null;
        }

        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $raw) === 1) {
            return $raw . ($endOfDay ? ' 23:59:59' : ' 00:00:00');
        }

        $timestamp = strtotime($raw);
        if ($timestamp === false) {
            return null;
        }

        return gmdate('Y-m-d H:i:s', $timestamp);
    }

    public function findByPublicId(string $publicId): ?array
    {
        return (new QueryBuilder($this->pdo))
            ->from('counterparties cp')
            ->where('cp.public_id', '=', $publicId)
            ->first();
    }

    public function create(array $payload): void
    {
        (new QueryBuilder($this->pdo))
            ->from('counterparties')
            ->insert($payload);
    }

    public function updateByPublicId(string $publicId, array $set): bool
    {
        if ($set === []) {
            return false;
        }

        return (new QueryBuilder($this->pdo))
            ->from('counterparties')
            ->where('public_id', '=', $publicId)
            ->update($set) > 0;
    }

    public function deleteByPublicId(string $publicId): bool
    {
        return (new QueryBuilder($this->pdo))
            ->from('counterparties')
            ->where('public_id', '=', $publicId)
            ->delete() > 0;
    }
}
