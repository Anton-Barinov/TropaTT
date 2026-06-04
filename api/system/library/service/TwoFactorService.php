<?php
declare(strict_types=1);

namespace Api\System\Library\Service;

use Api\Model\Common\UserRepository;
use Api\Model\Security\TwoFactorRepository;
use Api\System\Library\Logger\JsonLogger;
use Api\System\Library\Security\PasswordHasher;
use Api\System\Library\Security\TokenManager;
use Api\System\Library\Support\Ulid;

final class TwoFactorService
{
    public function __construct(
        private readonly TwoFactorRepository $twoFactor,
        private readonly UserRepository $users,
        private readonly PasswordHasher $hasher,
        private readonly TokenManager $tokens,
        private readonly JsonLogger $logger
    ) {
    }

    public function status(array $actor): array
    {
        $userId = (int)($actor['id'] ?? 0);
        $record = $this->twoFactor->findByUserId($userId);

        return [
            'enabled' => $record !== null,
            'two_factor' => $record ? $this->normalize($record) : [
                'enabled' => false,
                'public_id' => '',
                'created_at' => '',
                'updated_at' => '',
                'backup_codes_remaining' => 0,
            ],
        ];
    }

    public function enable(array $actor, string $currentPassword): array
    {
        $user = $this->users->findById((int)($actor['id'] ?? 0));
        if (!$user || (int)($user['is_active'] ?? 0) !== 1) {
            return ['ok' => false, 'code' => 'USER_NOT_FOUND'];
        }

        if (!$this->hasher->verify($currentPassword, (string)($user['password_hash'] ?? ''))) {
            return ['ok' => false, 'code' => 'INVALID_CURRENT_PASSWORD'];
        }

        if ($this->twoFactor->findByUserId((int)$user['id'])) {
            return ['ok' => false, 'code' => 'TWO_FACTOR_ALREADY_ENABLED'];
        }

        $secret = $this->tokens->generate(20);
        $backupCodesPlain = $this->generateBackupCodes(8);
        $backupCodesHashed = array_map([$this->hasher, 'hash'], $backupCodesPlain);
        $now = gmdate('Y-m-d H:i:s');
        $publicId = Ulid::generate('tfa');

        $this->twoFactor->createOrReplace([
            'public_id' => $publicId,
            'user_id' => (int)$user['id'],
            'secret_hash' => $this->tokens->hash($secret),
            'backup_codes' => json_encode($backupCodesHashed, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $record = $this->twoFactor->findByUserId((int)$user['id']);
        $this->logger->security([
            'event_type' => 'two_factor_enabled',
            'actor_public_id' => $user['public_id'] ?? null,
            'two_factor_public_id' => $publicId,
        ]);

        return [
            'ok' => true,
            'two_factor' => $record ? $this->normalize($record) : ['enabled' => true, 'public_id' => $publicId],
            'setup_secret' => $secret,
            'backup_codes' => $backupCodesPlain,
        ];
    }

    public function disable(array $actor, string $currentPassword): array
    {
        $user = $this->users->findById((int)($actor['id'] ?? 0));
        if (!$user || (int)($user['is_active'] ?? 0) !== 1) {
            return ['ok' => false, 'code' => 'USER_NOT_FOUND'];
        }

        if (!$this->hasher->verify($currentPassword, (string)($user['password_hash'] ?? ''))) {
            return ['ok' => false, 'code' => 'INVALID_CURRENT_PASSWORD'];
        }

        $record = $this->twoFactor->findByUserId((int)$user['id']);
        if (!$record) {
            return ['ok' => false, 'code' => 'TWO_FACTOR_NOT_ENABLED'];
        }

        $ok = $this->twoFactor->deleteByUserId((int)$user['id']);
        if ($ok) {
            $this->logger->security([
                'event_type' => 'two_factor_disabled',
                'actor_public_id' => $user['public_id'] ?? null,
                'two_factor_public_id' => (string)($record['public_id'] ?? ''),
            ]);
        }

        return ['ok' => $ok];
    }

    private function normalize(array $record): array
    {
        $codes = json_decode((string)($record['backup_codes'] ?? '[]'), true);
        $backupCodes = is_array($codes) ? $codes : [];

        return [
            'enabled' => true,
            'public_id' => (string)($record['public_id'] ?? ''),
            'created_at' => (string)($record['created_at'] ?? ''),
            'updated_at' => (string)($record['updated_at'] ?? ''),
            'backup_codes_remaining' => count($backupCodes),
        ];
    }

    /** @return array<int,string> */
    private function generateBackupCodes(int $count): array
    {
        $codes = [];
        for ($i = 0; $i < $count; $i++) {
            $codes[] = strtoupper(substr(bin2hex(random_bytes(4)), 0, 8));
        }

        return $codes;
    }
}
