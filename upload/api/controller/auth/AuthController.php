<?php
declare(strict_types=1);

namespace Api\Controller\Auth;

use Api\Controller\Common\BaseController;
use Api\System\Library\Config;
use Api\System\Library\Service\AuthService;
use Api\System\Library\Validation\Validator;
use RuntimeException;

final class AuthController extends BaseController
{
    public function login(): \Api\System\Library\Http\JsonResponse
    {
        /** @var AuthService $auth */
        $auth = $this->container->get('service.auth');
        $input = $this->request()->allInput();

        $v = new Validator();
        $v->require($input, 'login', $this->t('common/messages.field_required'))
            ->maxLen($input, 'login', 120, $this->t('auth/messages.max_120'))
            ->require($input, 'password', $this->t('common/messages.field_required'))
            ->maxLen($input, 'password', 4096, $this->t('auth/messages.max_4096'));

        if ($v->fails()) {
            return $this->error('VALIDATION_ERROR', $this->t('common/messages.validation_error'), 422, $v->errors());
        }

        $result = $auth->login($input, $this->request()->ip(), $this->request()->userAgent());
        if (($result['ok'] ?? false) !== true) {
            if ((string)($result['code'] ?? '') === 'AUTH_RATE_LIMITED') {
                $retryAfter = max(1, (int)($result['retry_after'] ?? 1));
                return $this->error('AUTH_RATE_LIMITED', $this->t('auth/messages.rate_limited'), 429, [
                    'auth' => [$this->t('auth/messages.rate_limited')],
                ], [
                    'retry_after' => $retryAfter,
                ]);
            }

            return $this->error('UNAUTHORIZED', $this->t('auth/messages.invalid_credentials'), 401, [
                'auth' => [$this->t('auth/messages.invalid_credentials')],
            ]);
        }

        // If 2FA is required, return pending login token instead of session
        if (!empty($result['requires_two_factor'])) {
            $locale = trim((string)$this->request()->locale);
            if ($locale !== '') {
                $this->lang()->setLocale($locale);
            }

            return $this->success('TWO_FACTOR_REQUIRED', $this->t('security/messages.two_factor_required'), [
                'requires_two_factor' => true,
                'login_token' => (string)($result['login_token'] ?? ''),
                'expires_in' => (int)($result['expires_in'] ?? 300),
                'user' => $result['user'] ?? [],
            ]);
        }

        $locale = trim((string)$this->request()->locale);
        if ($locale !== '') {
            $this->lang()->setLocale($locale);
        }
        $this->issueSessionCookie((string)$result['access_token'], (int)($result['expires_in'] ?? 0));
        $csrfToken = $this->csrfTokenForSession((string)$result['access_token']);
        $this->issueCsrfCookie($csrfToken, (int)($result['expires_in'] ?? 0));

        return $this->success('AUTH_LOGIN_SUCCESS', $this->t('auth/messages.login_success'), [
            'access_token' => $result['access_token'],
            'token_type' => $result['token_type'],
            'expires_in' => $result['expires_in'],
            'session_public_id' => $result['session_public_id'],
            'csrf_token' => $csrfToken,
            'user' => $result['user'],
        ]);
    }

    public function logout(): \Api\System\Library\Http\JsonResponse
    {
        /** @var AuthService $auth */
        $auth = $this->container->get('service.auth');

        $token = $this->request()->bearerToken();
        if (!$token) {
            $token = $this->request()->cookie($this->cookieName(), '');
        }
        if (!$token) {
            return $this->error('UNAUTHORIZED', $this->t('auth/messages.token_required'), 401, [
                'auth' => [$this->t('auth/messages.token_required')],
            ]);
        }

        $ok = $auth->logout($token);
        if (!$ok) {
            return $this->error('SESSION_NOT_FOUND', $this->t('auth/messages.session_not_found'), 404, [
                'session' => [$this->t('auth/messages.session_not_found')],
            ]);
        }

        $this->clearSessionCookie();

        return $this->success('AUTH_LOGOUT_SUCCESS', $this->t('auth/messages.logout_success'));
    }

