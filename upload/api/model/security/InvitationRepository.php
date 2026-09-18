<?php
declare(strict_types=1);

namespace Api\Model\Security;

use Api\System\Library\Database\Builder\QueryBuilder;
use PDO;
use Api\System\Library\Support\LikeEscaper;

final class InvitationRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function list(array $filters, ?int $actorUserId = null, bool $actorIsRoot = false): array
    {
        $page = max(1, (int)($filters['page'] ?? 1));
        $limit = min(100, max(1, (int)($filters['limit'] ?? 20)));
        $offset = ($page - 1) * $limit;

        $total = $this->buildListQuery($filters, $actorUserId, $actorIsRoot)->count();
        $items = $this->buildListQuery($filters, $actorUserId, $actorIsRoot)
            ->select([
                'i.public_id',
                'i.email',
                'i.expires_at',
                'i.accepted_at',
                'i.created_at',
                'inviter.public_id AS invited_by_public_id',
                'inviter.login AS invited_by_login',
                'inviter.full_name AS invited_by_full_name',
            ])
            ->orderBy('i.created_at', 'DESC')
            ->orderBy('i.public_id', 'DESC')
            ->limit($limit)
            ->offset($offset)
            ->get();

        return [$items, $total, $page, $limit];
    }

    /** List invitations belonging to one organization (new workspace API). */
    public function listForOrganization(string $organizationPublicId, array $filters): array
    {
        $page = max(1, (int)($filters['page'] ?? 1));
        $limit = min(100, max(1, (int)($filters['limit'] ?? 20)));
        $offset = ($page - 1) * $limit;
        $query = $this->buildOrganizationQuery($organizationPublicId, $filters);
        $total = $query->count();
        $items = $this->buildOrganizationQuery($organizationPublicId, $filters)
            ->select([
                'i.public_id', 'i.email', 'i.role_code', 'i.expires_at', 'i.accepted_at', 'i.revoked_at', 'i.created_at',
                'inviter.public_id AS invited_by_public_id', 'inviter.login AS invited_by_login', 'inviter.full_name AS invited_by_full_name',
            ])
            ->orderBy('i.created_at', 'DESC')->orderBy('i.public_id', 'DESC')
            ->limit($limit)->offset($offset)->get();
        return [$items, $total, $page, $limit];
    }

    private function buildOrganizationQuery(string $organizationPublicId, array $filters): QueryBuilder
    {
        $query = (new QueryBuilder($this->pdo))
            ->from('invitations i')
            ->join('organizations o', 'o.id', '=', 'i.organization_id')
            ->leftJoin('users inviter', 'inviter.id', '=', 'i.invited_by_user_id')
            ->where('o.public_id', '=', $organizationPublicId);
        if (!empty($filters['search'])) {
            $search = '%' . LikeEscaper::escape(trim((string)$filters['search'])) . '%';
            $query->whereRaw('(i.public_id LIKE ? OR i.email LIKE ?)', [$search, $search]);
        }
        $status = (string)($filters['status'] ?? '');
        if ($status === 'pending') $query->whereNull('i.accepted_at')->whereNull('i.revoked_at');
        elseif ($status === 'accepted') $query->whereNotNull('i.accepted_at');
        elseif ($status === 'revoked') $query->whereNotNull('i.revoked_at');
        return $query;
    }

    private function buildListQuery(array $filters, ?int $actorUserId, bool $actorIsRoot): QueryBuilder
    {
        $query = (new QueryBuilder($this->pdo))
            ->from('invitations i')
            ->leftJoin('users inviter', 'inviter.id', '=', 'i.invited_by_user_id');

        if (!$actorIsRoot && $actorUserId !== null && $actorUserId > 0) {
            $query->where('i.invited_by_user_id', '=', $actorUserId);
        }

        if (!empty($filters['search'])) {
            $search = '%' . LikeEscaper::escape(trim((string)$filters['search'])) . '%';
            $query->whereRaw('(i.public_id LIKE ? OR i.email LIKE ?)', [$search, $search]);
        }

        if (($filters['accepted'] ?? '') === '1') {
            $query->whereNotNull('i.accepted_at');
        } elseif (($filters['accepted'] ?? '') === '0') {
            $query->whereNull('i.accepted_at');
        }

        return $query;
    }

    public function findByPublicId(string $publicId): ?array
    {
        return $this->buildDetailsQuery()
            ->where('i.public_id', '=', $publicId)
            ->first();
    }

    public function findActiveByTokenHash(string $tokenHash): ?array
    {
        return $this->buildDetailsQuery()
            ->where('i.token_hash', '=', $tokenHash)
            ->whereNull('i.accepted_at')
            ->first();
    }

    public function findActiveByTokenHashForOrganization(string $tokenHash, string $organizationPublicId): ?array
    {
        return $this->buildDetailsQuery()
            ->join('organizations organization_scope', 'organization_scope.id', '=', 'i.organization_id')
            ->where('organization_scope.public_id', '=', $organizationPublicId)
            ->where('i.token_hash', '=', $tokenHash)
            ->whereNull('i.accepted_at')->whereNull('i.revoked_at')->first();
    }

    public function create(array $payload): void
    {
        (new QueryBuilder($this->pdo))
            ->from('invitations')
            ->insert($payload);
    }

    public function markAccepted(string $publicId, string $acceptedAt): bool
    {
        return (new QueryBuilder($this->pdo))
            ->from('invitations')
            ->where('public_id', '=', $publicId)
            ->whereNull('accepted_at')
            ->update(['accepted_at' => $acceptedAt]) > 0;
    }

    public function markAcceptedForOrganization(string $publicId, string $organizationPublicId, string $acceptedAt): bool
    {
        return (new QueryBuilder($this->pdo))->from('invitations')
            ->where('public_id', '=', $publicId)
            ->whereRaw('organization_id IN (SELECT id FROM organizations WHERE public_id = ?)', [$organizationPublicId])
            ->whereNull('accepted_at')->whereNull('revoked_at')
            ->update(['accepted_at' => $acceptedAt]) > 0;
    }

    public function revokeForOrganization(string $publicId, string $organizationPublicId, string $revokedAt): bool
    {
        return (new QueryBuilder($this->pdo))->from('invitations')
            ->where('public_id', '=', $publicId)
            ->whereRaw('organization_id IN (SELECT id FROM organizations WHERE public_id = ?)', [$organizationPublicId])
            ->whereNull('accepted_at')->whereNull('revoked_at')
            ->update(['revoked_at' => $revokedAt]) > 0;
    }

    public function refreshForOrganization(string $publicId, string $organizationPublicId, string $tokenHash, string $expiresAt): bool
    {
        return (new QueryBuilder($this->pdo))->from('invitations')
            ->where('public_id', '=', $publicId)
            ->whereRaw('organization_id IN (SELECT id FROM organizations WHERE public_id = ?)', [$organizationPublicId])
            ->whereNull('accepted_at')->whereNull('revoked_at')
            ->update(['token_hash' => $tokenHash, 'expires_at' => $expiresAt]) > 0;
    }

    private function buildDetailsQuery(): QueryBuilder
    {
        return (new QueryBuilder($this->pdo))
            ->from('invitations i')
            ->leftJoin('users inviter', 'inviter.id', '=', 'i.invited_by_user_id')
            ->leftJoin('organizations invitation_org', 'invitation_org.id', '=', 'i.organization_id')
            ->select([
                'i.*',
                'invitation_org.public_id AS organization_public_id',
                'inviter.public_id AS invited_by_public_id',
                'inviter.login AS invited_by_login',
                'inviter.full_name AS invited_by_full_name',
            ]);
    }
}
