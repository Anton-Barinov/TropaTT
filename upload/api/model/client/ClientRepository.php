<?php
declare(strict_types=1);

namespace Api\Model\Client;

use Api\System\Library\Database\Builder\QueryBuilder;
use PDO;
use Api\System\Library\Support\LikeEscaper;

final class ClientRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function list(array $filters): array
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

        $total = $this->buildListQuery($filters, $creatorIds)->count();
        $items = $this->buildListQuery($filters, $creatorIds)
            ->select([
                'c.public_id',
                'c.company_id',
                'c.title',
                'c.client_type',
                'c.legal_name',
                'c.person_last_name',
                'c.person_first_name',
                'c.person_middle_name',
                'c.person_birth_date',
                'c.tax_inn',
                'c.tax_kpp',
                'c.tax_ogrn',
                'c.tax_ogrnip',
                'c.bank_account',
                'c.bank_name',
                'c.bank_bik',
                'c.bank_corr_account',
                'c.website',
                'c.messenger',
                'c.address_legal',
                'c.address_postal',
                'c.notes',
                'c.email',
                'c.phone',
                'c.status',
                'c.extra_attributes',
                'c.created_by_user_id',
                'c.created_at',
                'c.updated_at',
                'co.public_id AS company_public_id',
                'co.title AS company_title',
            ])
            ->orderBy(...$this->resolveSorting($filters))
            ->orderBy('c.id', 'DESC')
            ->limit($limit)
            ->offset($offset)
            ->get();

        return [$items, $total, $page, $limit];
    }

    /** @param array<int,int> $creatorIds */
    private function buildListQuery(array $filters, array $creatorIds): QueryBuilder
    {
        $query = (new QueryBuilder($this->pdo))
            ->from('clients c')
            ->leftJoin('companies co', 'co.id', '=', 'c.company_id');

        if (!empty($filters['search'])) {
            $search = '%' . LikeEscaper::escape((string)$filters['search']) . '%';
            $query->whereRaw('(c.title LIKE ? OR c.legal_name LIKE ? OR c.tax_inn LIKE ? OR c.email LIKE ? OR c.phone LIKE ? OR c.website LIKE ?)', [$search, $search, $search, $search, $search, $search]);
        }

        if (!empty($filters['status'])) {
            $query->where('c.status', '=', (string)$filters['status']);
        }

        if (!empty($filters['client_type'])) {
            $query->where('c.client_type', '=', (string)$filters['client_type']);
        }

        if (!empty($filters['tax_inn'])) {
            $query->where('c.tax_inn', '=', trim((string)$filters['tax_inn']));
        }

        $hasWebsite = $this->parseBoolFilter($filters['has_website'] ?? null);
        if ($hasWebsite === true) {
            $query->whereRaw("(c.website IS NOT NULL AND TRIM(c.website) <> '')");
        } elseif ($hasWebsite === false) {
            $query->whereRaw("(c.website IS NULL OR TRIM(c.website) = '')");
        }

        $createdFrom = $this->normalizeDateFilter($filters['created_from'] ?? null, false);
        if ($createdFrom !== null) {
            $query->whereRaw('c.created_at >= ?', [$createdFrom]);
        }

        $createdTo = $this->normalizeDateFilter($filters['created_to'] ?? null, true);
        if ($createdTo !== null) {
            $query->whereRaw('c.created_at <= ?', [$createdTo]);
        }

        if (!empty($filters['company_public_id'])) {
            $query->where('co.public_id', '=', (string)$filters['company_public_id']);
        }

        if ($creatorIds !== []) {
            if (!empty($filters['include_unowned'])) {
                $placeholders = implode(',', array_fill(0, count($creatorIds), '?'));
                $query->whereRaw('(c.created_by_user_id IS NULL OR c.created_by_user_id IN (' . $placeholders . '))', $creatorIds);
            } else {
                $query->whereIn('c.created_by_user_id', $creatorIds);
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
            'title' => 'c.title',
            'created_at' => 'c.created_at',
            'updated_at' => 'c.updated_at',
        ];
        $column = $allowed[$sortBy] ?? 'c.created_at';

        return [$column, $sortDir];
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

    /**
     * @return array{duplicates:array<int,array<string,mixed>>,summary:array<string,mixed>}
     */
    public function duplicatesReport(): array
    {
        $rows = (new QueryBuilder($this->pdo))
            ->from('clients c')
            ->select([
                'c.public_id',
                'c.title',
                'c.client_type',
                'c.email',
                'c.phone',
                'c.tax_inn',
                'c.created_at',
            ])
            ->orderBy('c.created_at', 'DESC')
            ->get();

        $groups = [];
        foreach ($rows as $row) {
            $email = strtolower(trim((string)($row['email'] ?? '')));
            if ($email !== '') {
                $groups['email:' . $email][] = $row;
            }

            $phone = preg_replace('/\D+/', '', (string)($row['phone'] ?? ''));
            if (is_string($phone) && $phone !== '') {
                $groups['phone:' . $phone][] = $row;
            }

            $inn = trim((string)($row['tax_inn'] ?? ''));
            if ($inn !== '') {
                $groups['tax_inn:' . $inn][] = $row;
            }
        }

        $duplicates = [];
        foreach ($groups as $key => $items) {
            if (count($items) < 2) {
                continue;
            }

            [$kind, $value] = explode(':', $key, 2);
            $duplicates[] = [
                'match_type' => $kind,
                'match_value' => $value,
                'count' => count($items),
                'clients' => $items,
            ];
        }

        usort($duplicates, static function (array $a, array $b): int {
            return (int)($b['count'] ?? 0) <=> (int)($a['count'] ?? 0);
        });

        return [
            'duplicates' => $duplicates,
            'summary' => [
                'clients_total' => count($rows),
                'duplicate_groups' => count($duplicates),
            ],
        ];
    }

    /**
     * @return array<string,mixed>
     */
    public function dataQualitySummary(): array
    {
        $rows = (new QueryBuilder($this->pdo))
            ->from('clients')
            ->select([
                'public_id',
                'client_type',
                'email',
                'phone',
                'website',
                'tax_inn',
                'tax_kpp',
                'tax_ogrn',
                'tax_ogrnip',
                'bank_account',
                'bank_bik',
                'bank_corr_account',
                'legal_name',
            ])
            ->get();

        $total = count($rows);
        $safeTotal = max(1, $total);
        $filled = [
            'email' => 0,
            'phone' => 0,
            'website' => 0,
            'tax_inn' => 0,
            'bank_account' => 0,
            'legal_name' => 0,
        ];
        $byType = [
            'individual' => 0,
            'sole_proprietor' => 0,
            'legal_entity' => 0,
            'unknown' => 0,
        ];

        foreach ($rows as $row) {
            $type = trim((string)($row['client_type'] ?? ''));
            if (!isset($byType[$type])) {
                $type = 'unknown';
            }
            $byType[$type]++;

            foreach (array_keys($filled) as $field) {
                if (trim((string)($row[$field] ?? '')) !== '') {
                    $filled[$field]++;
                }
            }
        }

        $fillRate = [];
        foreach ($filled as $field => $count) {
            $fillRate[$field] = round(($count / $safeTotal) * 100, 2);
        }

        return [
            'clients_total' => $total,
            'by_type' => $byType,
            'filled_count' => $filled,
            'fill_rate_percent' => $fillRate,
        ];
    }

    public function findByPublicId(string $publicId): ?array
    {
        return (new QueryBuilder($this->pdo))
            ->from('clients c')
            ->leftJoin('companies co', 'co.id', '=', 'c.company_id')
            ->select(['c.*', 'co.public_id AS company_public_id', 'co.title AS company_title'])
            ->where('c.public_id', '=', $publicId)
            ->first();
    }

    public function companyIdByPublicId(string $publicId): ?int
    {
        $row = (new QueryBuilder($this->pdo))
            ->from('companies')
            ->select(['id'])
            ->where('public_id', '=', $publicId)
            ->first();
        $id = $row['id'] ?? false;

        return $id !== false ? (int)$id : null;
    }

    public function create(array $payload): void
    {
        (new QueryBuilder($this->pdo))
            ->from('clients')
            ->insert($payload);
    }

    public function updateByPublicId(string $publicId, array $set): bool
    {
        if ($set === []) {
            return false;
        }

        return (new QueryBuilder($this->pdo))
            ->from('clients')
            ->where('public_id', '=', $publicId)
            ->update($set) > 0;
    }

    public function deleteByPublicId(string $publicId): bool
    {
        return (new QueryBuilder($this->pdo))
            ->from('clients')
            ->where('public_id', '=', $publicId)
            ->delete() > 0;
    }
}
