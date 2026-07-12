<?php
declare(strict_types=1);

namespace Api\Model\Security;

use Api\System\Library\Database\Builder\QueryBuilder;
use PDO;

final class ImpersonationRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function create(array $payload): void
    {
        (new QueryBuilder($this->pdo))
            ->from('impersonation_audit')
            ->insert($payload);
    }

    public function findByPublicId(string $publicId): ?array
    {
        return $this->buildAuditQuery()
            ->where('ia.public_id', '=', $publicId)
            ->first();
    }

    public function findActiveByPublicId(string $publicId): ?array
    {
        return $this->buildAuditQuery()
            ->where('ia.public_id', '=', $publicId)
            ->whereNull('ia.ended_at')
            ->first();
    }

    public function findActiveByAdminAndTarget(int $adminUserId, int $targetUserId): ?array
    {
        return $this->buildAuditQuery()
            ->where('ia.admin_user_id', '=', $adminUserId)
            ->where('ia.target_user_id', '=', $targetUserId)
            ->whereNull('ia.ended_at')
            ->orderBy('ia.started_at', 'DESC')
            ->first();
    }

    /** @return array<int,array<string,mixed>> */
    public function listActiveByAdminUserId(int $adminUserId, int $limit = 20): array
    {
        $limit = min(100, max(1, $limit));
        return $this->buildAuditQuery()
            ->where('ia.admin_user_id', '=', $adminUserId)
            ->whereNull('ia.ended_at')
            ->orderBy('ia.started_at', 'DESC')
            ->limit($limit)
            ->get();
    }

    public function endByPublicId(string $publicId, string $endedAt): bool
    {
        return (new QueryBuilder($this->pdo))
            ->from('impersonation_audit')
            ->where('public_id', '=', $publicId)
            ->whereNull('ended_at')
            ->update(['ended_at' => $endedAt]) > 0;
    }

    private function buildAuditQuery(): QueryBuilder
    {
        return (new QueryBuilder($this->pdo))
            ->from('impersonation_audit ia')
            ->leftJoin('users au', 'au.id', '=', 'ia.admin_user_id')
            ->leftJoin('users tu', 'tu.id', '=', 'ia.target_user_id')
            ->select([
                'ia.*',
                'au.public_id AS admin_public_id',
                'au.login AS admin_login',
                'tu.public_id AS target_public_id',
                'tu.login AS target_login',
            ]);
    }
}
