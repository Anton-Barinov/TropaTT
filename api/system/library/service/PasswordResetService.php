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
        if ($identifier !== '') {
            if (str_contains($identifier, '@')) {
                $user = $this->users->findByEmail($identifier);
            } else {
                $user = $this->users->findByLogin($identifier);
            }
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

        // Log plain token to mail log (for dev/demo; production uses real email transport)
        $this->appendMailLog($identifier, $plainToken, (string)($user['public_id'] ?? ''));

        // Intentionally do not return reset token: delivery must happen out-of-band.
        return [
            'ok' => true,
            'accepted' => true,
        ];
    }

    public function confirm(array $input, string $ip = ''): array
    {
        // Rate limit by IP to prevent token brute-force flooding (Task 1.6)
        if ($ip !== '') {
            $rateKey = hash('sha256', 'password-confirm-ip:' . $ip);
            $check = $this->rateLimiter->check($rateKey);
            if ($check['blocked'] === true) {
                return ['ok' => false, 'code' => 'PASSWORD_RESET_RATE_LIMITED', 'retry_after' => $check['retry_after']];
            }
            $this->rateLimiter->hit($rateKey);
        }

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

    private function appendMailLog(string $identifier, string $plainToken, string $userPublicId): void
    {
        try {
            $logDir = dirname(__DIR__, 4) . '/storage/logs';
            if (!is_dir($logDir)) {
                @mkdir($logDir, 0755, true);
            }
            $logFile = $logDir . '/mail.log';
            $entry = sprintf(
                "[%s] PASSWORD_RESET identifier=%s user=%s token=%s" . PHP_EOL,
                gmdate('Y-m-d H:i:s'),
                $identifier,
                $userPublicId,
                $plainToken
            );
            @file_put_contents($logFile, $entry, FILE_APPEND | LOCK_EX);
        } catch (\Throwable) {
            // Best-effort logging
        }
    }

}