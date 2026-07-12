<?php
declare(strict_types=1);

namespace Api\System\Library\Service;

use Api\Model\Common\UserRepository;
use Api\Model\Security\PasswordResetRepository;
use Api\Model\Security\SessionRepository;
use Api\Model\User\UserManagementRepository;
use Api\System\Library\Logger\JsonLogger;
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
        private readonly JsonLogger $logger
    ) {
    }

    public function request(array $input, string $ip): array
    {
        $identifier = trim((string)($input['identifier'] ?? $input['login'] ?? ''));
        $normalizedIdentifier = mb_strtolower($identifier);
        $rateKey = hash('sha256', $normalizedIdentifier . '|' . trim($ip));
        $check = $this->checkFileRateLimit($rateKey, 'pwrst_req', false);
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
            $this->checkFileRateLimit($rateKey, 'pwrst_req', true);
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

        // Reset token is NOT logged: delivery must happen out-of-band via email/SMS.
        // Plaintext tokens in logs are a security risk (SEC-002).
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
            $check = $this->checkFileRateLimit($rateKey, 'pwrst_cnf', true);
            if ($check['blocked'] === true) {
                return ['ok' => false, 'code' => 'PASSWORD_RESET_RATE_LIMITED', 'retry_after' => $check['retry_after']];
            }
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

        $newPassword = (string)($input['new_password'] ?? '');
        if (mb_strlen($newPassword) < 8) {
            return ['ok' => false, 'code' => 'PASSWORD_RESET_WEAK_PASSWORD'];
        }

        $this->userManagement->updateByPublicId((string)$user['public_id'], [
            'password_hash' => $this->hasher->hash($newPassword),
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

    private function rateLimitStorageDir(): string
    {
        $dir = dirname(__DIR__, 3) . '/../storage_api/cache/rate_limits';
        if (!is_dir($dir)) {
            @mkdir($dir, 0700, true);
        }
        return realpath($dir) ?: $dir;
    }

    // ── File-based rate limit engine ──
    private function checkFileRateLimit(string $rateKey, string $prefix, bool $increment): array
    {
        $maxAttempts = 5;
        $windowSecs = 300;
        $lockSecs = 900;
        $now = time();
        $file = $this->rateLimitStorageDir() . '/crm_' . $prefix . '_' . hash('sha256', $rateKey) . '.counter';
        $fp = @fopen($file, 'c+');
        if (!$fp) return ['blocked' => false, 'retry_after' => 0];
        if (!flock($fp, LOCK_EX)) { fclose($fp); return ['blocked' => false, 'retry_after' => 0]; }
        $raw = stream_get_contents($fp);
        $data = ($raw !== false && $raw !== '') ? @json_decode($raw, true) : null;
        if (!is_array($data)) $data = ['count' => 0, 'window_start' => 0, 'blocked_until' => 0];
        $data['count'] = (int)($data['count'] ?? 0);
        $data['window_start'] = (int)($data['window_start'] ?? 0);
        $data['blocked_until'] = (int)($data['blocked_until'] ?? 0);
        if ($data['blocked_until'] > $now) { flock($fp, LOCK_UN); fclose($fp); return ['blocked' => true, 'retry_after' => $data['blocked_until'] - $now]; }
        if ($increment) {
            if (($now - $data['window_start']) > $windowSecs) $data = ['count' => 1, 'window_start' => $now, 'blocked_until' => 0];
            else { $data['count']++; if ($data['count'] >= $maxAttempts) $data['blocked_until'] = $now + $lockSecs; }
            ftruncate($fp, 0); rewind($fp); fwrite($fp, json_encode($data, JSON_UNESCAPED_SLASHES));
        }
        flock($fp, LOCK_UN); fclose($fp);
        return $data['blocked_until'] > $now ? ['blocked' => true, 'retry_after' => $data['blocked_until'] - $now] : ['blocked' => false, 'retry_after' => 0];
    }



}