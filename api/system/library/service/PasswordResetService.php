<?php
declare(strict_types=1);

namespace Api\System\Library\Service;

use Api\Model\Common\UserRepository;
use Api\Model\Security\PasswordResetRepository;
use Api\Model\Security\SessionRepository;
use Api\Model\User\UserManagementRepository;
use Api\System\Library\Logger\JsonLogger;
use Api\System\Library\Security\RateLimiterInterface;
use Api\System\Library\Security\PasswordHasher;
use Api\System\Library\Security\TokenManager;
use Api\System\Library\Support\Ulid;

final class PasswordResetService
{
    public function __construct(
        private readonly PasswordResetRepository $tokensRepository,
        private readonly UserRepository $users,
        private readonly SessionRepository $sessions,
        private readonly UserManagementRepository $userManagement,
        private readonly PasswordHasher $hasher,
        private readonly TokenManager $tokens,
        private readonly JsonLogger $logger,
        private readonly RateLimiterInterface $rateLimiter
    ) {
    }

    public function request(array $input, string $ip): array
    {
        $identifier = trim((string)($input['identifier'] ?? $input['login'] ?? ''));
        $normalizedIdentifier = mb_strtolower($identifier);
        $rateKey = hash('sha256', $normalizedIdentifier . '|' . trim($ip));
        $check = $this->rateLimiter->check($rateKey);
        if ($check['blocked'] === true) {
            $this->logger->security([
                'event_type' => 'password_reset_rate_limited',
                'identifier' => $identifier,
                'ip' => $ip,
                'retry_after' => $check['retry_after'],
            ]);

            return [
                'ok' => true,
                'accepted' => true,
            ];
        }

        $user = null;
        if ($identifier !== '' && !str_contains($identifier, '@')) {
            $user = $this->users->findByLogin($identifier);
        }

        if (!$user || (int)($user['is_active'] ?? 0) !== 1) {
            $this->rateLimiter->hit($rateKey);
            $this->logger->security([
                'event_type' => 'password_reset_request_missed',
                'identifier' => $identifier,
                'ip' => $ip,
            ]);

            return [
                'ok' => true,
                'accepted' => true,
            ];
        }

        $plainToken = $this->tokens->generate(24);
        $publicId = Ulid::generate('prt');
        $expiresAt = gmdate('Y-m-d H:i:s', time() + 7200);

        $this->tokensRepository->create([
            'public_id' => $publicId,
            'user_id' => (int)$user['id'],
            'token_hash' => $this->tokens->hash($plainToken),
            'expires_at' => $expiresAt,
            'used_at' => null,
            'created_at' => gmdate('Y-m-d H:i:s'),
        ]);

        $this->logger->security([
            'event_type' => 'password_reset_requested',
            'user_public_id' => (string)($user['public_id'] ?? ''),
            'reset_public_id' => $publicId,
            'ip' => $ip,
        ]);

        // Intentionally do not return reset token: delivery must happen out-of-band.
        return [
            'ok' => true,
            'accepted' => true,
        ];
    }

    public function confirm(array $input): array
    {
        $resetToken = trim((string)($input['reset_token'] ?? ''));
        $tokenRow = $this->tokensRepository->findActiveByTokenHash($this->tokens->hash($resetToken));
        if (!$tokenRow) {
            return ['ok' => false, 'code' => 'PASSWORD_RESET_TOKEN_INVALID'];
        }

        $expiresAt = strtotime(((string)($tokenRow['expires_at'] ?? '')) . ' UTC');
        if ($expiresAt !== false && $expiresAt < time()) {
            return ['ok' => false, 'code' => 'PASSWORD_RESET_TOKEN_EXPIRED'];
        }

        $user = $this->userManagement->findById((int)($tokenRow['user_id'] ?? 0));
        if (!$user || (int)($user['is_active'] ?? 0) !== 1) {
            return ['ok' => false, 'code' => 'USER_NOT_FOUND'];
        }

        $this->userManagement->updateByPublicId((string)$user['public_id'], [
            'password_hash' => $this->hasher->hash((string)$input['new_password']),
            'updated_at' => gmdate('Y-m-d H:i:s'),
        ]);
        $this->tokensRepository->markUsed((string)$tokenRow['public_id'], gmdate('Y-m-d H:i:s'));
        $revokedCount = $this->sessions->revokeAllByUserId((int)$user['id'], gmdate('Y-m-d H:i:s'));

        $this->logger->security([
            'event_type' => 'password_reset_completed',
            'user_public_id' => (string)$user['public_id'],
            'reset_public_id' => (string)$tokenRow['public_id'],
            'revoked_sessions' => $revokedCount,
        ]);

        return [
            'ok' => true,
            'reset' => [
                'public_id' => (string)$tokenRow['public_id'],
                'user_public_id' => (string)$user['public_id'],
            ],
        ];
    }
}