    public function me(): \Api\System\Library\Http\JsonResponse
    {
        $authUser = $this->user();
        if (!$authUser) {
            return $this->error('UNAUTHORIZED', $this->t('common/messages.unauthorized'), 401);
        }

        $csrfToken = '';
        $sessionToken = (string)($authUser['auth_token'] ?? '');
        if ($sessionToken !== '') {
            $ttlSeconds = max(60, (int)($authUser['expires_in'] ?? $this->config()->get('security.auth.access_token_ttl', 604800)));
            $this->issueSessionCookie($sessionToken, $ttlSeconds);
            $csrfToken = $this->csrfTokenForSession($sessionToken);
            $this->issueCsrfCookie($csrfToken, $ttlSeconds);
        }

        return $this->success('AUTH_ME', $this->t('auth/messages.me'), [
            'session_public_id' => $authUser['session_public_id'],
            'expires_at' => (string)($authUser['expires_at'] ?? ''),
            'expires_in' => (int)($authUser['expires_in'] ?? $this->config()->get('security.auth.access_token_ttl', 604800)),
            'csrf_token' => $csrfToken,
            'user' => $authUser['user'],
        ]);
    }

    private function issueSessionCookie(string $token, int $ttlSeconds): void
    {
        if ($token === '') {
            return;
        }

        setcookie($this->cookieName(), $token, [
            'expires' => time() + max(60, $ttlSeconds),
            'path' => $this->cookiePath(),
            'secure' => $this->cookieSecure(),
            'httponly' => true,
            'samesite' => $this->cookieSameSite(),
        ]);
    }

    private function clearSessionCookie(): void
    {
        setcookie($this->cookieName(), '', [
            'expires' => time() - 3600,
            'path' => $this->cookiePath(),
            'secure' => $this->cookieSecure(),
            'httponly' => true,
            'samesite' => $this->cookieSameSite(),
        ]);
        setcookie($this->csrfCookieName(), '', [
            'expires' => time() - 3600,
            'path' => $this->cookiePath(),
            'secure' => $this->cookieSecure(),
            'httponly' => false,
            'samesite' => $this->cookieSameSite(),
        ]);
    }

    private function issueCsrfCookie(string $token, int $ttlSeconds): void
    {
        if ($token === '') {
            return;
        }

        setcookie($this->csrfCookieName(), $token, [
            'expires' => time() + max(60, $ttlSeconds),
            'path' => $this->cookiePath(),
            'secure' => $this->cookieSecure(),
            'httponly' => false, // CSRF cookie should be readable by JS
            'samesite' => $this->cookieSameSite(),
        ]);
    }

    private function cookieName(): string
    {
        /** @var Config $config */
        $config = $this->config();
        return trim((string)$config->get('security.auth.cookie.name', 'crm_api_session')) ?: 'crm_api_session';
    }

    private function csrfCookieName(): string
    {
        return trim((string)$this->config()->get('security.auth.csrf.cookie', 'crm_csrf_token')) ?: 'crm_csrf_token';
    }

    private function cookiePath(): string
    {
        $config = $this->config();
        $path = trim((string)$config->get('security.auth.cookie.path', '/'));
        return $path !== '' ? $path : '/';
    }

    private function cookieSameSite(): string
    {
        $config = $this->config();
        $raw = strtolower(trim((string)$config->get('security.auth.cookie.same_site', 'strict')));

        return match ($raw) {
            'strict' => 'Strict',
            'none' => 'None',
            'lax' => 'Lax',
            default => 'Strict',
        };
    }

    private function cookieSecure(): bool
    {
        $config = $this->config();
        $secureOnly = (bool)$config->get('security.auth.cookie.secure_only', true);
        if (!$secureOnly) {
            return false;
        }

        $https = strtolower((string)($this->request()->server['HTTPS'] ?? ''));
        if ($https !== '' && $https !== 'off' && $https !== '0') {
            return true;
        }

        $forwardedProto = strtolower((string)$this->request()->header('X-Forwarded-Proto', ''));
        if ($forwardedProto === 'https') {
            return true;
        }

        return false;
    }

    private function csrfTokenForSession(string $sessionToken): string
    {
        $secret = trim((string)$this->config()->get('security.auth.csrf.secret_key', ''));
        if ($secret === '') {
            throw new RuntimeException('CONFIG_SECURITY_CSRF_SECRET_REQUIRED');
        }

        return hash_hmac('sha256', $sessionToken, $secret);
    }

    private function isProductionEnvironment(): bool
    {
        $env = strtolower(trim((string)$this->config()->get('default.app.env', 'prod')));
        return in_array($env, ['prod', 'production'], true);
    }

    private function config(): Config
    {
        /** @var Config $config */
        $config = $this->container->get('config');
        return $config;
    }
}
