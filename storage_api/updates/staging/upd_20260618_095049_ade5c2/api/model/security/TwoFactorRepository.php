<?php
declare(strict_types=1);

namespace Api\Model\Security;

use Api\System\Library\Database\Builder\QueryBuilder;
use PDO;

final class TwoFactorRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function findByUserId(int $userId): ?array
    {
        return (new QueryBuilder($this->pdo))
            ->from('two_factor_secrets')
            ->select(['public_id', 'user_id', 'secret_hash', 'backup_codes', 'created_at', 'updated_at'])
            ->where('user_id', '=', $userId)
            ->first();
    }

    public function createOrReplace(array $payload): void
    {
        (new QueryBuilder($this->pdo))
            ->from('two_factor_secrets')
            ->where('user_id', '=', (int)$payload['user_id'])
            ->delete();

        (new QueryBuilder($this->pdo))
            ->from('two_factor_secrets')
            ->insert($payload);
    }

    public function deleteByUserId(int $userId): bool
    {
        return (new QueryBuilder($this->pdo))
            ->from('two_factor_secrets')
            ->where('user_id', '=', $userId)
            ->delete() > 0;
    }
}
