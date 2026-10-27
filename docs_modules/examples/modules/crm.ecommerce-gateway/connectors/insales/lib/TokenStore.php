<?php

namespace Tropatt\InSales;

/**
 * Access/refresh token storage (JSON file outside the web root is recommended).
 */
class TokenStore
{
    /**
     * @return array
     */
    public static function load($file = null)
    {
        $file = $file === null ? Config::tokenFile() : $file;

        if (!is_file($file)) {
            return array();
        }

        $data = json_decode((string)@file_get_contents($file), true);

        return is_array($data) ? $data : array();
    }

    /**
     * @return bool
     */
    public static function save(array $token, $file = null)
    {
        $file = $file === null ? Config::tokenFile() : $file;
        $dir = dirname($file);

        if (!is_dir($dir) && !@mkdir($dir, 0770, true) && !is_dir($dir)) {
            return false;
        }

        $json = json_encode($token, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return @file_put_contents($file, (string)$json, LOCK_EX) !== false;
    }

    /**
     * @return string
     */
    public static function accessToken($file = null)
    {
        $token = self::load($file);

        return isset($token['access_token']) ? (string)$token['access_token'] : '';
    }

    /**
     * @return bool
     */
    public static function isExpired(array $token, $now = null)
    {
        if (empty($token['expires_at'])) {
            return false;
        }

        $now = $now === null ? time() : (int)$now;

        // Refresh 60 seconds before the actual expiry.
        return (int)$token['expires_at'] - 60 <= $now;
    }
}
