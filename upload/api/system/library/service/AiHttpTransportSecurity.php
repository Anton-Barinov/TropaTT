<?php
declare(strict_types=1);

namespace Api\System\Library\Service;

/** Network policy is supplied by server configuration, never provider_payload. */
final class AiHttpTransportSecurity
{
    public const MAX_RESPONSE_BYTES = 2097152;
    /** Injectable resolver permits offline DNS-rebinding regression tests. */
    public function __construct(private readonly ?\Closure $resolver = null) {}

    public function prepare(string $url, array $provider = []): array
    {
        $policy = (array)($provider['_transport_policy'] ?? []);
        $allowLocal = (bool)($policy['allow_local'] ?? false);
        $schemes = array_values(array_intersect(['https', 'http'], (array)($policy['allowed_schemes'] ?? ['https', 'http'])));
        $target = $this->resolveUrl($url, $allowLocal, $schemes);
        if (!$target['ok']) {
            return $target;
        }
        if ($target['scheme'] === 'http' && (!$allowLocal || !$this->isLocalTarget($target))) {
            return $this->failure('AI_PROVIDER_URL_SCHEME_NOT_ALLOWED');
        }
        // Literal IPs have no second DNS lookup and need no synthetic cache entry.
        $resolve = filter_var($target['host'], FILTER_VALIDATE_IP) === false ? [$this->resolveEntry($target)] : [];
        $connectTo = [];
        $payload = $provider['provider_payload'] ?? [];
        if (is_string($payload)) {
            $payload = json_decode($payload, true) ?: [];
        }
        $proxyUrl = is_array($payload) ? trim((string)($payload['proxy_url'] ?? '')) : '';
        $proxyOptions = [];
        if ($proxyUrl !== '') {
            $parsed = AiProxyUrl::parse($proxyUrl);
            if (!$parsed['ok']) {
                return $this->failure((string)($parsed['code'] ?? AiProxyUrl::CODE_INVALID));
            }
            // A proxy can resolve CONNECT/SOCKS destinations itself. Only a server
            // operator's exact endpoint allowlist may authorize that trust boundary.
            $trusted = (array)($policy['trusted_proxies'] ?? []);
            if (!in_array((string)$parsed['proxy'], $trusted, true)) {
                return $this->failure('AI_PROVIDER_PROXY_NOT_TRUSTED');
            }
            $proxy = $this->resolveUrl((string)$parsed['proxy'], $allowLocal, AiProxyUrl::ALLOWED_SCHEMES);
            if (!$proxy['ok']) {
                return $proxy;
            }
            if (filter_var($proxy['host'], FILTER_VALIDATE_IP) === false) {
                $resolve[] = $this->resolveEntry($proxy);
            }
            // Route to the vetted address while retaining the original hostname
            // for TLS SNI/certificate validation and HTTP Host.
            $connectTo[] = $this->formatHost($target['host']) . ':' . $target['port'] . ':' . $this->formatHost($target['ips'][0]) . ':' . $target['port'];
            $proxyOptions = AiProxyUrl::curlOptions($proxyUrl);
        }
        return ['ok' => true, 'resolve' => $resolve, 'connect_to' => $connectTo, 'proxy' => $proxyOptions, 'target_ips' => $target['ips']];
    }

    public static function apply(mixed $ch, array $prepared, ?string &$responseBody = null, ?bool &$tooLarge = null): bool
    {
        if (!defined('CURLOPT_RESOLVE') || !defined('CURLOPT_CONNECT_TO')) return false;
        $responseBody = '';
        $tooLarge = false;
        // Disable implicit environment proxies: their trust was never configured.
        $options = [CURLOPT_PROXY => '', CURLOPT_NOPROXY => '', CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_MAXREDIRS => 0, CURLOPT_SSL_VERIFYPEER => true, CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_RESOLVE => $prepared['resolve'], CURLOPT_CONNECT_TO => $prepared['connect_to'],
            CURLOPT_WRITEFUNCTION => static function (mixed $handle, string $chunk) use (&$responseBody, &$tooLarge): int {
                if (strlen($responseBody) + strlen($chunk) > self::MAX_RESPONSE_BYTES) {
                    $tooLarge = true;
                    return 0;
                }
                $responseBody .= $chunk;
                return strlen($chunk);
            }];
        if (defined('CURLOPT_PROTOCOLS')) {
            $options[CURLOPT_PROTOCOLS] = CURLPROTO_HTTP | CURLPROTO_HTTPS;
        }
        if (defined('CURLOPT_REDIR_PROTOCOLS')) {
            $options[CURLOPT_REDIR_PROTOCOLS] = CURLPROTO_HTTP | CURLPROTO_HTTPS;
        }
        if (!empty($prepared['proxy']['proxy'])) {
            $options[CURLOPT_PROXY] = $prepared['proxy']['proxy'];
            if (!empty($prepared['proxy']['userpwd'])) {
                $options[CURLOPT_PROXYUSERPWD] = $prepared['proxy']['userpwd'];
            }
            if (defined('CURLOPT_PROXY_SSL_VERIFYPEER')) {
                $options[CURLOPT_PROXY_SSL_VERIFYPEER] = true;
                $options[CURLOPT_PROXY_SSL_VERIFYHOST] = 2;
            }
        }
        return curl_setopt_array($ch, $options);
    }

