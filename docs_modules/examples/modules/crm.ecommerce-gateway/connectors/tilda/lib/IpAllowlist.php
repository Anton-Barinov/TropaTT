<?php

namespace Tropatt\Tilda;

/**
 * CIDR allowlist for the webhook endpoint.
 *
 * Tilda posts the cart webhook from its own infrastructure, so the endpoint can
 * be limited to those ranges (see the README: the current list is available from
 * Tilda support and is configured, not hardcoded).
 */
class IpAllowlist
{
    /**
     * @return bool
     */
    public static function matches(array $ranges, $ip)
    {
        $ip = trim((string)$ip);
        if ($ip === '') {
            return false;
        }

        if ($ranges === array()) {
            // An empty list disables the check (the secret token still applies).
            return true;
        }

        foreach ($ranges as $range) {
            if (self::inRange($ip, (string)$range)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return bool
     */
    public static function inRange($ip, $range)
    {
        $range = trim($range);
        if ($range === '') {
            return false;
        }

        if (strpos($range, '/') === false) {
            return $ip === $range;
        }

        list($subnet, $bits) = explode('/', $range, 2);
        $bits = (int)$bits;

        $ipLong = ip2long($ip);
        $subnetLong = ip2long($subnet);

        if ($ipLong === false || $subnetLong === false) {
            return false;
        }

        if ($bits < 0 || $bits > 32) {
            return false;
        }

        $mask = $bits === 0 ? 0 : (-1 << (32 - $bits));

        return ($ipLong & $mask) === ($subnetLong & $mask);
    }

    /**
     * Client IP, honouring a reverse proxy header when configured.
     *
     * @return string
     */
    public static function clientIp(array $server)
    {
        foreach (array('HTTP_CF_CONNECTING_IP', 'HTTP_X_REAL_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR') as $key) {
            if (empty($server[$key])) {
                continue;
            }

            $value = (string)$server[$key];
            if ($key === 'HTTP_X_FORWARDED_FOR') {
                $parts = explode(',', $value);
                $value = trim($parts[0]);
            }

            if (filter_var($value, FILTER_VALIDATE_IP)) {
                return $value;
            }
        }

        return '';
    }
}
