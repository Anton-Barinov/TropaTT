<?php
declare(strict_types=1);

namespace Module\Crm\GitlabIntegration\Service;

use Closure;
use RuntimeException;

final class GitLabClient
{
    private const MAX_RESPONSE_BYTES = 2097152;
    private int $timeout;
    private int $maxRetries;
    /** @var list<string> */
    private array $allowedHosts;
    private ?Closure $dnsResolver;
    private ?Closure $httpTransport;

    /** @param list<string> $allowedHosts Exact FQDNs approved by a CRM root administrator. */
    public function __construct(int $timeout = 30, int $maxRetries = 3, array $allowedHosts = ['gitlab.com'], ?Closure $dnsResolver = null, ?Closure $httpTransport = null)
    {
        $this->timeout = max(1, min(60, $timeout));
        $this->maxRetries = max(1, min(5, $maxRetries));
        $this->allowedHosts = self::normalizeAllowedHosts($allowedHosts, 'gitlab.com');
        $this->dnsResolver = $dnsResolver;
        $this->httpTransport = $httpTransport;
    }

    public static function assertCurlSupportAvailable(?bool $extensionLoaded = null, ?bool $curlInitAvailable = null): void
    {
        if (!(($extensionLoaded ?? extension_loaded('curl')) && ($curlInitAvailable ?? function_exists('curl_init')))) {
            throw new RuntimeException('GITLAB_TRANSPORT_UNAVAILABLE');
        }
    }

    public static function appendResponseChunk(string &$buffer, string $chunk, bool &$overflow): int
    {
        $length = strlen($chunk);
        if (strlen($buffer) + $length > self::MAX_RESPONSE_BYTES) {
            $overflow = true;
            return 0;
        }
        $buffer .= $chunk;
        return $length;
    }

    /** @param array<mixed> $hosts @return list<string> */
    public static function normalizeAllowedHosts(array $hosts, string $default = 'gitlab.com'): array
    {
        $normalized = [];
        foreach ($hosts as $host) {
            if (!is_string($host)) continue;
            $host = strtolower(rtrim(trim($host), '.'));
            if ($host === '' || strlen($host) > 253 || str_contains($host, '*') || str_contains($host, '/')
                || filter_var($host, FILTER_VALIDATE_IP) !== false
                || !preg_match('/^(?=.{1,253}$)(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?)(?:\.(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?))*$/', $host)) {
                continue;
            }
            $normalized[] = $host;
        }
        return array_values(array_unique($normalized ?: [$default]));
    }

    /** Validate URL syntax and exact-host trust without performing DNS or HTTP. */
    public static function validateBaseUrl(string $baseUrl, array $allowedHosts = ['gitlab.com']): bool
    {
        if ($baseUrl === '' || strlen($baseUrl) > 2048 || preg_match('/[\x00-\x20\x7f]/', $baseUrl)) return false;
        $parts = parse_url($baseUrl);
        if (!is_array($parts) || strtolower((string)($parts['scheme'] ?? '')) !== 'https'
            || isset($parts['user']) || isset($parts['pass']) || isset($parts['query']) || isset($parts['fragment'])
            || (isset($parts['port']) && (int)$parts['port'] !== 443)) return false;
        $host = strtolower(rtrim((string)($parts['host'] ?? ''), '.'));
        if ($host === '' || filter_var($host, FILTER_VALIDATE_IP) !== false) return false;
        return in_array($host, self::normalizeAllowedHosts($allowedHosts, 'gitlab.com'), true);
    }

