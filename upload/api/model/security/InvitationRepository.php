<?php
declare(strict_types=1);

namespace Api\Model\Security;

use Api\System\Library\Database\Builder\QueryBuilder;
use PDO;

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

    private function buildListQuery(array $filters, ?int $actorUserId, bool $actorIsRoot): QueryBuilder
    {
        $query = (new QueryBuilder($this->pdo))
            ->from('invitations i')
            ->leftJoin('users inviter', 'inviter.id', '=', 'i.invited_by_user_id');

        if (!$actorIsRoot && $actorUserId !== null && $actorUserId > 0) {
            $query->where('i.invited_by_user_id', '=', $actorUserId);
        }

        if (!empty($filters['search'])) {
            $search = '%' . trim((string)$filters['search']) . '%';
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

    private function buildDetailsQuery(): QueryBuilder
    {
        return (new QueryBuilder($this->pdo))
            ->from('invitations i')
            ->leftJoin('users inviter', 'inviter.id', '=', 'i.invited_by_user_id')
            ->select([
                'i.*',
                'inviter.public_id AS invited_by_public_id',
                'inviter.login AS invited_by_login',
                'inviter.full_name AS invited_by_full_name',
            ]);
    }
}
