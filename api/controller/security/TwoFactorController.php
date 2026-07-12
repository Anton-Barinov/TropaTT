<?php
declare(strict_types=1);

namespace Api\Controller\Security;

use Api\Controller\Common\BaseController;
use Api\System\Library\Config;
use Api\System\Library\Service\AuthService;
use Api\System\Library\Service\TwoFactorService;
use Api\System\Library\Validation\Validator;
use RuntimeException;

final class TwoFactorController extends BaseController
{
    public function status(): \Api\System\Library\Http\JsonResponse
    {
        $auth = $this->user();
        if (!$auth) {
            return $this->error('UNAUTHORIZED', $this->t('common/messages.unauthorized'), 401);
        }

        /** @var TwoFactorService $service */
        $service = $this->container->get('service.two_factor');
        $result = $service->status($auth['user']);

        return $this->success('TWO_FACTOR_STATUS', $this->t('security/messages.two_factor_status'), [
            'enabled' => (bool)$result['enabled'],
            'two_factor' => $result['two_factor'],
        ]);
    }

    public function enable(): \Api\System\Library\Http\JsonResponse
    {
        $auth = $this->user();
        if (!$auth) {
            return $this->error('UNAUTHORIZED', $this->t('common/messages.unauthorized'), 401);
        }

        $input = $this->request()->allInput();
        $validator = new Validator();
        $validator->require($input, 'current_password', $this->t('common/messages.field_required'));
        if ($validator->fails()) {
            return $this->error('VALIDATION_ERROR', $this->t('common/messages.validation_error'), 422, $validator->errors());
        }

        /** @var TwoFactorService $service */
        $service = $this->container->get('service.two_factor');
        $result = $service->enable($auth['user'], (string)$input['current_password']);
        if (!(bool)($result['ok'] ?? false)) {
            $code = (string)($result['code'] ?? 'TWO_FACTOR_ENABLE_FAILED');
            $status = match ($code) {
                'INVALID_CURRENT_PASSWORD' => 422,
                'USER_NOT_FOUND' => 404,
                'TWO_FACTOR_ALREADY_ENABLED' => 409,
                default => 400,
            };

            return $this->error($code, $this->t('security/messages.two_factor_enable_failed'), $status, [
                'two_factor' => [$code],
            ]);
        }

        return $this->success('TWO_FACTOR_ENABLED', $this->t('security/messages.two_factor_enabled'), [
            'two_factor' => $result['two_factor'],
            'setup_code' => $result['setup_secret'],
            'recovery_codes' => $result['backup_codes'],
        ]);
    }

    public function disable(): \Api\System\Library\Http\JsonResponse
    {
        $auth = $this->user();
        if (!$auth) {
            return $this->error('UNAUTHORIZED', $this->t('common/messages.unauthorized'), 401);
        }

        $input = $this->request()->allInput();
        $validator = new Validator();
        $validator->require($input, 'current_password', $this->t('common/messages.field_required'));
        if ($validator->fails()) {
            return $this->error('VALIDATION_ERROR', $this->t('common/messages.validation_error'), 422, $validator->errors());
        }

        /** @var TwoFactorService $service */
        $service = $this->container->get('service.two_factor');
        $result = $service->disable($auth['user'], (string)$input['current_password']);
        if (!(bool)($result['ok'] ?? false)) {
            $code = (string)($result['code'] ?? 'TWO_FACTOR_DISABLE_FAILED');
            $status = match ($code) {
                'INVALID_CURRENT_PASSWORD' => 422,
                'USER_NOT_FOUND' => 404,
                'TWO_FACTOR_NOT_ENABLED' => 409,
                default => 400,
            };

            return $this->error($code, $this->t('security/messages.two_factor_disable_failed'), $status, [
                'two_factor' => [$code],
            ]);
        }

        return $this->success('TWO_FACTOR_DISABLED', $this->t('security/messages.two_factor_disabled'));
    }

