<?php
declare(strict_types=1);

namespace Api\Model\Contact;

use Api\System\Library\Database\Builder\QueryBuilder;
use PDO;

final class ContactRepository
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
                'ct.id',
                'ct.public_id',
                'ct.company_id',
                'ct.client_id',
                'ct.counterparty_id',
                'ct.user_id',
                'ct.full_name',
                'ct.email',
                'ct.phone',
                'ct.role',
                'ct.is_primary',
                'ct.created_by_user_id',
                'ct.created_at',
                'ct.updated_at',
                'co.public_id AS company_public_id',
                'co.title AS company_title',
                'cl.public_id AS client_public_id',
                'cl.title AS client_title',
                'cp.public_id AS counterparty_public_id',
                'cp.title AS counterparty_title',
                'cp.counterparty_type AS counterparty_type',
                'eu.public_id AS external_user_public_id',
                'eu.is_active AS external_user_is_active',
                'eu.external_invitation_expires_at AS external_invitation_expires_at',
            ])
            ->orderBy('ct.created_at', 'DESC')
            ->limit($limit)
            ->offset($offset)
            ->get();

        return [$items, $total, $page, $limit];
    }

    /** @param array<int,int> $creatorIds */
    private function buildListQuery(array $filters, array $creatorIds): QueryBuilder
    {
        $query = (new QueryBuilder($this->pdo))
            ->from('contacts ct')
            ->leftJoin('companies co', 'co.id', '=', 'ct.company_id')
            ->leftJoin('clients cl', 'cl.id', '=', 'ct.client_id')
            ->leftJoin('counterparties cp', 'cp.id', '=', 'ct.counterparty_id')
            ->leftJoin('users eu', 'eu.id', '=', 'ct.user_id');

        if (!empty($filters['search'])) {
            $search = '%' . (string)$filters['search'] . '%';
            $query->whereRaw('(ct.full_name LIKE ? OR ct.email LIKE ? OR ct.phone LIKE ?)', [$search, $search, $search]);
        }

        if (!empty($filters['company_public_id'])) {
            $query->where('co.public_id', '=', (string)$filters['company_public_id']);
        }

        if (!empty($filters['client_public_id'])) {
            $query->where('cl.public_id', '=', (string)$filters['client_public_id']);
        }

        if (!empty($filters['counterparty_public_id'])) {
            $query->where('cp.public_id', '=', (string)$filters['counterparty_public_id']);
        }

        if ($creatorIds !== []) {
            if (!empty($filters['include_unowned'])) {
                $placeholders = implode(',', array_fill(0, count($creatorIds), '?'));
                $query->whereRaw('(ct.created_by_user_id IS NULL OR ct.created_by_user_id IN (' . $placeholders . '))', $creatorIds);
            } else {
                $query->whereIn('ct.created_by_user_id', $creatorIds);
            }
        }

        return $query;
    }

    public function findByPublicId(string $publicId): ?array
    {
        return (new QueryBuilder($this->pdo))
            ->from('contacts ct')
            ->leftJoin('companies co', 'co.id', '=', 'ct.company_id')
            ->leftJoin('clients cl', 'cl.id', '=', 'ct.client_id')
            ->leftJoin('counterparties cp', 'cp.id', '=', 'ct.counterparty_id')
            ->select([
                'ct.*',
                'co.public_id AS company_public_id',
                'co.title AS company_title',
                'cl.public_id AS client_public_id',
                'cl.title AS client_title',
                'cp.public_id AS counterparty_public_id',
                'cp.title AS counterparty_title',
                'cp.counterparty_type AS counterparty_type',
            ])
            ->where('ct.public_id', '=', $publicId)
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

    public function clientIdByPublicId(string $publicId): ?int
    {
        $row = (new QueryBuilder($this->pdo))
            ->from('clients')
            ->select(['id'])
            ->where('public_id', '=', $publicId)
            ->first();
        $id = $row['id'] ?? false;

        return $id !== false ? (int)$id : null;
    }

    public function create(array $payload): void
    {
        (new QueryBuilder($this->pdo))
            ->from('contacts')
            ->insert($payload);
    }

    public function updateByPublicId(string $publicId, array $set): bool
    {
        if ($set === []) {
            return false;
        }

        return (new QueryBuilder($this->pdo))
            ->from('contacts')
            ->where('public_id', '=', $publicId)
            ->update($set) > 0;
    }

    public function deleteByPublicId(string $publicId): bool
    {
        return (new QueryBuilder($this->pdo))
            ->from('contacts')
            ->where('public_id', '=', $publicId)
            ->delete() > 0;
    }

    public function findById(int $id): ?array
    {
        return (new QueryBuilder($this->pdo))
            ->from('contacts')
            ->where('id', '=', $id)
            ->first();
    }

    /**
     * Load a contact while holding its row lock for a surrounding transaction.
     * SQLite has no FOR UPDATE; its transaction-level write lock is the closest
     * portable equivalent, while MySQL/PostgreSQL use the row-level lock.
     */
    public function findByIdForUpdate(int $id): ?array
    {
        if ($id <= 0) {
            return null;
        }

        $driver = (string)$this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        $sql = 'SELECT * FROM contacts WHERE id = :id';
        if (in_array($driver, ['mysql', 'pgsql'], true)) {
            $sql .= ' FOR UPDATE';
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row !== false ? $row : null;
    }

    public function findByUserId(int $userId): ?array
    {
        return (new QueryBuilder($this->pdo))
            ->from('contacts')
            ->where('user_id', '=', $userId)
            ->first();
    }

    public function updateById(int $id, array $set): bool
    {
        if ($set === []) {
            return false;
        }
        return (new QueryBuilder($this->pdo))
            ->from('contacts')
            ->where('id', '=', $id)
            ->update($set) > 0;
    }
}
