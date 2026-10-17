<?php
declare(strict_types=1);

namespace Module\Crm\StorageConnectors\Service;

use Api\System\Library\Security\UrlSafetyValidator;
use RuntimeException;

final class SafeEndpoint
{
    /** @return array<int, mixed> */
    public static function curlOptions(string $url, int $timeoutSeconds): array
    {
        if (!extension_loaded('curl') || !defined('CURLOPT_RESOLVE') || !defined('CURLOPT_PROTOCOLS')) {
            throw new RuntimeException('Secure HTTPS storage transport is unavailable on this host.');
        }

        $parts = parse_url($url);
        if (!is_array($parts) || isset($parts['user']) || isset($parts['pass'])) {
            throw new RuntimeException('Storage request URL is invalid.');
        }

        $validation = (new UrlSafetyValidator())->validateProviderUrl($url, true, ['https']);
        $ips = $validation['resolved_ips'] ?? [];
        if (($validation['ok'] ?? false) !== true || $ips === []) {
            throw new RuntimeException('Storage request URL is blocked by the outbound network safety policy.');
        }

        $host = trim((string)($parts['host'] ?? ''), '[]');
        $port = (int)($parts['port'] ?? 443);
        if ($host === '' || $port !== 443) {
            throw new RuntimeException('Storage requests must use HTTPS on port 443.');
        }

        $ip = trim((string)$ips[0], '[]');
        $resolveAddress = filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) !== false ? '[' . $ip . ']' : $ip;

        return [
            CURLOPT_RESOLVE => [$host . ':443:' . $resolveAddress],
            CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
            CURLOPT_REDIR_PROTOCOLS => CURLPROTO_HTTPS,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_MAXREDIRS => 0,
            CURLOPT_PROXY => '',
            CURLOPT_NOPROXY => '*',
            CURLOPT_CONNECTTIMEOUT => max(1, min(5, (int)ceil($timeoutSeconds / 3))),
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ];
    }
}
