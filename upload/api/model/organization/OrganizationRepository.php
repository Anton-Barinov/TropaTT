<?php
declare(strict_types=1);

namespace Api\Model\Organization;

use Api\System\Library\Database\Builder\QueryBuilder;
use PDO;

final class OrganizationRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function list(array $filters, int $actorUserId, bool $actorIsRoot): array
    {
        $page = max(1, (int)($filters['page'] ?? 1));
        $limit = min(100, max(1, (int)($filters['limit'] ?? 20)));
        $offset = ($page - 1) * $limit;

        $total = $this->buildListQuery($filters, $actorUserId, $actorIsRoot)->count();
        $items = $this->buildListQuery($filters, $actorUserId, $actorIsRoot)
            ->select([
                'o.public_id',
                'o.title',
                'o.slug',
                'o.created_at',
                'o.updated_at',
                '(SELECT COUNT(*) FROM organization_memberships om WHERE om.organization_id = o.id) AS members_count',
            ])
            ->orderBy('o.created_at', 'DESC')
            ->limit($limit)
            ->offset($offset)
            ->get();

        return [$items, $total, $page, $limit];
    }

    private function buildListQuery(array $filters, int $actorUserId, bool $actorIsRoot): QueryBuilder
    {
        $query = (new QueryBuilder($this->pdo))
            ->from('organizations o');

        if (!empty($filters['search'])) {
            $search = '%' . (string)$filters['search'] . '%';
            $query->whereRaw('(o.title LIKE ? OR o.slug LIKE ?)', [$search, $search]);
        }

        if (!$actorIsRoot) {
            $query->whereRaw(
                'EXISTS (SELECT 1 FROM organization_memberships omf WHERE omf.organization_id = o.id AND omf.user_id = ?)',
                [$actorUserId]
            );
        }

        return $query;
    }

    public function findByPublicId(string $publicId): ?array
    {
        return (new QueryBuilder($this->pdo))
            ->from('organizations')
            ->where('public_id', '=', $publicId)
            ->first();
    }

    public function findBySlug(string $slug): ?array
    {
        return (new QueryBuilder($this->pdo))
            ->from('organizations')
            ->where('slug', '=', $slug)
            ->first();
    }

    public function create(array $payload): void
    {
        (new QueryBuilder($this->pdo))
            ->from('organizations')
            ->insert($payload);
    }

    public function updateByPublicId(string $publicId, array $set): bool
    {
        if ($set === []) {
            return false;
        }

        return (new QueryBuilder($this->pdo))
            ->from('organizations')
            ->where('public_id', '=', $publicId)
            ->update($set) > 0;
    }

    public function deleteByPublicId(string $publicId): bool
    {
        $org = $this->findByPublicId($publicId);
        if (!$org) {
            return false;
        }

        $orgId = (int)$org['id'];
        (new QueryBuilder($this->pdo))
            ->from('organization_memberships')
            ->where('organization_id', '=', $orgId)
            ->delete();

        return (new QueryBuilder($this->pdo))
            ->from('organizations')
            ->where('id', '=', $orgId)
            ->delete() > 0;
    }

    public function listMembers(string $organizationPublicId): array
    {
        return (new QueryBuilder($this->pdo))
            ->from('organization_memberships om')
            ->join('organizations o', 'o.id', '=', 'om.organization_id')
            ->join('users u', 'u.id', '=', 'om.user_id')
            ->select([
                'om.public_id',
                'om.role_code',
                'om.created_at',
                'u.public_id AS user_public_id',
                'u.login',
                'u.email',
                'u.full_name',
                'u.is_active',
            ])
            ->where('o.public_id', '=', $organizationPublicId)
            ->orderBy('om.created_at', 'ASC')
            ->get();
    }

    public function addOrUpdateMember(string $organizationPublicId, string $userPublicId, string $roleCode, string $membershipPublicId, string $createdAt): bool
    {
        $organizationId = $this->resolveOrganizationId($organizationPublicId);
        $userId = $this->resolveUserId($userPublicId);
        if ($organizationId <= 0 || $userId <= 0) {
            return false;
        }

        $existing = $this->membershipByOrgAndUser($organizationId, $userId);
        if ($existing) {
            (new QueryBuilder($this->pdo))
                ->from('organization_memberships')
                ->where('id', '=', (int)$existing['id'])
                ->update(['role_code' => $roleCode]);
            return true;
        }

        (new QueryBuilder($this->pdo))
            ->from('organization_memberships')
            ->insert([
            'public_id' => $membershipPublicId,
            'organization_id' => $organizationId,
            'user_id' => $userId,
            'role_code' => $roleCode,
            'created_at' => $createdAt,
        ]);

        return true;
    }

    public function removeMember(string $organizationPublicId, string $userPublicId): bool
    {
        $organizationId = $this->resolveOrganizationId($organizationPublicId);
        $userId = $this->resolveUserId($userPublicId);
        if ($organizationId <= 0 || $userId <= 0) {
            return false;
        }

        return (new QueryBuilder($this->pdo))
            ->from('organization_memberships')
            ->where('organization_id', '=', $organizationId)
            ->where('user_id', '=', $userId)
            ->delete() > 0;
    }

    public function isMember(string $organizationPublicId, int $userId): bool
    {
        $organizationId = $this->resolveOrganizationId($organizationPublicId);
        if ($organizationId <= 0 || $userId <= 0) {
            return false;
        }

        return (new QueryBuilder($this->pdo))
            ->from('organization_memberships')
            ->select(['id'])
            ->where('organization_id', '=', $organizationId)
            ->where('user_id', '=', $userId)
            ->first() !== null;
    }

    public function memberRole(string $organizationPublicId, int $userId): ?string
    {
        $organizationId = $this->resolveOrganizationId($organizationPublicId);
        if ($organizationId <= 0 || $userId <= 0) {
            return null;
        }

        $row = (new QueryBuilder($this->pdo))
            ->from('organization_memberships')
            ->select(['role_code'])
            ->where('organization_id', '=', $organizationId)
            ->where('user_id', '=', $userId)
            ->first();

        return isset($row['role_code']) ? (string)$row['role_code'] : null;
    }

    public function countOwners(string $organizationPublicId): int
    {
        $organizationId = $this->resolveOrganizationId($organizationPublicId);
        if ($organizationId <= 0) {
            return 0;
        }

        return (new QueryBuilder($this->pdo))
            ->from('organization_memberships')
            ->where('organization_id', '=', $organizationId)
            ->where('role_code', '=', 'owner')
            ->count();
    }

    private function resolveOrganizationId(string $organizationPublicId): int
    {
        $row = (new QueryBuilder($this->pdo))
            ->from('organizations')
            ->select(['id'])
            ->where('public_id', '=', $organizationPublicId)
            ->first();

        return isset($row['id']) ? (int)$row['id'] : 0;
    }

    private function resolveUserId(string $userPublicId): int
    {
        $row = (new QueryBuilder($this->pdo))
            ->from('users')
            ->select(['id'])
            ->where('public_id', '=', $userPublicId)
            ->whereNull('deleted_at')
            ->first();

        return isset($row['id']) ? (int)$row['id'] : 0;
    }

    private function membershipByOrgAndUser(int $organizationId, int $userId): ?array
    {
        return (new QueryBuilder($this->pdo))
            ->from('organization_memberships')
            ->select(['id', 'public_id', 'role_code'])
            ->where('organization_id', '=', $organizationId)
            ->where('user_id', '=', $userId)
            ->first();
    }
}