    public function verify(): \Api\System\Library\Http\JsonResponse
    {
        $input = $this->request()->allInput();
        $loginToken = trim((string)($input['login_token'] ?? ''));
        $code = trim((string)($input['code'] ?? ''));
        $isBackup = (bool)($input['is_backup'] ?? false);

        $validator = new Validator();
        $validator->require($input, 'login_token', $this->t('common/messages.field_required'))
            ->require($input, 'code', $this->t('common/messages.field_required'))
            ->maxLen($input, 'code', 10, $this->t('security/messages.max_10'));
        if ($validator->fails()) {
            return $this->error('VALIDATION_ERROR', $this->t('common/messages.validation_error'), 422, $validator->errors());
        }

        // Resolve the user from the temporary login token
        /** @var AuthService $authService */
        $authService = $this->container->get('service.auth');
        $user = $authService->resolveTwoFactorToken($loginToken);
        if (!$user) {
            return $this->error('TWO_FACTOR_TOKEN_INVALID', $this->t('security/messages.two_factor_token_invalid'), 401, [
                'two_factor' => [$this->t('security/messages.two_factor_token_invalid')],
            ]);
        }

        /** @var TwoFactorService $service */
        $service = $this->container->get('service.two_factor');

        if ($isBackup) {
            $result = $service->verifyBackupCode($user, $code);
        } else {
            $result = $service->verifyTotp($user, $code);
        }

        if (!(bool)($result['ok'] ?? false)) {
            $errorCode = (string)($result['code'] ?? 'TWO_FACTOR_VERIFY_FAILED');
            return $this->error($errorCode, $this->t('security/messages.two_factor_verify_failed'), 401, [
                'two_factor' => [$errorCode],
            ]);
        }

        // Complete the login — issue session and CSRF tokens
        $issuedToken = $authService->completeTwoFactorLogin($loginToken, $this->request()->ip(), $this->request()->userAgent());
        if (!$issuedToken) {
            return $this->error('TWO_FACTOR_LOGIN_FAILED', $this->t('security/messages.two_factor_login_failed'), 500);
        }

        $locale = trim((string)$this->request()->locale);
        if ($locale !== '') {
            $this->lang()->setLocale($locale);
        }
        $this->issueSessionCookie((string)$issuedToken['access_token'], (int)($issuedToken['expires_in'] ?? 0));
        $csrfToken = $this->csrfTokenForSession((string)$issuedToken['access_token']);
        $this->issueCsrfCookie($csrfToken, (int)($issuedToken['expires_in'] ?? 0));

        return $this->success('TWO_FACTOR_VERIFIED', $this->t('security/messages.two_factor_verified'), [
            'access_token' => $issuedToken['access_token'],
            'token_type' => $issuedToken['token_type'],
            'expires_in' => $issuedToken['expires_in'],
            'session_public_id' => $issuedToken['session_public_id'],
            'csrf_token' => $csrfToken,
            'user' => $issuedToken['user'],
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

    private function issueCsrfCookie(string $token, int $ttlSeconds): void
    {
        if ($token === '') {
            return;
        }

        setcookie($this->csrfCookieName(), $token, [
            'expires' => time() + max(60, $ttlSeconds),
            'path' => $this->cookiePath(),
            'secure' => $this->cookieSecure(),
            'httponly' => false,
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
        /** @var Config $config */
        $config = $this->config();
        $path = trim((string)$config->get('security.auth.cookie.path', '/'));
        return $path !== '' ? $path : '/';
    }

    private function cookieSameSite(): string
    {
        /** @var Config $config */
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
        /** @var Config $config */
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

    private function config(): Config
    {
        /** @var Config $config */
        $config = $this->container->get('config');
        return $config;
    }

    private function csrfTokenForSession(string $sessionToken): string
    {
        $secret = trim((string)$this->config()->get('security.auth.csrf.secret_key', ''));
        if ($secret === '') {
            throw new RuntimeException('CONFIG_SECURITY_CSRF_SECRET_REQUIRED');
        }

        return hash_hmac('sha256', $sessionToken, $secret);
    }
}
