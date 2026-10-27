<?php
declare(strict_types=1);

namespace Module\Crm\EcommerceGateway\Service;

use Api\System\Library\Security\UrlSafetyValidator;
use Closure;

/**
 * Direct, DNS-pinned HTTP transport for user-configured webhook destinations.
 * Its shared-hosting fallback uses PHP HTTP streams with the validated IP literal
 * while retaining the original hostname for Host and TLS certificate checks.
 */
final class SafeWebhookHttpClient
{
    /** @var Closure|null */
    private ?Closure $curlExecutor;

    /**
     * Test seam accepts already validated target arguments; production always
     * uses the built-in cURL executor and URL safety validator.
     */
    public function __construct(?Closure $curlExecutor = null)
    {
        $this->curlExecutor = $curlExecutor;
    }

    /**
     * @param array<string,string> $headers
     * @return array{status:int,body:string,error:?string}
     */
    public function request(
        string $url,
        string $method,
        array $headers,
        string $body,
        int $timeoutSeconds,
        int $maxResponseBytes = 65536
    ): array {
        $parts = parse_url(trim($url));
        if (!is_array($parts)
            || isset($parts['user'])
            || isset($parts['pass'])
            || isset($parts['fragment'])
        ) {
            return self::failure('Destination URL is invalid');
        }

        $scheme = strtolower((string)($parts['scheme'] ?? ''));
        $host = strtolower(trim((string)($parts['host'] ?? ''), '[]'));
        $port = (int)($parts['port'] ?? ($scheme === 'https' ? 443 : 80));
        if ($host === '' || !in_array($scheme, ['http', 'https'], true) || $port < 1 || $port > 65535) {
            return self::failure('Destination URL is invalid');
        }

        $check = (new UrlSafetyValidator())->validateProviderUrl($url, true, ['https', 'http']);
        $ips = array_values(array_filter(array_map('strval', (array)($check['resolved_ips'] ?? []))));
        if (empty($check['ok']) || $ips === []) {
            return self::failure('Destination host is not publicly reachable');
        }

        $ip = trim($ips[0], '[]');
        if (filter_var($ip, FILTER_VALIDATE_IP) === false) {
            return self::failure('Destination host could not be safely resolved');
        }

        $method = strtoupper(trim($method));
        if (!in_array($method, ['GET', 'HEAD', 'POST', 'PUT', 'PATCH', 'DELETE'], true)) {
            return self::failure('HTTP method is not allowed');
        }
        foreach ($headers as $name => $value) {
            if (preg_match('/^[!#$%&\'*+.^_`|~0-9A-Za-z-]+$/', (string)$name) !== 1
                || in_array(strtolower((string)$name), ['host', 'content-length', 'transfer-encoding', 'proxy-authorization', 'connection'], true)
                || str_contains((string)$value, "\r")
                || str_contains((string)$value, "\n")
            ) {
                return self::failure('HTTP headers are invalid');
            }
        }
        $timeoutSeconds = max(1, min(30, $timeoutSeconds));
        $maxResponseBytes = max(0, min(1048576, $maxResponseBytes));

        if ($this->curlExecutor !== null) {
            return ($this->curlExecutor)($url, $method, $headers, $body, $timeoutSeconds, $host, $port, $ip, $maxResponseBytes);
        }
        if (!function_exists('curl_init')) {
            return $this->executeStream($url, $method, $headers, $body, $timeoutSeconds, $parts, $host, $port, $ip, $maxResponseBytes);
        }

        return $this->executeCurl($url, $method, $headers, $body, $timeoutSeconds, $host, $port, $ip, $maxResponseBytes);
    }

    /**
     * @param array<string,string> $headers
     * @return array{status:int,body:string,error:?string}
     */
    private function executeCurl(
        string $url,
        string $method,
        array $headers,
        string $body,
        int $timeoutSeconds,
        string $host,
        int $port,
        string $ip,
        int $maxResponseBytes
    ): array {
        $handle = curl_init($url);
        if ($handle === false) {
            return self::failure('Could not initialize safe HTTP transport');
        }

        $formattedHeaders = [];
        foreach ($headers as $name => $value) {
            if (preg_match('/^[!#$%&\'*+.^_`|~0-9A-Za-z-]+$/', (string)$name) !== 1
                || in_array(strtolower((string)$name), ['host', 'content-length', 'transfer-encoding', 'proxy-authorization', 'connection'], true)
                || str_contains((string)$value, "\r")
                || str_contains((string)$value, "\n")
            ) {
                curl_close($handle);
                return self::failure('HTTP headers are invalid');
            }
            $formattedHeaders[] = $name . ': ' . $value;
        }

        $resolveIp = str_contains($ip, ':') ? '[' . $ip . ']' : $ip;
        $responseBody = '';
        $tooLarge = false;
        $options = [
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => $formattedHeaders,
            CURLOPT_RETURNTRANSFER => false,
            CURLOPT_HEADER => false,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_MAXREDIRS => 0,
            CURLOPT_CONNECTTIMEOUT => $timeoutSeconds,
            CURLOPT_TIMEOUT => $timeoutSeconds,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
            CURLOPT_REDIR_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
            CURLOPT_PROXY => '',
            CURLOPT_WRITEFUNCTION => static function ($curl, string $chunk) use (&$responseBody, &$tooLarge, $maxResponseBytes): int {
                if (strlen($responseBody) + strlen($chunk) > $maxResponseBytes) {
                    $tooLarge = true;
                    return 0;
                }
                $responseBody .= $chunk;
                return strlen($chunk);
            },
        ];
        if (in_array($method, ['POST', 'PUT', 'PATCH'], true)) {
            $options[CURLOPT_POSTFIELDS] = $body;
        }
        if (filter_var($host, FILTER_VALIDATE_IP) === false) {
            $options[CURLOPT_RESOLVE] = [$host . ':' . $port . ':' . $resolveIp];
        }
        curl_setopt_array($handle, $options);

        $ok = curl_exec($handle);
        $status = (int)curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
        curl_close($handle);

        if ($tooLarge) {
            return self::failure('Destination response exceeded the allowed size', $status);
        }
        if ($ok === false) {
            return self::failure('Safe HTTP request failed', $status);
        }

        return ['status' => $status, 'body' => $responseBody, 'error' => null];
    }

