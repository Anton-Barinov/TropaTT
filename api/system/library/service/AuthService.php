<?php
declare(strict_types=1);

namespace Api\System\Library\Service;

use Api\Model\Auth\AuthRepository;
use Api\Model\Common\UserRepository;
use Api\System\Library\Logger\JsonLogger;
use Api\System\Library\Security\RateLimiterInterface;
use Api\System\Library\Security\PasswordHasher;
use Api\System\Library\Security\TokenManager;
use Api\System\Library\Support\Ulid;

final class AuthService
{
    public function __construct(
        private readonly UserRepository $users,
        private readonly AuthRepository $auth,
        private readonly PasswordHasher $hasher,
        private readonly TokenManager $tokens,
        private readonly JsonLogger $logger,
        private readonly int $tokenTtlSeconds,
        private readonly RateLimiterInterface $rateLimiter
    ) {
    }

    public function login(array $input, string $ip, string $userAgent): array
    {
        $login = trim((string)($input['login'] ?? ''));
        $password = (string)($input['password'] ?? '');
        $token = trim((string)($input['token'] ?? ''));
        $rateKey = $this->rateLimitKey($login, $ip);
        $check = $this->rateLimiter->check($rateKey);
        if ($check['blocked'] === true) {
            $this->logger->security([
                'event_type' => 'auth_rate_limited',
                'login' => $login,
                'ip' => $ip,
                'user_agent' => $userAgent,
                'retry_after' => $check['retry_after'],
            ]);

            return [
                'ok' => false,
                'code' => 'AUTH_RATE_LIMITED',
                'retry_after' => $check['retry_after'],
            ];
        }

        $user = $this->users->findByLogin($login);
        if (!$user || (int)($user['is_active'] ?? 0) !== 1) {
            // Timing-attack mitigation (Task 1.5): simulate password hash verification
            // so that non-existent users take the same time as existent ones.
            // Uses a pre-generated Argon2id hash to match the system's actual hash algorithm.
            $this->hasher->verify('dummy_plain_text', '$argon2id$v=19$m=65536,t=4,p=1$c29tZXNhbHR2YWx1ZXMxMjM0NQ$wLdTJFplKxH5XKRhXQz7vA+VL0VvN8gD4v7TyzHGlc0');
            $state = $this->rateLimiter->hit($rateKey);
            $this->logger->security([
                'event_type' => 'auth_failed',
                'reason' => 'user_not_found_or_inactive',
                'login' => $login,
                'ip' => $ip,
                'user_agent' => $userAgent,
                'rate_limited' => $state['blocked'],
                'retry_after' => $state['retry_after'],
            ]);

            if ($state['blocked'] === true) {
                return ['ok' => false, 'code' => 'AUTH_RATE_LIMITED', 'retry_after' => $state['retry_after']];
            }

            return ['ok' => false, 'code' => 'INVALID_CREDENTIALS'];
        }

        if (!$this->hasher->verify($password, (string)$user['password_hash'])) {
            $state = $this->rateLimiter->hit($rateKey);
            $this->logger->security([
                'event_type' => 'auth_failed',
                'reason' => 'invalid_password',
                'user_public_id' => (string)$user['public_id'],
                'ip' => $ip,
                'user_agent' => $userAgent,
                'rate_limited' => $state['blocked'],
                'retry_after' => $state['retry_after'],
            ]);

            if ($state['blocked'] === true) {
                return ['ok' => false, 'code' => 'AUTH_RATE_LIMITED', 'retry_after' => $state['retry_after']];
            }

            return ['ok' => false, 'code' => 'INVALID_CREDENTIALS'];
        }

        $tokenHash = (string)($user['auth_token_hash'] ?? '');
        if ($tokenHash !== '' && ($token === '' || !hash_equals($tokenHash, hash('sha256', $token)))) {
            $state = $this->rateLimiter->hit($rateKey);
            $this->logger->security([
                'event_type' => 'auth_failed',
                'reason' => 'invalid_token_factor',
                'user_public_id' => (string)$user['public_id'],
                'ip' => $ip,
                'user_agent' => $userAgent,
                'rate_limited' => $state['blocked'],
                'retry_after' => $state['retry_after'],
            ]);

            if ($state['blocked'] === true) {
                return ['ok' => false, 'code' => 'AUTH_RATE_LIMITED', 'retry_after' => $state['retry_after']];
            }

            return ['ok' => false, 'code' => 'INVALID_CREDENTIALS'];
        }

        $this->rateLimiter->clear($rateKey);
        $plainAccess = $this->tokens->generate();
        $sessionPublicId = Ulid::generate('ses');
        $expiresAt = gmdate('Y-m-d H:i:s', time() + $this->tokenTtlSeconds);
        $deviceFingerprint = $this->buildDeviceFingerprint($userAgent);
        $deviceName = $this->buildDeviceName($userAgent);

        $this->auth->createSession([
            'public_id' => $sessionPublicId,
            'user_id' => (int)$user['id'],
            'token_hash' => $this->tokens->hash($plainAccess),
            'ip' => $ip,
            'user_agent' => $userAgent,
            'device_fingerprint' => $deviceFingerprint,
            'device_name' => $deviceName,
            'expires_at' => $expiresAt,
            'created_at' => gmdate('Y-m-d H:i:s'),
        ]);

        $this->logger->security([
            'event_type' => 'auth_login_success',
            'user_public_id' => (string)$user['public_id'],
            'session_public_id' => $sessionPublicId,
            'ip' => $ip,
            'user_agent' => $userAgent,
        ]);

        return [
            'ok' => true,
            'access_token' => $plainAccess,
            'token_type' => 'Bearer',
            'expires_in' => $this->tokenTtlSeconds,
            'session_public_id' => $sessionPublicId,
            'user' => $this->normalizeUser($user),
        ];
    }

