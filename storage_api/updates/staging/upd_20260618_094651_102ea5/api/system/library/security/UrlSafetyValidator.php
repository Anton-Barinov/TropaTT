<?php
declare(strict_types=1);

namespace Api\System\Library\Security;

final class UrlSafetyValidator
{
    /**
     * @return array{ok:bool,code:string}
     */
    public function validateProviderUrl(string $url, bool $strictNetworkPolicy, array $allowedSchemes = ['https', 'http']): array
    {
        $value = trim($url);
        if ($value === '') {
            return ['ok' => false, 'code' => 'AI_PROVIDER_URL_REQUIRED'];
        }

        if (filter_var($value, FILTER_VALIDATE_URL) === false) {
            return ['ok' => false, 'code' => 'AI_PROVIDER_URL_INVALID'];
        }

        $parts = parse_url($value);
        if (!is_array($parts)) {
            return ['ok' => false, 'code' => 'AI_PROVIDER_URL_INVALID'];
        }

        $scheme = strtolower((string)($parts['scheme'] ?? ''));
        if ($scheme === '' || !in_array($scheme, $allowedSchemes, true)) {
            return ['ok' => false, 'code' => 'AI_PROVIDER_URL_SCHEME_NOT_ALLOWED'];
        }

        $host = strtolower(trim((string)($parts['host'] ?? '')));
        if ($host === '') {
            return ['ok' => false, 'code' => 'AI_PROVIDER_URL_INVALID'];
        }

        if (!$strictNetworkPolicy) {
            return ['ok' => true, 'code' => 'OK'];
        }

        if ($this->isLocalHostname($host)) {
            return ['ok' => false, 'code' => 'AI_PROVIDER_URL_LOCALHOST_FORBIDDEN'];
        }

        if (filter_var($host, FILTER_VALIDATE_IP) !== false) {
            if ($this->isPrivateOrReservedIp($host)) {
                return ['ok' => false, 'code' => 'AI_PROVIDER_URL_PRIVATE_IP_FORBIDDEN'];
            }
            return ['ok' => true, 'code' => 'OK'];
        }

        $ips = $this->resolveHostIps($host);
        foreach ($ips as $ip) {
            if ($this->isPrivateOrReservedIp($ip)) {
                return ['ok' => false, 'code' => 'AI_PROVIDER_URL_PRIVATE_IP_FORBIDDEN'];
            }
        }

        return ['ok' => true, 'code' => 'OK'];
    }

    private function isLocalHostname(string $host): bool
    {
        if ($host === 'localhost') {
            return true;
        }

        return str_ends_with($host, '.localhost')
            || str_ends_with($host, '.local')
            || str_ends_with($host, '.localdomain');
    }

    private function isPrivateOrReservedIp(string $ip): bool
    {
        return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false;
    }

    /** @return list<string> */
    private function resolveHostIps(string $host): array
    {
        $ips = [];
        $a = @dns_get_record($host, DNS_A);
        if (is_array($a)) {
            foreach ($a as $row) {
                $ip = trim((string)($row['ip'] ?? ''));
                if ($ip !== '') {
                    $ips[] = $ip;
                }
            }
        }

        $aaaa = @dns_get_record($host, DNS_AAAA);
        if (is_array($aaaa)) {
            foreach ($aaaa as $row) {
                $ip = trim((string)($row['ipv6'] ?? ''));
                if ($ip !== '') {
                    $ips[] = $ip;
                }
            }
        }

        $fallback = @gethostbynamel($host);
        if (is_array($fallback)) {
            foreach ($fallback as $ip) {
                $candidate = trim((string)$ip);
                if ($candidate !== '') {
                    $ips[] = $candidate;
                }
            }
        }

        return array_values(array_unique($ips));
    }
}