    /** Resolve and pin every address to prevent DNS rebinding and private-network access. @return list<string> */
    public static function resolvePublicBaseUrl(string $baseUrl, array $allowedHosts = ['gitlab.com'], ?Closure $resolver = null): array
    {
        if (!self::validateBaseUrl($baseUrl, $allowedHosts)) throw new RuntimeException('GITLAB_UNTRUSTED_HOST');
        $host = strtolower(rtrim((string)parse_url($baseUrl, PHP_URL_HOST), '.'));
        if ($resolver !== null) { $addresses = $resolver($host); }
        else {
            if (!function_exists('dns_get_record') || !defined('DNS_A') || !defined('DNS_AAAA')) throw new RuntimeException('GITLAB_HOST_RESOLUTION_UNAVAILABLE');
            $records = @dns_get_record($host, DNS_A | DNS_AAAA);
            $addresses = [];
            foreach (is_array($records) ? $records : [] as $record) { $ip = (string)($record['ip'] ?? $record['ipv6'] ?? ''); if ($ip !== '') $addresses[] = $ip; }
        }
        $addresses = array_values(array_unique(array_map('strval', $addresses)));
        if ($addresses === []) throw new RuntimeException('GITLAB_HOST_RESOLUTION_FAILED');
        foreach ($addresses as $ip) if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) throw new RuntimeException('GITLAB_PRIVATE_DESTINATION_BLOCKED');
        return $addresses;
    }

    /**
     * @param array<string, mixed> $query
     * @return array<string, mixed>
     */
    public function request(string $token, string $baseUrl, string $method, string $path, array $query = [], ?array $jsonBody = null): array
    {
        if ($this->httpTransport === null) self::assertCurlSupportAvailable();
        $addresses = self::resolvePublicBaseUrl($baseUrl, $this->allowedHosts, $this->dnsResolver);
        $parts = parse_url($baseUrl);
        $host = strtolower(rtrim((string)($parts['host'] ?? ''), '.'));
        $url = rtrim($baseUrl, '/') . $path;
        if ($query !== []) {
            $url .= '?' . http_build_query($query);
        }
        $method = strtoupper($method);

        $headers = [
            'PRIVATE-TOKEN: ' . $token,
            'User-Agent: TropaTT-GitLab-Integration/1.0',
        ];
        if ($jsonBody !== null) $headers[] = 'Content-Type: application/json';

        for ($attempt = 1; $attempt <= $this->maxRetries; $attempt++) {
            if ($this->httpTransport !== null) {
                $mockResponse = ($this->httpTransport)($url, $method, $headers, $jsonBody, $addresses);
                $raw = (string)($mockResponse['body'] ?? '{}');
                $httpCode = (int)($mockResponse['http_code'] ?? 200);
                $curlError = (string)($mockResponse['error'] ?? '');
            } else {
                $ch = curl_init($url);
                if ($ch === false) {
                    throw new RuntimeException('GITLAB_TRANSPORT_UNAVAILABLE');
                }
                $raw = '';
                $responseTooLarge = false;
                curl_setopt_array($ch, [
                    CURLOPT_PROXY => '',
                    CURLOPT_WRITEFUNCTION => static function ($handle, string $chunk) use (&$raw, &$responseTooLarge): int {
                        return self::appendResponseChunk($raw, $chunk, $responseTooLarge);
                    },
                    CURLOPT_HTTPHEADER => $headers,
                    CURLOPT_TIMEOUT => $this->timeout,
                    CURLOPT_CONNECTTIMEOUT => 10,
                    CURLOPT_FOLLOWLOCATION => false,
                    CURLOPT_SSL_VERIFYPEER => true,
                    CURLOPT_SSL_VERIFYHOST => 2,
                    CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
                    CURLOPT_RESOLVE => array_map(static fn(string $ip): string => $host . ':443:' . (str_contains($ip, ':') ? '[' . $ip . ']' : $ip), $addresses),
                ]);
                if ($jsonBody !== null) {
                    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
                    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($jsonBody, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
                } elseif ($method === 'POST') {
                    curl_setopt($ch, CURLOPT_POST, true);
                } elseif ($method !== 'GET') {
                    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
                }

                $result = curl_exec($ch);
                $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);
                if ($responseTooLarge) {
                    throw new RuntimeException('GITLAB_RESPONSE_TOO_LARGE');
                }
                $curlError = $result === false ? 'request failed' : '';
            }
            if ($this->httpTransport !== null && strlen($raw) > self::MAX_RESPONSE_BYTES) {
                throw new RuntimeException('GITLAB_RESPONSE_TOO_LARGE');
            }

            if ($raw === false || $raw === '') {
                $raw = '{}';
            }

            if ($httpCode === 401) {
                throw new RuntimeException('GITLAB_AUTH_FAILED: invalid token', 401);
            }
            if ($httpCode === 403 || $httpCode === 429) {
                if ($attempt < $this->maxRetries) {
                    sleep(5 * $attempt);
                    continue;
                }
                throw new RuntimeException('GITLAB_RATE_LIMITED', 429);
            }
            if ($httpCode === 404) {
                throw new RuntimeException('GITLAB_NOT_FOUND', 404);
            }
            if ($httpCode < 200 || $httpCode >= 300) {
                throw new RuntimeException('GITLAB_ERROR: HTTP ' . $httpCode, $httpCode);
            }
            if ($curlError !== '') {
                throw new RuntimeException('GITLAB_TRANSPORT_FAILED', 0);
            }

            $decoded = json_decode((string)$raw, true);
            return is_array($decoded) ? $decoded : [];
        }

        throw new RuntimeException('GITLAB_RATE_LIMITED: max retries reached', 429);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listAll(string $token, string $baseUrl, string $path, array $query = []): array
    {
        $items = [];
        $page = 1;
        do {
            $data = $this->request($token, $baseUrl, 'GET', $path, array_merge($query, ['page' => $page, 'per_page' => 100]));
            if (!is_array($data)) {
                break;
            }
            foreach ($data as $item) {
                if (is_array($item)) {
                    $items[] = $item;
                }
            }
            $page++;
        } while (count($data) >= 100 && $page <= 50);
        return $items;
    }

    /**
     * @return array{success: bool, message: string, username: string|null}
     */
    public function testConnection(string $token, string $baseUrl): array
    {
        try {
            $data = $this->request($token, $baseUrl, 'GET', '/user');
            return ['success' => true, 'message' => 'Connection successful', 'username' => (string)($data['username'] ?? '')];
        } catch (\Throwable $e) {
            error_log('[GitLabClient::testConnection] ' . $e->getMessage());
            return ['success' => false, 'message' => 'Connection test failed. Check the token.', 'username' => null];
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listProjects(string $token, string $baseUrl): array
    {
        return $this->listAll($token, $baseUrl, '/projects', ['membership' => 'true', 'order_by' => 'last_activity_at']);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listMergeRequests(string $token, string $baseUrl, string $projectPath): array
    {
        return $this->listAll($token, $baseUrl, '/projects/' . rawurlencode($projectPath) . '/merge_requests', ['state' => 'all']);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listMergeRequestNotes(string $token, string $baseUrl, string $projectPath, int $iid): array
    {
        return $this->listAll($token, $baseUrl, '/projects/' . rawurlencode($projectPath) . '/merge_requests/' . $iid . '/notes');
    }

    /**
     * @return array<string, mixed>
     */
    public function createMergeRequestNote(string $token, string $baseUrl, string $projectPath, int $iid, string $body): array
    {
        return $this->request($token, $baseUrl, 'POST', '/projects/' . rawurlencode($projectPath) . '/merge_requests/' . $iid . '/notes', [], ['body' => $body]);
    }

    /**
     * Close or reopen a merge request.
     *
     * @param string $stateEvent Either "close" or "reopen".
     * @return array<string, mixed>
     */
    public function updateMergeRequestState(string $token, string $baseUrl, string $projectPath, int $iid, string $stateEvent): array
    {
        return $this->request($token, $baseUrl, 'PUT', '/projects/' . rawurlencode($projectPath) . '/merge_requests/' . $iid, [], ['state_event' => $stateEvent]);
    }
}
