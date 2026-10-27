<?php
declare(strict_types=1);

namespace Module\Crm\GithubIntegration\Service;

use Closure;
use RuntimeException;

final class GitHubClient
{
    private const MAX_RESPONSE_BYTES = 2097152;
    private int $timeout;
    private int $maxRetries;
    /** @var list<string> */
    private array $allowedHosts;
    private ?Closure $dnsResolver;
    private ?Closure $httpTransport;

    /** @param list<string> $allowedHosts Exact FQDNs approved by a CRM root administrator. */
    public function __construct(int $timeout = 30, int $maxRetries = 3, array $allowedHosts = ['api.github.com'], ?Closure $dnsResolver = null, ?Closure $httpTransport = null)
    {
        $this->timeout = max(1, min(60, $timeout));
        $this->maxRetries = max(1, min(5, $maxRetries));
        $this->allowedHosts = self::normalizeAllowedHosts($allowedHosts, 'api.github.com');
        $this->dnsResolver = $dnsResolver;
        $this->httpTransport = $httpTransport;
    }

    public static function assertCurlSupportAvailable(?bool $extensionLoaded = null, ?bool $curlInitAvailable = null): void
    {
        if (!(($extensionLoaded ?? extension_loaded('curl')) && ($curlInitAvailable ?? function_exists('curl_init')))) {
            throw new RuntimeException('GITHUB_TRANSPORT_UNAVAILABLE');
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
    public static function normalizeAllowedHosts(array $hosts, string $default = 'api.github.com'): array
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
    public static function validateBaseUrl(string $baseUrl, array $allowedHosts = ['api.github.com']): bool
    {
        if ($baseUrl === '' || strlen($baseUrl) > 2048 || preg_match('/[\x00-\x20\x7f]/', $baseUrl)) return false;
        $parts = parse_url($baseUrl);
        if (!is_array($parts) || strtolower((string)($parts['scheme'] ?? '')) !== 'https'
            || isset($parts['user']) || isset($parts['pass']) || isset($parts['query']) || isset($parts['fragment'])
            || (isset($parts['port']) && (int)$parts['port'] !== 443)) return false;
        $host = strtolower(rtrim((string)($parts['host'] ?? ''), '.'));
        if ($host === '' || filter_var($host, FILTER_VALIDATE_IP) !== false) return false;
        return in_array($host, self::normalizeAllowedHosts($allowedHosts, 'api.github.com'), true);
    }

    /**
     * Resolve a public HTTPS API host and reject private/reserved destinations.
     * Every resolved address is later pinned into cURL to prevent DNS rebinding.
     * @return list<string>
     */
    public static function resolvePublicBaseUrl(string $baseUrl, array $allowedHosts = ['api.github.com'], ?Closure $resolver = null): array
    {
        if ($baseUrl === '' || strlen($baseUrl) > 2048 || preg_match('/[\x00-\x20\x7f]/', $baseUrl)) {
            throw new RuntimeException('GITHUB_INVALID_BASE_URL');
        }
        $parts = parse_url($baseUrl);
        if (!is_array($parts)
            || strtolower((string)($parts['scheme'] ?? '')) !== 'https'
            || isset($parts['user']) || isset($parts['pass']) || isset($parts['query']) || isset($parts['fragment'])
            || (isset($parts['port']) && (int)$parts['port'] !== 443)) {
            throw new RuntimeException('GITHUB_INVALID_BASE_URL');
        }
        $host = strtolower(rtrim((string)($parts['host'] ?? ''), '.'));
        if ($host === '' || preg_match('/[^a-z0-9.:-]/', $host)) {
            throw new RuntimeException('GITHUB_INVALID_BASE_URL');
        }
        if (filter_var($host, FILTER_VALIDATE_IP) !== false || !in_array($host, self::normalizeAllowedHosts($allowedHosts), true)) {
            throw new RuntimeException('GITHUB_UNTRUSTED_HOST');
        }

        if ($resolver !== null) {
            $addresses = $resolver($host);
        } else {
            if (!function_exists('dns_get_record') || !defined('DNS_A') || !defined('DNS_AAAA')) {
                throw new RuntimeException('GITHUB_HOST_RESOLUTION_UNAVAILABLE');
            }
            $records = @dns_get_record($host, DNS_A | DNS_AAAA);
            $addresses = [];
            foreach (is_array($records) ? $records : [] as $record) {
                $ip = (string)($record['ip'] ?? $record['ipv6'] ?? '');
                if ($ip !== '') $addresses[] = $ip;
            }
            $addresses = array_values(array_unique($addresses));
        }
        if ($addresses === []) throw new RuntimeException('GITHUB_HOST_RESOLUTION_FAILED');
        foreach ($addresses as $ip) {
            if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
                throw new RuntimeException('GITHUB_PRIVATE_DESTINATION_BLOCKED');
            }
        }
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
            'Authorization: Bearer ' . $token,
            'Accept: application/vnd.github+json',
            'User-Agent: TropaTT-GitHub-Integration/1.0',
            'X-GitHub-Api-Version: 2022-11-28',
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
                    throw new RuntimeException('GITHUB_TRANSPORT_UNAVAILABLE');
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
                    CURLOPT_RESOLVE => array_map(
                        static fn(string $ip): string => $host . ':443:' . (str_contains($ip, ':') ? '[' . $ip . ']' : $ip),
                        $addresses,
                    ),
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
                    throw new RuntimeException('GITHUB_RESPONSE_TOO_LARGE');
                }
                $curlError = $result === false ? 'request failed' : '';
            }
            if ($this->httpTransport !== null && strlen($raw) > self::MAX_RESPONSE_BYTES) {
                throw new RuntimeException('GITHUB_RESPONSE_TOO_LARGE');
            }

