<?php
declare(strict_types=1);

namespace Api\System\Library\Http;

final class Request
{
    public const MCP_MAX_RAW_BODY_BYTES = 28 * 1024 * 1024;
    public const MCP_PROTOCOL_VERSION = '2025-06-18';
    public const MCP_MODERN_PROTOCOL_VERSION = '2026-07-28';
    private const MCP_MAX_JSON_STRUCTURAL_TOKENS = 10000;
    private const MCP_MAX_JSON_NESTING = 64;
    private const PUBLIC_FORM_MAX_RAW_BODY_BYTES = 32769;
    private const WHATSAPP_WEBHOOK_MAX_RAW_BODY_BYTES = 262145;
    private const PAYSTACK_WEBHOOK_MAX_RAW_BODY_BYTES = 262145;
    private const TELEGRAM_WEBHOOK_MAX_RAW_BODY_BYTES = 1048577;
    private const ECOMMERCE_INGEST_MAX_RAW_BODY_BYTES = 1048577;
    private const DRAWIO_MAX_RAW_BODY_BYTES = 2000001;
    private const HUBSPOT_MAX_RAW_BODY_BYTES = 600000;

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
        public readonly bool $bodyTooLarge = false,
        bool $decodeJsonBody = true,
    ) {
        if ($decodeJsonBody && !$this->bodyTooLarge) {
            $decoded = json_decode($this->rawBody, true);
            if (is_array($decoded)) {
                $this->jsonBody = $decoded;
            }
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

        $isMcp = self::isMcpTransport($method, $path, $_GET);
        $rawBodyLimit = self::rawBodyLimit($method, $path, $_GET);
        $bodyTooLarge = $isMcp && self::mcpContentLengthExceedsLimit($_SERVER);
        if (!$bodyTooLarge && $rawBodyLimit !== null) {
            // Reject a declared oversized body before opening php://input. If
            // length is absent or untrusted, the bounded read below still caps
            // memory use for chunked/unknown-length requests.
            $bodyTooLarge = self::contentLengthExceedsLimit($_SERVER, $rawBodyLimit - 1);
        }
        $rawBodyStream = $bodyTooLarge ? false : fopen('php://input', 'rb');
        $rawBody = '';
        if (is_resource($rawBodyStream)) {
            $rawBody = $rawBodyLimit === null
                ? (stream_get_contents($rawBodyStream) ?: '')
                : (stream_get_contents($rawBodyStream, $rawBodyLimit) ?: '');
            fclose($rawBodyStream);
        }
        if ($rawBodyLimit !== null && strlen($rawBody) >= $rawBodyLimit) {
            $bodyTooLarge = true;
            $rawBody = '';
        } elseif ($isMcp && strlen($rawBody) > self::MCP_MAX_RAW_BODY_BYTES) {
            $bodyTooLarge = true;
            $rawBody = '';
        }

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
            rawBody: $rawBody,
            requestId: $requestId,
            correlationId: $correlationId,
            locale: $locale,
            bodyTooLarge: $bodyTooLarge,
            decodeJsonBody: !$isMcp,
        );
    }

    public static function resolveRoutePath(string $path, array $query = []): string
    {
        $rawRoute = !empty($query['route']) ? $query['route'] : (!empty($query['_route_']) ? $query['_route_'] : '');
        if (is_scalar($rawRoute) && (string)$rawRoute !== '') {
            $rawRoute = trim((string)$rawRoute, '/');
            if (str_starts_with($rawRoute, 'web/')) {
                $rawRoute = substr($rawRoute, 4);
            }
            [$rawRoute] = explode('?', $rawRoute, 2);
            return preg_replace('#/+#', '/', '/' . ltrim($rawRoute, '/')) ?: '/';
        }
        $normalizedPath = preg_replace('#^/api/index\.php#', '', $path) ?? $path;
        $normalizedPath = preg_replace('#^/index\.php#', '', $normalizedPath) ?? $normalizedPath;
        if (str_starts_with($normalizedPath, '/v1/')) {
            $normalizedPath = '/api' . $normalizedPath;
        }
        return preg_replace('#/+#', '/', $normalizedPath) ?: '/';
    }

    public static function isMcpTransport(string $method, string $path, array $query = []): bool
    {
        return strtoupper($method) === 'POST' && self::resolveRoutePath($path, $query) === '/api/v1/mcp';
    }

    /**
     * MCP's Origin check must run before auth and request-size short-circuits,
     * so it is shared by the front controller and MCP handler. Requests from
     * non-browser clients may omit Origin; when present, it must be same-origin.
     */
    public static function isMcpOriginAllowed(array $server, array $headers = []): bool
    {
        $origin = '';
        foreach ($headers as $name => $value) {
            if (strtolower((string)$name) === 'origin') {
                $origin = trim(is_array($value) ? (string)($value[0] ?? '') : (string)$value);
                break;
            }
        }
        if ($origin === '') {
            $origin = trim((string)($server['HTTP_ORIGIN'] ?? ''));
        }
        if ($origin === '') {
            return true;
        }

        try {
            $originParts = parse_url($origin);
            $requestHost = trim((string)($server['HTTP_HOST'] ?? $server['SERVER_NAME'] ?? ''));
            $requestParts = $requestHost !== '' ? parse_url('//' . $requestHost) : false;
        } catch (\Throwable) {
            return false;
        }
        if (!is_array($originParts) || !is_array($requestParts)) {
            return false;
        }

        $originScheme = strtolower((string)($originParts['scheme'] ?? ''));
        $originHost = strtolower((string)($originParts['host'] ?? ''));
        $requestHostName = strtolower((string)($requestParts['host'] ?? ''));
        $https = strtolower((string)($server['HTTPS'] ?? ''));
        $requestScheme = ($https !== '' && $https !== 'off' && $https !== '0') ? 'https' : 'http';

        $forwardedProto = '';
        foreach ($headers as $name => $value) {
            if (strtolower((string)$name) === 'x-forwarded-proto') {
                $forwardedProto = strtolower(trim(is_array($value) ? (string)($value[0] ?? '') : (string)$value));
                break;
            }
        }
        if ($forwardedProto === '') {
            $forwardedProto = strtolower(trim((string)($server['HTTP_X_FORWARDED_PROTO'] ?? '')));
        }
        if (in_array($forwardedProto, ['http', 'https'], true)) {
            $requestScheme = $forwardedProto;
        } elseif (in_array(strtolower((string)($server['REQUEST_SCHEME'] ?? '')), ['http', 'https'], true)) {
            $requestScheme = strtolower((string)$server['REQUEST_SCHEME']);
        }

        $originPort = (int)($originParts['port'] ?? ($originScheme === 'https' ? 443 : 80));
        $requestPort = (int)($requestParts['port'] ?? ($requestScheme === 'https' ? 443 : 80));
        $hasOriginTail = isset($originParts['user']) || isset($originParts['pass'])
            || isset($originParts['query']) || isset($originParts['fragment'])
            || isset($originParts['path']);

        return in_array($originScheme, ['http', 'https'], true)
            && $originHost !== ''
            && $requestHostName !== ''
            && !$hasOriginTail
            && $originPort >= 1 && $originPort <= 65535
            && $requestPort >= 1 && $requestPort <= 65535
            && $originScheme === $requestScheme
            && $originPort === $requestPort
            && strcasecmp($originHost, $requestHostName) === 0;
    }

    public function mcpOriginIsAllowed(): bool
    {
        return self::isMcpOriginAllowed($this->server, $this->headers);
    }

    public static function mcpContentLengthExceedsLimit(array $server): bool
    {
        return self::contentLengthExceedsLimit($server, self::MCP_MAX_RAW_BODY_BYTES);
    }

    public static function contentLengthExceedsLimit(array $server, int $maxBytes): bool
    {
        if ($maxBytes < 0) {
            return true;
        }
        $length = trim((string)($server['CONTENT_LENGTH'] ?? $server['HTTP_CONTENT_LENGTH'] ?? ''));
        if ($length === '' || !ctype_digit($length)) {
            return false;
        }
        $length = ltrim($length, '0');
        if ($length === '') {
            $length = '0';
        }
        $limit = (string)$maxBytes;
        return strlen($length) > strlen($limit) || (strlen($length) === strlen($limit) && strcmp($length, $limit) > 0);
    }

    public static function mcpJsonExceedsComplexityLimit(string $body): bool
    {
        $inString = false;
        $escaped = false;
        $depth = 0;
        $tokens = 0;
        $length = strlen($body);
        for ($index = 0; $index < $length; $index++) {
            $character = $body[$index];
            if ($inString) {
                if ($escaped) {
                    $escaped = false;
                } elseif ($character === '\\') {
                    $escaped = true;
                } elseif ($character === '"') {
                    $inString = false;
                }
                continue;
            }
            if ($character === '"') {
                $inString = true;
                continue;
            }
            if ($character === '{' || $character === '[') {
                if (++$depth > self::MCP_MAX_JSON_NESTING) {
                    return true;
                }
            } elseif ($character === '}' || $character === ']') {
                $depth--;
            } elseif ($character === ',' || $character === ':') {
                if (++$tokens > self::MCP_MAX_JSON_STRUCTURAL_TOKENS) {
                    return true;
                }
            }
        }
        return false;
    }

    private static function rawBodyLimit(string $method, string $path, array $query = []): ?int
    {
        $normalizedPath = self::resolveRoutePath($path, $query);

        $method = strtoupper($method);
        $drawioRoute = preg_match('#^/(?:api/v1/)?_module/crm\.drawio/diagrams(?:/[A-Za-z0-9_-]{1,64})?$#D', $normalizedPath) === 1;
        if ($drawioRoute && in_array($method, ['POST', 'PATCH'], true)) {
            return self::DRAWIO_MAX_RAW_BODY_BYTES;
        }
        if ($method !== 'POST') {
            return null;
        }
        if ($normalizedPath === '/api/v1/mcp') {
            return self::MCP_MAX_RAW_BODY_BYTES + 1;
        }

        if (preg_match('#^/(?:api/v1/)?_module/crm\.telegram-bot/webhook/[1-9][0-9]{0,9}$#D', $normalizedPath) === 1) {
            return self::TELEGRAM_WEBHOOK_MAX_RAW_BODY_BYTES;
        }

        if (preg_match('#^/api/v1/_module/crm\.hubspot-migration/(?:sessions|sessions/contacts|sessions/[A-Za-z0-9_-]{1,64}/(?:import|discard-pending|rollback))$#D', $normalizedPath) === 1) {
            return self::HUBSPOT_MAX_RAW_BODY_BYTES + 1;
        }

        if (preg_match('#^/api/v1/_module/crm\.public-forms/forms/[A-Za-z0-9_-]{1,64}/submit$#D', $normalizedPath) === 1) {
            return self::PUBLIC_FORM_MAX_RAW_BODY_BYTES;
        }

        if (preg_match('#^/api/v1/_module/crm\.whatsapp-business/webhook/[A-Za-z0-9_-]{1,128}$#D', $normalizedPath) === 1) {
            return self::WHATSAPP_WEBHOOK_MAX_RAW_BODY_BYTES;
        }

        if (preg_match('#^/api/v1/_module/crm\.paystack-africa/webhook/[A-Fa-f0-9]{32}$#D', $normalizedPath) === 1) {
            return self::PAYSTACK_WEBHOOK_MAX_RAW_BODY_BYTES;
        }

        if (preg_match('#^/(?:api/v1/)?_module/crm\.ecommerce-gateway/v1/(?:orders|quick-orders|callbacks|feedback|forms|stock)$#D', $normalizedPath) === 1) {
            return self::ECOMMERCE_INGEST_MAX_RAW_BODY_BYTES;
        }

        return null;
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
            // PHP turns numeric-string array keys ("0", "1", ...) into integers, so a
            // malformed request header must be cast before strtolower() — otherwise the
            // whole API request dies with a TypeError instead of a 400/401 answer.
            if (strtolower((string)$k) === strtolower($name)) {
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

    /**
     * SEC-005: Trusted proxies configuration (resolved at runtime).
     * Format: array of CIDR strings e.g. ['10.0.0.0/8', '192.168.0.0/16']
     * Passed in from security config via constructor or setter.
     */
    private array $trustedProxies = [];
    private string $trustedProxyHeader = 'X-Forwarded-For';

    public function setTrustedProxies(array $cidrs, string $header = 'X-Forwarded-For'): void
    {
        $this->trustedProxies = $cidrs;
        $this->trustedProxyHeader = $header;
    }

    /**
     * Raw REMOTE_ADDR — the actual TCP connection peer.
     * Always use this for security-sensitive checks (loopback, installer).
     */
    public function remoteAddr(): string
    {
        return (string)($this->server['REMOTE_ADDR'] ?? '0.0.0.0');
    }

    /**
     * Client IP with optional trusted proxy support.
     * If REMOTE_ADDR is a trusted proxy, parse the configured forward-header
     * from right to left and return the first untrusted address.
     * If no trusted proxies are configured, returns raw REMOTE_ADDR.
     */
    public function clientIp(): string
    {
        $remoteAddr = $this->remoteAddr();

        if ($this->trustedProxies === []) {
            return $remoteAddr;
        }

        if (!$this->ipInTrustedRanges($remoteAddr)) {
            return $remoteAddr;
        }

        $headerValue = $this->header($this->trustedProxyHeader, '');
        if ($headerValue === '') {
            return $remoteAddr;
        }

        // Parse right-to-left: the rightmost address is the immediate upstream
        $addresses = array_map('trim', explode(',', $headerValue));
        $addresses = array_reverse($addresses);

        foreach ($addresses as $addr) {
            if ($addr !== '' && !$this->ipInTrustedRanges($addr)) {
                if (filter_var($addr, FILTER_VALIDATE_IP)) {
                    return $addr;
                }
            }
        }

        return $remoteAddr;
    }

    /**
     * Alias for clientIp() — backward compatibility.
     */
    public function ip(): string
    {
        return $this->clientIp();
    }

    /**
     * Check if the given IP string falls within any configured trusted CIDR range.
     */
    private function ipInTrustedRanges(string $ip): bool
    {
        $ip = trim($ip);
        if ($ip === '' || !filter_var($ip, FILTER_VALIDATE_IP)) {
            return false;
        }

        $packed = inet_pton($ip);
        if ($packed === false) {
            return false;
        }

        foreach ($this->trustedProxies as $cidr) {
            if ($this->cidrMatch($packed, $cidr)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Match a packed IP (from inet_pton) against a CIDR string.
     * Supports IPv4 and IPv6, single addresses (/32 for v4, /128 for v6),
     * full ranges (/0), and edge cases.
     */
    private function cidrMatch(string $packedIp, string $cidr): bool
    {
        $cidr = trim($cidr);
        if ($cidr === '') {
            return false;
        }

        // Parse prefix length
        if (str_contains($cidr, '/')) {
            $parts = explode('/', $cidr, 2);
            $network = trim($parts[0]);
            $prefix = (int)$parts[1];
        } else {
            $network = $cidr;
            // Default prefix: /32 for IPv4, /128 for IPv6
            $prefix = filter_var($network, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) ? 128 : 32;
        }

        $packedNetwork = @inet_pton($network);
        if ($packedNetwork === false) {
            return false;
        }

        // Length must match (both IPv4 or both IPv6)
        if (strlen($packedIp) !== strlen($packedNetwork)) {
            // Handle IPv4-mapped IPv6 (::ffff:x.x.x.x)
            if (strlen($packedNetwork) === 4 && strlen($packedIp) === 16) {
                // Extract the last 4 bytes of the packed IPv6 to compare as IPv4
                $packedIp = substr($packedIp, -4);
            } elseif (strlen($packedNetwork) === 16 && strlen($packedIp) === 4) {
                $packedNetwork = substr($packedNetwork, -4);
            } else {
                return false;
            }
        }

        $bitLen = strlen($packedIp) * 8;
        $prefix = max(0, min($bitLen, $prefix));

        if ($prefix === 0) {
            return true; // /0 matches everything
        }

        if ($prefix === $bitLen) {
            return $packedIp === $packedNetwork; // /32 or /128 — exact match
        }

        // Bitwise comparison: create mask and compare
        $mask = str_repeat("\xff", (int)($prefix / 8));
        $remainderBits = $prefix % 8;
        if ($remainderBits > 0) {
            $mask .= chr(0xff << (8 - $remainderBits) & 0xff);
        }
        $mask = str_pad($mask, strlen($packedIp), "\x00");

        return ($packedIp & $mask) === ($packedNetwork & $mask);
    }

    public function userAgent(): string
    {
        return (string)($this->server['HTTP_USER_AGENT'] ?? 'unknown');
    }
}
