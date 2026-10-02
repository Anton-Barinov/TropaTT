<?php
declare(strict_types=1);

namespace Api\System\Library\Connector;

/**
 * Diagnostics, redaction, SSRF prevention, and health monitoring for module connectors.
 */
final class ConnectorDiagnostics implements ConnectorDiagnosticsInterface
{
    private const SENSITIVE_KEY_PATTERNS = [
        '/token/i',
        '/secret/i',
        '/password/i',
        '/passwd/i',
        '/api[_-]?key/i',
        '/auth/i',
        '/authorization/i',
        '/signature/i',
        '/sig/i',
        '/private[_-]?key/i',
        '/access[_-]?token/i',
        '/refresh[_-]?token/i',
        '/client[_-]?secret/i',
        '/credit[_-]?card/i',
        '/cvv/i',
    ];

    /**
     * Recursively redact sensitive fields from diagnostic logs or responses.
     */
    public function sanitizePayload(mixed $data): mixed
    {
        if (is_array($data)) {
            $sanitized = [];
            foreach ($data as $key => $value) {
                if (is_string($key) && $this->isSensitiveKey($key)) {
                    $sanitized[$key] = '[REDACTED]';
                } else {
                    $sanitized[$key] = $this->sanitizePayload($value);
                }
            }
            return $sanitized;
        }

        if (is_object($data)) {
            return $this->sanitizePayload((array)$data);
        }

        return $data;
    }

    /**
     * Format a standardized health / connection-test result payload.
     *
     * @param string $status 'healthy' | 'warning' | 'error' | 'disconnected'
     * @param array<string, array{status: string, message: string, latency_ms?: float}> $checks
     * @param string|null $correlationId
     * @return array<string, mixed>
     */
    public function formatHealthReport(string $status, array $checks, ?string $correlationId = null): array
    {
        if (!in_array($status, ['healthy', 'warning', 'error', 'disconnected'], true)) {
            $status = 'error';
        }

        return [
            'status' => $status,
            'timestamp' => gmdate('Y-m-d\TH:i:s\Z'),
            'correlation_id' => $correlationId ?? bin2hex(random_bytes(16)),
            'checks' => $this->sanitizePayload($checks),
        ];
    }

    /**
     * Strict SSRF guard for external connector endpoints.
     * Rejects private IP ranges, loopback, link-local, file/gopher/etc schemes.
     */
    public function validateOutboundUrl(string $url): bool
    {
        $parts = parse_url($url);
        if (!$parts || empty($parts['scheme']) || empty($parts['host'])) {
            return false;
        }

        $scheme = strtolower((string)$parts['scheme']);
        if (!in_array($scheme, ['https', 'http'], true)) {
            return false;
        }

        $host = strtolower((string)$parts['host']);

        // Reject localhost and mDNS
        if ($host === 'localhost' || str_ends_with($host, '.local') || str_ends_with($host, '.internal')) {
            return false;
        }

        // Check resolved IP address
        $ip = filter_var($host, FILTER_VALIDATE_IP) ? $host : gethostbyname($host);
        if (!filter_var($ip, FILTER_VALIDATE_IP)) {
            return false;
        }

        // Disallow private / reserved IP addresses
        if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
            return false;
        }

        return true;
    }

    /**
     * Bounded cURL options suitable for shared hosting.
     *
     * @return array<int, mixed>
     */
    public function getSafeHttpOptions(int $timeoutSeconds = 15): array
    {
        $timeout = max(1, min(60, $timeoutSeconds));
        $connectTimeout = min(5, (int)ceil($timeout / 3));

        return [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 3,
            CURLOPT_CONNECTTIMEOUT => $connectTimeout,
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_USERAGENT => 'TropaTT-Connector/1.0 (Shared-Hosting; Bounded)',
        ];
    }

    private function isSensitiveKey(string $key): bool
    {
        foreach (self::SENSITIVE_KEY_PATTERNS as $pattern) {
            if (preg_match($pattern, $key)) {
                return true;
            }
        }

        return false;
    }
}
