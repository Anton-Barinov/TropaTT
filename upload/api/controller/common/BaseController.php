<?php
declare(strict_types=1);

namespace Api\Controller\Common;

use Api\System\Library\Cache\ApiFileCache;
use Api\System\Library\Container;
use Api\System\Library\Http\JsonResponse;
use Api\System\Library\Http\Request;
use Api\System\Library\Language\LanguageManager;

abstract class BaseController
{
    public function __construct(protected readonly Container $container)
    {
    }

    protected function request(): Request
    {
        return $this->container->get('request');
    }

    protected function user(): ?array
    {
        return $this->container->has('auth_user') ? $this->container->get('auth_user') : null;
    }

    protected function lang(): LanguageManager
    {
        return $this->container->get('lang');
    }

    protected function t(string $key, string $default = ''): string
    {
        return $this->lang()->get($key, $default !== '' ? $default : $key);
    }

    protected function success(string $code, string $message, array $data = [], int $status = 200, array $meta = []): JsonResponse
    {
        $request = $this->request();
        $sanitizedData = $this->sanitizePublicContract($data);

        return JsonResponse::success(
            code: $code,
            message: $message,
            data: $sanitizedData,
            status: $status,
            requestId: $request->requestId,
            correlationId: $request->correlationId,
            meta: $meta
        );
    }

    protected function error(string $code, string $message, int $status = 400, array $errors = [], array $meta = []): JsonResponse
    {
        $request = $this->request();

        return JsonResponse::error(
            code: $code,
            message: $message,
            status: $status,
            errors: $errors,
            requestId: $request->requestId,
            correlationId: $request->correlationId,
            meta: $meta
        );
    }

    /**
     * @param callable():JsonResponse $producer
     */
    protected function withIdempotency(callable $producer): JsonResponse
    {
        if (!$this->container->has('service.idempotency')) {
            return $producer();
        }

        /** @var \Api\System\Library\Service\IdempotencyService $service */
        $service = $this->container->get('service.idempotency');
        $request = $this->request();
        $actor = $this->user()['user'] ?? null;

        $replayed = $service->replay($request, is_array($actor) ? $actor : null);
        if ($replayed instanceof JsonResponse) {
            return $replayed;
        }

        $response = $producer();
        $service->remember($request, is_array($actor) ? $actor : null, $response);

        return $response;
    }

    protected function cacheApi(): ?ApiFileCache
    {
        if (!$this->container->has('cache.api')) {
            return null;
        }
        $cache = $this->container->get('cache.api');
        if (!($cache instanceof ApiFileCache)) {
            return null;
        }

        $enabled = $cache->isEnabled();
        $ttl = $cache->getDefaultTtl();

        if ($this->container->has('service.setting')) {
            try {
                $settingSvc = $this->container->get('service.setting');

                $enabledSetting = $settingSvc->get('system', 'api_file_cache_enabled');
                if ($enabledSetting !== null) {
                    $enabled = (bool)($enabledSetting['value'] ?? true);
                }

                $ttlSetting = $settingSvc->get('system', 'api_file_cache_ttl');
                if ($ttlSetting !== null) {
                    $val = $ttlSetting['value'] ?? null;
                    if ($val !== null && $val !== '') {
                        $ttl = max(1, (int)$val);
                    }
                }
            } catch (\Throwable $e) {
                error_log('[BaseController::cacheApi] ' . $e->getMessage());
                // Settings service unavailable — proceed with bootstrap config
            }
        }

        $cache->setEnabled($enabled);
        $cache->setDefaultTtl($ttl);

        return $enabled ? $cache : null;
    }

    protected function invalidateCache(string $namespace): void
    {
        $cache = $this->cacheApi();
        if ($cache !== null) {
            $cache->invalidateNamespace($namespace);
        }
    }

    protected function cacheUserId(): string
    {
        $auth = $this->user();
        // Prefer public_id (always present) over id (may be missing from auth user object)
        return (string)($auth['user']['public_id'] ?? $auth['user']['id'] ?? '0');
    }

    /**
     * Return only the allowed input keys from the request body.
     * Prevents mass assignment by discarding unexpected fields.
     *
     * @param array<int,string> $allowedKeys List of allowed parameter names
     * @return array<string,mixed>
     */
    protected function validatedInput(array $allowedKeys): array
    {
        $input = $this->request()->allInput();
        return array_intersect_key($input, array_flip($allowedKeys));
    }

    /**
     * File-based IP rate limit check-and-increment.
     * Delegates to the shared RateLimitService for consistency.
     *
     * @param string $prefix Unique prefix for the rate limit bucket (e.g. 'inv_accept', 'pw_reset_confirm')
     * @param int $maxAttempts Max attempts within the window
     * @param int $windowSeconds Window duration in seconds
     * @param int $lockSeconds Lock duration after hitting the limit
     */
    protected function checkIpRateLimit(string $prefix, int $maxAttempts = 20, int $windowSeconds = 60, int $lockSeconds = 300): array
    {
        if (!$this->container->has('service.rate_limiter')) {
            return ['blocked' => false, 'retry_after' => 0];
        }
        /** @var \Api\System\Library\Service\RateLimitService $rateLimiter */
        $rateLimiter = $this->container->get('service.rate_limiter');
        return $rateLimiter->check($prefix, $this->request()->ip(), $maxAttempts, $windowSeconds, $lockSeconds, true);
    }

    /** @param array<string,mixed>|array<int,mixed> $payload */
    private function sanitizePublicContract(array $payload): array
    {
        if (array_is_list($payload)) {
            $result = [];
            foreach ($payload as $item) {
                $result[] = is_array($item) ? $this->sanitizePublicContract($item) : $item;
            }

            return $result;
        }

        $result = [];
        foreach ($payload as $key => $value) {
            if (is_string($key) && ($this->isInternalIdentifierKey($key) || $this->isSensitiveKey($key))) {
                continue;
            }

            $result[$key] = is_array($value) ? $this->sanitizePublicContract($value) : $value;
        }

        return $result;
    }

    private function isInternalIdentifierKey(string $key): bool
    {
        if ($key === 'id') {
            return true;
        }

        // Allow author_user_id, assigned_user_id etc for frontend display
        if (in_array($key, ['author_user_id', 'assigned_user_id', 'created_by_user_id', 'updated_by_user_id', 'current_user_id'], true)) {
            return false;
        }

        if (in_array($key, ['request_id', 'correlation_id', 'job_id', 'backup_id'], true)) {
            return false;
        }

        if (str_ends_with($key, 'public_id')) {
            return false;
        }

        return str_ends_with($key, '_id');
    }

    private function isSensitiveKey(string $key): bool
    {
        static $exact = [
            'password',
            'password_hash',
            'token',
            'token_hash',
            'auth_token_hash',
            'key_hash',
            'secret',
            'secret_hash',
            'backup_codes',
        ];

        $normalized = strtolower($key);
        if (in_array($normalized, $exact, true)) {
            return true;
        }

        return str_contains($normalized, 'password') || str_contains($normalized, 'secret');
    }
}