    /**
     * Shared-hosting fallback that connects to the checked IP directly and
     * supplies the original hostname for TLS SNI/certificate verification.
     * Redirects are never followed by this transport.
     *
     * @param array<string,mixed> $parts
     * @param array<string,string> $headers
     * @return array{status:int,body:string,error:?string}
     */
    private function executeStream(
        string $url,
        string $method,
        array $headers,
        string $body,
        int $timeoutSeconds,
        array $parts,
        string $host,
        int $port,
        string $ip,
        int $maxResponseBytes
    ): array {
        if (!function_exists('file_get_contents') || ini_get('allow_url_fopen') !== '1') {
            return self::failure('Safe direct transport is unavailable on this host');
        }

        $scheme = strtolower((string)($parts['scheme'] ?? ''));
        $address = str_contains($ip, ':') ? '[' . $ip . ']' : $ip;
        $path = (string)($parts['path'] ?? '/');
        if ($path === '') {
            $path = '/';
        }
        if (isset($parts['query']) && $parts['query'] !== '') {
            $path .= '?' . (string)$parts['query'];
        }
        // Use only the already validated IP in the URL, so the wrapper cannot
        // perform a second, attacker-influenced DNS resolution.
        $pinnedUrl = $scheme . '://' . $address . ':' . $port . $path;
        $hostHeader = str_contains($host, ':') ? '[' . $host . ']' : $host;
        if (($scheme === 'https' && $port !== 443) || ($scheme === 'http' && $port !== 80)) {
            $hostHeader .= ':' . $port;
        }

        $requestHeaders = ['Host: ' . $hostHeader, 'Connection: close', 'Accept-Encoding: identity'];
        foreach ($headers as $name => $value) {
            if (preg_match('/^[!#$%&\'*+.^_`|~0-9A-Za-z-]+$/', (string)$name) !== 1
                || in_array(strtolower((string)$name), ['host', 'content-length', 'transfer-encoding', 'proxy-authorization', 'connection'], true)
                || str_contains((string)$value, "\r")
                || str_contains((string)$value, "\n")
            ) {
                return self::failure('HTTP headers are invalid');
            }
            $requestHeaders[] = $name . ': ' . $value;
        }
        if (in_array($method, ['POST', 'PUT', 'PATCH'], true)) {
            $requestHeaders[] = 'Content-Length: ' . strlen($body);
        }

        $httpOptions = [
            'method' => $method,
            'header' => implode("\r\n", $requestHeaders),
            'timeout' => $timeoutSeconds,
            'ignore_errors' => true,
            'follow_location' => 0,
            'max_redirects' => 0,
            'protocol_version' => 1.1,
        ];
        if (in_array($method, ['POST', 'PUT', 'PATCH'], true)) {
            $httpOptions['content'] = $body;
        }
        $contextOptions = ['http' => $httpOptions];
        if ($scheme === 'https') {
            $contextOptions['ssl'] = [
                'verify_peer' => true,
                'verify_peer_name' => true,
                'allow_self_signed' => false,
                'peer_name' => $host,
                'SNI_enabled' => true,
            ];
        }

        $context = stream_context_create($contextOptions);
        $responseBody = @file_get_contents($pinnedUrl, false, $context, 0, $maxResponseBytes + 1);
        $responseHeaders = $http_response_header ?? [];
        $status = 0;
        foreach ($responseHeaders as $responseHeader) {
            if (preg_match('/^HTTP\/\d(?:\.\d)?\s+(\d{3})\b/', (string)$responseHeader, $matches) === 1) {
                $status = (int)$matches[1];
            }
        }
        if ($responseBody === false || $status < 100 || $status > 599) {
            return self::failure('Safe HTTP request failed', $status);
        }
        if (strlen($responseBody) > $maxResponseBytes) {
            return self::failure('Destination response exceeded the allowed size', $status);
        }

        return ['status' => $status, 'body' => $responseBody, 'error' => null];
    }

    /** @return array{status:int,body:string,error:?string} */
    private static function failure(string $message, int $status = 0): array
    {
        return ['status' => $status, 'body' => '', 'error' => $message];
    }
}