            if ($raw === false || $raw === '') {
                $raw = '{}';
            }

            if ($httpCode === 401) {
                throw new RuntimeException('GITHUB_AUTH_FAILED: invalid token', 401);
            }
            if ($httpCode === 403 || $httpCode === 429) {
                if ($attempt < $this->maxRetries) {
                    sleep(5 * $attempt);
                    continue;
                }
                throw new RuntimeException('GITHUB_RATE_LIMITED', 429);
            }
            if ($httpCode === 404) {
                throw new RuntimeException('GITHUB_NOT_FOUND', 404);
            }
            if ($httpCode < 200 || $httpCode >= 300) {
                throw new RuntimeException('GITHUB_ERROR: HTTP ' . $httpCode, $httpCode);
            }
            if ($curlError !== '') {
                throw new RuntimeException('GITHUB_TRANSPORT_FAILED', 0);
            }

            $decoded = json_decode((string)$raw, true);
            return is_array($decoded) ? $decoded : [];
        }

        throw new RuntimeException('GITHUB_RATE_LIMITED: max retries reached', 429);
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
     * @return array{success: bool, message: string, login: string|null}
     */
    public function testConnection(string $token, string $baseUrl): array
    {
        try {
            $data = $this->request($token, $baseUrl, 'GET', '/user');
            return ['success' => true, 'message' => 'Connection successful', 'login' => (string)($data['login'] ?? '')];
        } catch (\Throwable $e) {
            error_log('[GitHubClient::testConnection] ' . $e->getMessage());
            return ['success' => false, 'message' => 'Connection test failed. Check the token.', 'login' => null];
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listRepos(string $token, string $baseUrl): array
    {
        return $this->listAll($token, $baseUrl, '/user/repos', ['affiliation' => 'owner,collaborator,organization_member', 'sort' => 'updated']);
    }

    /**
     * List issues + pull requests for a repository.
     *
     * @return array<int, array<string, mixed>>
     */
    public function listIssues(string $token, string $baseUrl, string $owner, string $repo): array
    {
        return $this->listAll($token, $baseUrl, '/repos/' . rawurlencode($owner) . '/' . rawurlencode($repo) . '/issues', ['state' => 'all']);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listIssueComments(string $token, string $baseUrl, string $owner, string $repo, int $number): array
    {
        return $this->listAll($token, $baseUrl, '/repos/' . rawurlencode($owner) . '/' . rawurlencode($repo) . '/issues/' . $number . '/comments');
    }

    /**
     * @return array<string, mixed>
     */
    public function createIssueComment(string $token, string $baseUrl, string $owner, string $repo, int $number, string $body): array
    {
        return $this->request($token, $baseUrl, 'POST', '/repos/' . rawurlencode($owner) . '/' . rawurlencode($repo) . '/issues/' . $number . '/comments', [], ['body' => $body]);
    }

    /**
     * Open or close an issue / pull request.
     *
     * @param string $state Either "open" or "closed".
     * @return array<string, mixed>
     */
    public function updateIssueState(string $token, string $baseUrl, string $owner, string $repo, int $number, string $state): array
    {
        return $this->request($token, $baseUrl, 'PATCH', '/repos/' . rawurlencode($owner) . '/' . rawurlencode($repo) . '/issues/' . $number, [], ['state' => $state]);
    }
}
