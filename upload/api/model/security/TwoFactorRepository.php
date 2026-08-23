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
            ->select(['public_id', 'user_id', 'secret_hash', 'backup_codes', 'last_totp_step', 'last_login_nonce_hash', 'created_at', 'updated_at'])
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

    public function updateBackupCodes(int $userId, string $backupCodesJson, string $updatedAt): void
    {
        (new QueryBuilder($this->pdo))
            ->from('two_factor_secrets')
            ->where('user_id', '=', $userId)
            ->update([
                'backup_codes' => $backupCodesJson,
                'updated_at' => $updatedAt,
            ]);
    }

    public function updateLastTotpStep(int $userId, int $step): void
    {
        (new QueryBuilder($this->pdo))
            ->from('two_factor_secrets')
            ->where('user_id', '=', $userId)
            ->update([
                'last_totp_step' => $step,
            ]);
    }

    public function consumeLoginNonce(int $userId, string $nonceHash): void
    {
        (new QueryBuilder($this->pdo))
            ->from('two_factor_secrets')
            ->where('user_id', '=', $userId)
            ->update([
                'last_login_nonce_hash' => $nonceHash,
            ]);
    }

    public function findLoginNonceHash(int $userId): ?string
    {
        $row = (new QueryBuilder($this->pdo))
            ->from('two_factor_secrets')
            ->select(['last_login_nonce_hash'])
            ->where('user_id', '=', $userId)
            ->first();

        return $row !== null ? ((string)($row['last_login_nonce_hash'] ?? '')) : null;
    }
}
