<?php
declare(strict_types=1);

namespace Api\System\Library\Service;

use Api\Model\Auth\AuthRepository;
use Api\Model\Common\UserRepository;
use Api\System\Library\Logger\JsonLogger;
use Api\System\Library\Security\PasswordHasher;
use Api\System\Library\Security\TokenManager;
use Api\System\Library\Service\TwoFactorService;
use Api\System\Library\Support\Ulid;

final class AuthService
{
    private const PENDING_2FA_TTL = 300; // 5 minutes

    public function __construct(
        private readonly UserRepository $users,
        private readonly AuthRepository $auth,
        private readonly PasswordHasher $hasher,
        private readonly TokenManager $tokens,
        private readonly JsonLogger $logger,
        private readonly RateLimitService $rateLimiter,
        private readonly int $tokenTtlSeconds,
        private readonly int $maxSessionLifetimeSeconds,
        private readonly ?TwoFactorService $twoFactorService = null
    ) {
    }

    public function login(array $input, string $ip, string $userAgent): array
    {
        $login = trim((string)($input['login'] ?? ''));
        $password = (string)($input['password'] ?? '');
        $token = trim((string)($input['token'] ?? ''));

        // Global IP-based rate limit (SEC-01: prevents password spraying)
        $ipCheck = $this->checkIpRateLimit($ip);
        if ($ipCheck['blocked'] === true) {
            $this->logger->security([
                'event_type' => 'auth_ip_rate_limited',
                'login' => $login,
                'ip' => $ip,
                'user_agent' => $userAgent,
                'retry_after' => $ipCheck['retry_after'],
            ]);
            return [
                'ok' => false,
                'code' => 'AUTH_RATE_LIMITED',
                'retry_after' => $ipCheck['retry_after'],
            ];
        }

        // Per-login rate limit (file-based, SEC-01)
        $rateKey = $this->rateLimitKey($login, $ip);
        $check = $this->checkLoginRateLimit($rateKey);
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
            $state = $this->recordFailedLoginAttempt($rateKey, $ip);
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
            $state = $this->recordFailedLoginAttempt($rateKey, $ip);
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
            $state = $this->recordFailedLoginAttempt($rateKey, $ip);
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

        $this->clearLoginRateLimit($rateKey);

        // Check if 2FA is enabled for this user
        if ($this->twoFactorService !== null && $this->twoFactorService->requiresTwoFactor((int)$user['id'])) {
            $pendingToken = $this->createPendingTwoFactorToken($user);

            $this->logger->security([
                'event_type' => 'auth_two_factor_required',
                'user_public_id' => (string)$user['public_id'],
                'ip' => $ip,
                'user_agent' => $userAgent,
            ]);

            return [
                'ok' => true,
                'requires_two_factor' => true,
                'login_token' => $pendingToken,
                'expires_in' => self::PENDING_2FA_TTL,
                'user' => $this->normalizeUser($user),
            ];
        }

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

    public function me(string $accessToken, ?string $userAgent = null): ?array
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

        $createdAt = strtotime(((string)$session['created_at']) . ' UTC');
        if ($createdAt !== false && (time() - $createdAt) > $this->maxSessionLifetimeSeconds) {
            $this->auth->revokeByTokenHash($hash, gmdate('Y-m-d H:i:s'));
            return null;
        }

        // Older/current schemas do not store a separate session is_active flag;
        // revoked_at and expires_at already determine whether the session is active.
        if ((int)($session['is_active'] ?? 1) !== 1) {
            return null;
        }

        // SEC-007: device fingerprint verification
        // If a user agent is provided, derive a fingerprint and compare it against
        // the one stored when the session was created. A mismatch indicates potential
        // token theft (different browser/device than the original login).
        if ($userAgent !== null && $userAgent !== '') {
            $storedFingerprint = (string)($session['device_fingerprint'] ?? '');
            if ($storedFingerprint !== '') {
                $currentFingerprint = $this->buildDeviceFingerprint($userAgent);
                if ($currentFingerprint !== $storedFingerprint) {
                    $this->logger->security([
                        'event_type' => 'device_fingerprint_mismatch',
                        'session_public_id' => (string)$session['public_id'],
                        'user_public_id' => (string)($session['user_public_id'] ?? ''),
                        'stored_fingerprint' => $storedFingerprint,
                        'current_fingerprint' => $currentFingerprint,
                    ]);
                    $this->auth->revokeByTokenHash($hash, gmdate('Y-m-d H:i:s'));
                    return null;
                }
            }
        }

        // A session is read on every authenticated API request. Rewriting its
        // expiry every time creates unnecessary row locks and write pressure
        // when several users work concurrently. Keep the sliding window, but
        // refresh only after at least half of it has elapsed.
        $now = time();
        $sessionExpiresAt = strtotime(((string)$session['expires_at']) . ' UTC');
        $refreshThreshold = max(60, intdiv($this->tokenTtlSeconds, 2));
        $shouldRefreshSession = $sessionExpiresAt === false
            || ($sessionExpiresAt - $now) <= $refreshThreshold;

        if ($shouldRefreshSession) {
            $newExpiresAt = gmdate('Y-m-d H:i:s', $now + $this->tokenTtlSeconds);
            $this->auth->extendSessionByTokenHash($hash, $newExpiresAt);
            $expiresIn = $this->tokenTtlSeconds;
        } else {
            $newExpiresAt = (string)$session['expires_at'];
            $expiresIn = max(1, $sessionExpiresAt - $now);
        }

        $user = $this->normalizeUser([
            'id' => (int)($session['user_id'] ?? 0),
            'public_id' => (string)$session['user_public_id'],
            'login' => (string)$session['login'],
            'full_name' => (string)$session['full_name'],
            'locale' => (string)$session['locale'],
            'is_root' => (bool)$session['is_root'],
            'is_active' => (bool)($session['is_active'] ?? true),
            'created_by_user_id' => $session['created_by_user_id'] ?? null,
        ]);

        return [
            'session_public_id' => (string)$session['public_id'],
            'expires_at' => $newExpiresAt,
            'expires_in' => $expiresIn,
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

        // Treat users with the super_admin role as root even when the DB
        // is_root flag is stale or missing (self-hosted installations may
        // lose the flag after migrations or manual edits).
        if (!$isRoot && in_array('super_admin', $roleCodes, true)) {
            $isRoot = true;
        }

        $permissionCodes = $isRoot ? ['*'] : $this->auth->permissionCodesByUserId($userId);

        return [
            'id' => $userId,
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

    private function checkIpRateLimit(string $ip): array
    {
        // A successful login must not consume a shared IP quota: office and
        // mobile networks commonly put many legitimate users behind one NAT.
        return $this->rateLimiter->check('ip_login', $ip, 10, 60, 300, false);
    }

    private function hitIpRateLimit(string $ip): array
    {
        return $this->rateLimiter->check('ip_login', $ip, 10, 60, 300, true);
    }

    private function checkLoginRateLimit(string $rateKey): array
    {
        return $this->rateLimiter->check('login', $rateKey, 5, 300, 900, false);
    }

    private function hitLoginRateLimit(string $rateKey): array
    {
        return $this->rateLimiter->check('login', $rateKey, 5, 300, 900, true);
    }

    private function recordFailedLoginAttempt(string $rateKey, string $ip): array
    {
        $ipState = $this->hitIpRateLimit($ip);
        $loginState = $this->hitLoginRateLimit($rateKey);

        return [
            'blocked' => ($ipState['blocked'] ?? false) === true || ($loginState['blocked'] ?? false) === true,
            'retry_after' => max((int)($ipState['retry_after'] ?? 0), (int)($loginState['retry_after'] ?? 0)),
        ];
    }

    private function clearLoginRateLimit(string $rateKey): void
    {
        $this->rateLimiter->clear('login', $rateKey);
    }

    private function rateLimitKey(string $login, string $ip): string
    {
        $normalizedLogin = strtolower(trim($login));
        if ($normalizedLogin === '') {
            $normalizedLogin = '_';
        }

        // Combined login+IP key prevents targeted lockout-by-login (F1-2).
        // Per-login rate limiting is handled by a separate file-based limiter.
        // Using both: login-only for brute-force protection, login+IP for lockout prevention.
        return hash('sha256', $normalizedLogin) . ':' . hash('sha256', $ip);
    }

    private function buildDeviceFingerprint(string $userAgent): string
    {
        $normalized = strtolower(trim($userAgent));
        if ($normalized === '') {
            $normalized = 'unknown-device';
        }

        return substr(hash('sha256', $normalized), 0, 64);
    }

    /**
     * Create a signed pending 2FA token for a user who passed password verification.
     */
    private function createPendingTwoFactorToken(array $user): string
    {
        $payload = json_encode([
            'uid' => (int)($user['id'] ?? 0),
            'pub' => (string)($user['public_id'] ?? ''),
            'exp' => time() + self::PENDING_2FA_TTL,
            'nonce' => bin2hex(random_bytes(16)),
        ]);

        $signature = hash_hmac('sha256', $payload, $this->getPendingTokenKey());

        return base64_encode($signature . ':' . $payload);
    }

    /**
     * Resolve a pending 2FA token and return the user array if valid.
     * Returns null if token is invalid, expired, or tampered with.
     */
    public function resolveTwoFactorToken(string $token): ?array
    {
        $decoded = base64_decode($token, true);
        if ($decoded === false) {
            return null;
        }

        $separatorPos = strpos($decoded, ':');
        if ($separatorPos === false) {
            return null;
        }

        $signature = substr($decoded, 0, $separatorPos);
        $payload = substr($decoded, $separatorPos + 1);

        if ($signature === false || $payload === false || $signature === '' || $payload === '') {
            return null;
        }

        $expected = hash_hmac('sha256', $payload, $this->getPendingTokenKey());
        if (!hash_equals($expected, $signature)) {
            return null;
        }

        $data = json_decode($payload, true);
        if (!is_array($data) || !isset($data['exp']) || !isset($data['uid']) || !isset($data['pub'])) {
            return null;
        }

        if ((int)$data['exp'] < time()) {
            return null;
        }

        $user = $this->users->findById((int)$data['uid']);
        if (!$user || (string)($user['public_id'] ?? '') !== (string)$data['pub']) {
            return null;
        }

        return $user;
    }

    /**
     * Complete a 2FA login by issuing session tokens for the resolved user.
     */
    public function completeTwoFactorLogin(string $loginToken, string $ip, string $userAgent): ?array
    {
        $user = $this->resolveTwoFactorToken($loginToken);
        if (!$user) {
            return null;
        }

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
            'event_type' => 'auth_two_factor_completed',
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

    /**
     * Get the key used for signing pending 2FA tokens.
     *
     * SEC-003: 2FA pending token signing MUST use a stable cross-worker key.
     * PHP-FPM workers have independent memory, so a process-local random
     * fallback would cause signing (Worker A) and verification (Worker B)
     * to use different keys, silently invalidating every pending 2FA token
     * routed through a different worker. We therefore require APP_KEY to
     * be present in the environment — there is no safe per-WORKER fallback.
     * If APP_KEY is missing in production, security.php will already have
     * failed-fast on the related CSRF/WEBHOOK/AI secrets, so this throw
     * surfaces the misconfiguration at the earliest possible point.
     */
    private function getPendingTokenKey(): string
    {
        $appKey = trim((string) getenv('APP_KEY'));
        if ($appKey === '') {
            // Log before throw so operator sees root cause even though 2FA
            // controllers will surface a generic failure to the user.
            error_log('SECURITY CRITICAL: APP_KEY is not set; 2FA pending token signing refused. Set APP_KEY in .env.');
            throw new \RuntimeException(
                'APP_KEY is required for stable 2FA pending-token signing; '
                . 'set APP_KEY in .env or the process environment.'
            );
        }
        $seed = __CLASS__ . '::pending_2fa::' . $appKey;
        return hash('sha256', $seed, true);
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
