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
