<?php
declare(strict_types=1);

namespace Api\Model\Security;

use Api\System\Library\Database\Builder\QueryBuilder;
use PDO;

final class PasswordResetRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function create(array $payload): void
    {
        (new QueryBuilder($this->pdo))
            ->from('password_reset_tokens')
            ->insert($payload);
    }

    public function findActiveByTokenHash(string $tokenHash): ?array
    {
        return (new QueryBuilder($this->pdo))
            ->from('password_reset_tokens')
            ->select(['public_id', 'user_id', 'token_hash', 'expires_at', 'used_at', 'created_at'])
            ->where('token_hash', '=', $tokenHash)
            ->whereNull('used_at')
            ->first();
    }

    public function markUsed(string $publicId, string $usedAt): bool
    {
        return (new QueryBuilder($this->pdo))
            ->from('password_reset_tokens')
            ->where('public_id', '=', $publicId)
            ->whereNull('used_at')
            ->update(['used_at' => $usedAt]) > 0;
    }
}
