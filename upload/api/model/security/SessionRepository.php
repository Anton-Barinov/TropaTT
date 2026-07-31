<?php
declare(strict_types=1);

namespace Api\Model\Security;

use Api\System\Library\Database\Builder\QueryBuilder;
use PDO;

final class SessionRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function listByUserId(int $userId, int $page = 1, int $limit = 20): array
    {
        $page = max(1, $page);
        $limit = min(100, max(1, $limit));
        $offset = ($page - 1) * $limit;

        $total = $this->buildListByUserQuery($userId)->count();
        $items = $this->buildListByUserQuery($userId)
            ->select(['public_id', 'ip', 'user_agent', 'device_fingerprint', 'device_name', 'expires_at', 'revoked_at', 'created_at'])
            ->orderBy('created_at', 'DESC')
            ->limit($limit)
            ->offset($offset)
            ->get();

        return [$items, $total, $page, $limit];
    }

    private function buildListByUserQuery(int $userId): QueryBuilder
    {
        return (new QueryBuilder($this->pdo))
            ->from('user_sessions')
            ->where('user_id', '=', $userId);
    }

    public function findByPublicId(string $publicId): ?array
    {
        return (new QueryBuilder($this->pdo))
            ->from('user_sessions')
            ->where('public_id', '=', $publicId)
            ->first();
    }

    public function revokeByPublicId(string $publicId, string $revokedAt): bool
    {
        return (new QueryBuilder($this->pdo))
            ->from('user_sessions')
            ->where('public_id', '=', $publicId)
            ->whereNull('revoked_at')
            ->update(['revoked_at' => $revokedAt]) > 0;
    }

    public function revokeOthers(int $userId, string $keepPublicId, string $revokedAt): int
    {
        return (new QueryBuilder($this->pdo))
            ->from('user_sessions')
            ->where('user_id', '=', $userId)
            ->where('public_id', '<>', $keepPublicId)
            ->whereNull('revoked_at')
            ->update(['revoked_at' => $revokedAt]);
    }

    public function revokeByUserAgentNeedle(string $needle, string $revokedAt): int
    {
        return (new QueryBuilder($this->pdo))
            ->from('user_sessions')
            ->whereNull('revoked_at')
            ->where('user_agent', 'LIKE', '%' . $needle . '%')
            ->update(['revoked_at' => $revokedAt]);
    }

    public function revokeByDeviceFingerprint(int $userId, string $fingerprint, string $revokedAt, ?string $excludeSessionPublicId = null): int
    {
        $query = (new QueryBuilder($this->pdo))
            ->from('user_sessions')
            ->where('user_id', '=', $userId)
            ->where('device_fingerprint', '=', $fingerprint)
            ->whereNull('revoked_at');

        if ($excludeSessionPublicId !== null && $excludeSessionPublicId !== '') {
            $query->where('public_id', '<>', $excludeSessionPublicId);
        }

        return $query->update(['revoked_at' => $revokedAt]);
    }

    public function revokeAllByUserId(int $userId, string $revokedAt, ?string $excludeSessionPublicId = null): int
    {
        $query = (new QueryBuilder($this->pdo))
            ->from('user_sessions')
            ->where('user_id', '=', $userId)
            ->whereNull('revoked_at');

        if ($excludeSessionPublicId !== null && trim($excludeSessionPublicId) !== '') {
            $query->where('public_id', '<>', trim($excludeSessionPublicId));
        }

        return $query->update(['revoked_at' => $revokedAt]);
    }
}
