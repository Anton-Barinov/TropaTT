<?php
declare(strict_types=1);

namespace Api\System\Library\Http;

final class Request
{
    /** @var array<string,mixed> */
    private array $jsonBody = [];

    public function __construct(
        public readonly string $method,
        public readonly string $uri,
        public readonly string $path,
        public readonly array $query,
        public readonly array $post,
        public readonly array $cookies,
        public readonly array $files,
        public readonly array $server,
        public readonly array $headers,
        public readonly string $rawBody,
        public readonly string $requestId,
        public readonly string $correlationId,
        public readonly string $locale,
    ) {
        $decoded = json_decode($this->rawBody, true);
        if (is_array($decoded)) {
            $this->jsonBody = $decoded;
        }
    }

    public static function capture(string $defaultLocale = 'en-gb'): self
    {
        $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
        $uri = $_SERVER['REQUEST_URI'] ?? '/';
        $path = parse_url($uri, PHP_URL_PATH) ?: '/';
        $headers = self::collectHeaders();

        $requestId = self::normalizeTraceId((string)($headers['X-Request-Id'] ?? $headers['x-request-id'] ?? ''), 64);
        if ($requestId === '') {
            $requestId = bin2hex(random_bytes(8));
        }
        $correlationId = self::normalizeTraceId((string)($headers['X-Correlation-Id'] ?? $headers['x-correlation-id'] ?? ''), 64);
        if ($correlationId === '') {
            $correlationId = $requestId;
        }
        $locale = (string)($headers['X-Locale'] ?? $headers['x-locale'] ?? $defaultLocale);

        return new self(
            method: $method,
            uri: $uri,
            path: $path,
            query: $_GET,
            post: $_POST,
            cookies: $_COOKIE,
            files: $_FILES,
            server: $_SERVER,
            headers: $headers,
            rawBody: file_get_contents('php://input') ?: '',
            requestId: $requestId,
            correlationId: $correlationId,
            locale: $locale,
        );
    }

    private static function collectHeaders(): array
    {
        if (function_exists('getallheaders')) {
            $headers = getallheaders();
            if (is_array($headers) && $headers !== []) {
                return $headers;
            }
        }

        $headers = [];
        foreach ($_SERVER as $key => $value) {
            if (!is_string($key)) {
                continue;
            }

            if (str_starts_with($key, 'HTTP_')) {
                $name = str_replace('_', '-', strtolower(substr($key, 5)));
                $name = implode('-', array_map('ucfirst', explode('-', $name)));
                $headers[$name] = (string)$value;
            } elseif (in_array($key, ['CONTENT_TYPE', 'CONTENT_LENGTH'], true)) {
                $name = str_replace('_', '-', strtolower($key));
                $name = implode('-', array_map('ucfirst', explode('-', $name)));
                $headers[$name] = (string)$value;
            }
        }

        return $headers;
    }

    private static function normalizeTraceId(string $raw, int $maxLen): string
    {
        $value = trim($raw);
        if ($value === '') {
            return '';
        }

        $value = preg_replace('/[^A-Za-z0-9._:-]/', '', $value) ?? '';
        if ($value === '') {
            return '';
        }

        return substr($value, 0, max(16, $maxLen));
    }

    public function header(string $name, ?string $default = null): ?string
    {
        foreach ($this->headers as $k => $v) {
            if (strtolower($k) === strtolower($name)) {
                return is_array($v) ? (string)($v[0] ?? $default) : (string)$v;
            }
        }
        return $default;
    }

    public function bearerToken(): ?string
    {
        $auth = $this->header('Authorization');
        if (!$auth) {
            return null;
        }

        if (preg_match('/Bearer\s+(.+)/i', $auth, $m)) {
            return trim($m[1]);
        }

        return null;
    }

    public function input(string $key, mixed $default = null): mixed
    {
        if (array_key_exists($key, $this->jsonBody)) {
            return $this->jsonBody[$key];
        }
        if (array_key_exists($key, $this->post)) {
            return $this->post[$key];
        }
        return $this->query[$key] ?? $default;
    }

    public function cookie(string $key, ?string $default = null): ?string
    {
        if (!array_key_exists($key, $this->cookies)) {
            return $default;
        }

        $value = $this->cookies[$key];
        if (is_array($value)) {
            return (string)($value[0] ?? $default);
        }

        return (string)$value;
    }

    public function allInput(): array
    {
        return array_replace($this->query, $this->post, $this->jsonBody);
    }

    public function ip(): string
    {
        return (string)($this->server['REMOTE_ADDR'] ?? '0.0.0.0');
    }

    public function userAgent(): string
    {
        return (string)($this->server['HTTP_USER_AGENT'] ?? 'unknown');
    }
}