    public function me(string $accessToken): ?array
    {
        $hash = $this->tokens->hash($accessToken);
        $session = $this->auth->findSessionByTokenHash($hash);

        if (!$session) {
            return null;
        }

        $expiresAt = strtotime(((string)$session['expires_at']) . ' UTC');
        if ($expiresAt !== false && $expiresAt < time()) {
            return null;
        }

        if ((int)($session['is_active'] ?? 0) !== 1) {
            return null;
        }

        $newExpiresAt = gmdate('Y-m-d H:i:s', time() + $this->tokenTtlSeconds);
        $this->auth->extendSessionByTokenHash($hash, $newExpiresAt);

        $user = $this->normalizeUser([
            'id' => (int)($session['user_id'] ?? 0),
            'public_id' => (string)$session['user_public_id'],
            'login' => (string)$session['login'],
            'full_name' => (string)$session['full_name'],
            'locale' => (string)$session['locale'],
            'is_root' => (bool)$session['is_root'],
            'is_active' => (bool)$session['is_active'],
            'created_by_user_id' => $session['created_by_user_id'] ?? null,
        ]);

        return [
            'session_public_id' => (string)$session['public_id'],
            'expires_at' => $newExpiresAt,
            'expires_in' => $this->tokenTtlSeconds,
            'user' => $user,
        ];
    }

    public function logout(string $accessToken): bool
    {
        $hash = $this->tokens->hash($accessToken);
        return $this->auth->revokeByTokenHash($hash, gmdate('Y-m-d H:i:s'));
    }

    private function normalizeUser(array $user): array
    {
        $userId = (int)($user['id'] ?? 0);
        $isRoot = (bool)($user['is_root'] ?? false);
        $roleCodes = $this->auth->roleCodesByUserId($userId);
        $permissionCodes = $isRoot ? ['*'] : $this->auth->permissionCodesByUserId($userId);

        return [
            'public_id' => (string)$user['public_id'],
            'login' => (string)$user['login'],
            'email' => (string)($user['email'] ?? ''),
            'full_name' => (string)($user['full_name'] ?? ''),
            'locale' => (string)($user['locale'] ?? 'en-gb'),
            'is_root' => $isRoot,
            'is_active' => (bool)$user['is_active'],
            'roles' => $roleCodes,
            'permission_codes' => $permissionCodes,
        ];
    }

    private function rateLimitKey(string $login, string $ip): string
    {
        $normalizedLogin = strtolower(trim($login));
        if ($normalizedLogin === '') {
            $normalizedLogin = '_';
        }

        // Key is per-login (NOT per login+IP) so that brute-force across
        // different IPs still locks the account after N attempts.
        // IP-level blocking is handled by the global route rate limiter.
        return hash('sha256', $normalizedLogin);
    }

    private function buildDeviceFingerprint(string $userAgent): string
    {
        $normalized = strtolower(trim($userAgent));
        if ($normalized === '') {
            $normalized = 'unknown-device';
        }

        return substr(hash('sha256', $normalized), 0, 64);
    }

    private function buildDeviceName(string $userAgent): string
    {
        $ua = strtolower($userAgent);
        $os = 'Unknown OS';
        $client = 'Unknown Client';

        if (str_contains($ua, 'windows')) {
            $os = 'Windows';
        } elseif (str_contains($ua, 'mac os') || str_contains($ua, 'macintosh')) {
            $os = 'macOS';
        } elseif (str_contains($ua, 'android')) {
            $os = 'Android';
        } elseif (str_contains($ua, 'iphone') || str_contains($ua, 'ipad') || str_contains($ua, 'ios')) {
            $os = 'iOS';
        } elseif (str_contains($ua, 'linux')) {
            $os = 'Linux';
        }

        if (str_contains($ua, 'edg/')) {
            $client = 'Edge';
        } elseif (str_contains($ua, 'chrome/')) {
            $client = 'Chrome';
        } elseif (str_contains($ua, 'firefox/')) {
            $client = 'Firefox';
        } elseif (str_contains($ua, 'safari/') && !str_contains($ua, 'chrome/')) {
            $client = 'Safari';
        }

        return $os . ' / ' . $client;
    }
}