    private function resolveUrl(string $url, bool $allowLocal, array $schemes): array
    {
        if (preg_match('/[\x00-\x20\x7f\\\\]/', $url) || filter_var($url, FILTER_VALIDATE_URL) === false) {
            return $this->failure('AI_PROVIDER_URL_INVALID');
        }
        $parts = parse_url($url);
        if (!is_array($parts) || isset($parts['user']) || isset($parts['pass']) || isset($parts['fragment'])) {
            return $this->failure('AI_PROVIDER_URL_INVALID');
        }
        $scheme = strtolower((string)($parts['scheme'] ?? ''));
        if (!in_array($scheme, $schemes, true)) {
            return $this->failure('AI_PROVIDER_URL_SCHEME_NOT_ALLOWED');
        }
        $host = strtolower(trim((string)($parts['host'] ?? ''), '[]'));
        $port = (int)($parts['port'] ?? (match ($scheme) { 'https' => 443, 'socks5', 'socks5h' => 1080, default => 80 }));
        if ($host === '' || $port < 1 || $port > 65535 || str_contains($host, '%')) {
            return $this->failure('AI_PROVIDER_URL_INVALID');
        }
        // cURL and libc can disagree about noncanonical numeric host forms.
        // Reject legacy decimal/octal/hex IPv4 notation before any DNS lookup.
        if (filter_var($host, FILTER_VALIDATE_IP) === false
            && preg_match('/^(?:0x[0-9a-f]+|[0-9]+)(?:\.(?:0x[0-9a-f]+|[0-9]+)){0,3}$/i', $host)) {
            return $this->failure('AI_PROVIDER_URL_INVALID');
        }
        if (!$allowLocal && ($host === 'localhost' || str_ends_with(rtrim($host, '.'), '.localhost') || str_ends_with(rtrim($host, '.'), '.local'))) {
            return $this->failure('AI_PROVIDER_URL_LOCALHOST_FORBIDDEN');
        }
        $ips = filter_var($host, FILTER_VALIDATE_IP) !== false ? [$host] : $this->resolve($host);
        if ($ips === []) {
            return $this->failure('AI_PROVIDER_URL_UNRESOLVABLE');
        }
        foreach ($ips as $ip) {
            if (filter_var($ip, FILTER_VALIDATE_IP) === false || (!$allowLocal && !self::isPublicIp($ip))) {
                return $this->failure('AI_PROVIDER_URL_PRIVATE_IP_FORBIDDEN');
            }
        }
        return ['ok' => true, 'scheme' => $scheme, 'host' => $host, 'port' => $port, 'ips' => array_values(array_unique($ips))];
    }

    private function resolve(string $host): array
    {
        if ($this->resolver !== null) {
            return array_values((array)($this->resolver)($host));
        }
        $ips = [];
        if (function_exists('dns_get_record')) {
            $records = @dns_get_record($host, DNS_A | DNS_AAAA);
            foreach (is_array($records) ? $records : [] as $record) {
                if (isset($record['ip'])) $ips[] = $record['ip'];
                if (isset($record['ipv6'])) $ips[] = $record['ipv6'];
            }
        }
        if (function_exists('gethostbynamel')) {
            $fallback = @gethostbynamel($host);
            if (is_array($fallback)) $ips = array_merge($ips, $fallback);
        }
        return array_values(array_unique($ips));
    }

    public static function isPublicIp(string $ip): bool
    {
        $packed = @inet_pton($ip);
        if ($packed === false) return false;
        if (strlen($packed) === 4) {
            $n = unpack('N', $packed)[1];
            foreach (['0.0.0.0/8', '10.0.0.0/8', '100.64.0.0/10', '127.0.0.0/8', '169.254.0.0/16', '172.16.0.0/12', '192.0.0.0/24', '192.0.2.0/24', '192.88.99.0/24', '192.168.0.0/16', '198.18.0.0/15', '198.51.100.0/24', '203.0.113.0/24', '224.0.0.0/3'] as $cidr) {
                [$network, $bits] = explode('/', $cidr);
                $mask = (0xffffffff << (32 - (int)$bits)) & 0xffffffff;
                if (($n & $mask) === (unpack('N', inet_pton($network))[1] & $mask)) return false;
            }
            return true;
        }
        // Only global unicast. Exclude transition/tunnel prefixes that can hide
        // IPv4 private targets, independent of textual IPv6 spelling.
        if ((ord($packed[0]) & 0xe0) !== 0x20) return false;
        if (substr($packed, 0, 4) === "\x20\x01\x0d\xb8") return false;
        if (substr($packed, 0, 2) === "\x20\x02" || (substr($packed, 0, 2) === "\x20\x01" && ord($packed[2]) < 2)) return false;
        if (substr($packed, 0, 2) === "\x3f\xff" && (ord($packed[2]) & 0xf0) === 0) return false;
        return true;
    }

    private function isLocalTarget(array $target): bool
    {
        foreach ($target['ips'] as $ip) {
            if (self::isPublicIp($ip)) return false;
        }
        return true;
    }

    private function resolveEntry(array $target): string
    {
        return $this->formatHost($target['host']) . ':' . $target['port'] . ':' . $this->formatHost($target['ips'][0]);
    }
    private function formatHost(string $host): string { return str_contains($host, ':') ? '[' . $host . ']' : $host; }
    private function failure(string $code): array { return ['ok' => false, 'http_status' => 0, 'error_code' => $code, 'error_message' => 'Provider network policy rejected the request']; }
}
