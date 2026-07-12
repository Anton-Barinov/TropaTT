<?php
declare(strict_types=1);

namespace Api\System\Library\Module;

final class ModuleNetworkSandbox
{
    /** @var array<int, string> */
    private array $allowedHosts = [];

    /** @var array<int, string> */
    private array $allowedSchemes = ['https'];

    /** @var array<int, string> */
    private array $forbiddenHosts = [
        '127.0.0.1', 'localhost', '::1', '0.0.0.0',
        '169.254.', '10.', '172.16.', '172.17.', '172.18.',
        '172.19.', '172.20.', '172.21.', '172.22.', '172.23.',
        '172.24.', '172.25.', '172.26.', '172.27.', '172.28.',
        '172.29.', '172.30.', '172.31.', '192.168.',
    ];

    /** @var array<string, array{count: int, reset: int}> */
    private array $requestCounts = [];

    private int $maxRequestsPerMinute = 60;
    private int $requestTimeout = 30;

    public function __construct(
        private readonly string $moduleName,
        private readonly ModuleAuditLogger $auditLogger,
    ) {}

    public function allowHost(string $host): void
    {
        if (!in_array($host, $this->allowedHosts, true)) {
            $this->allowedHosts[] = $host;
        }
    }

    public function isAllowedUrl(string $url): bool
    {
        $parsed = parse_url($url);
        if ($parsed === false) {
            return false;
        }

        $scheme = $parsed['scheme'] ?? 'https';
        if (!in_array($scheme, $this->allowedSchemes, true)) {
            $this->auditLogger->log($this->moduleName, 'network', 'blocked_scheme', ['url' => $url, 'scheme' => $scheme]);
            return false;
        }

        $host = $parsed['host'] ?? '';
        if ($host === '') {
            return false;
        }

        foreach ($this->forbiddenHosts as $blocked) {
            if (str_starts_with($host, $blocked)) {
                $this->auditLogger->log($this->moduleName, 'network', 'blocked_host', ['url' => $url, 'host' => $host]);
                return false;
            }
        }

        if ($this->allowedHosts !== [] && !in_array($host, $this->allowedHosts, true)) {
            $this->auditLogger->log($this->moduleName, 'network', 'host_not_allowed', ['url' => $url, 'host' => $host]);
            return false;
        }

        return $this->checkRateLimit($host);
    }

    public function executeRequest(string $url, string $method = 'GET', array $headers = [], ?string $body = null): array
    {
        if (!$this->isAllowedUrl($url)) {
            return ['error' => 'Network access denied by sandbox', 'status' => 403];
        }

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $this->requestTimeout,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => $headers,
        ]);

        if ($body !== null && in_array($method, ['POST', 'PUT', 'PATCH'], true)) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        }

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($response === false || $curlError !== '') {
            $this->auditLogger->log($this->moduleName, 'network', 'request_failed', ['url' => $url, 'error' => $curlError]);
            return ['error' => $curlError ?: 'Request failed', 'status' => 0];
        }

        $this->auditLogger->log($this->moduleName, 'network', 'request_completed', ['url' => $url, 'status' => $httpCode]);
        return ['body' => $response, 'status' => $httpCode];
    }

    private function checkRateLimit(string $host): bool
    {
        $now = time();
        $bucket = $this->requestCounts[$host] ?? ['count' => 0, 'reset' => $now + 60];

        if ($now >= $bucket['reset']) {
            $bucket = ['count' => 0, 'reset' => $now + 60];
        }

        if ($bucket['count'] >= $this->maxRequestsPerMinute) {
            return false;
        }

        $bucket['count']++;
        $this->requestCounts[$host] = $bucket;
        return true;
    }

    public function getAllowedHosts(): array
    {
        return $this->allowedHosts;
    }

    public function getUsageReport(): array
    {
        $report = [];
        foreach ($this->requestCounts as $host => $bucket) {
            $report[$host] = [
                'count' => $bucket['count'],
                'reset_in' => $bucket['reset'] - time(),
            ];
        }
        return $report;
    }
}
