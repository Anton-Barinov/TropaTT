<?php

namespace Tropatt\Shopify;

/**
 * Access token storage (one shop per connector instance, JSON file).
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

        return @file_put_contents($file, (string)json_encode($token, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), LOCK_EX) !== false;
    }

    /**
     * @return bool
     */
    public static function remove($file = null)
    {
        $file = $file === null ? Config::tokenFile() : $file;

        return is_file($file) ? @unlink($file) : true;
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
     * Unix time the access token stops working, or null for a non-expiring one.
     *
     * The absolute expiry is derived from `expires_in` of the response instead
     * of trusting a hard-coded lifetime: Shopify can change it.
     *
     * @return int|null
     */
    public static function expiresAt(array $token)
    {
        $expiresIn = isset($token['expires_in']) ? (int)$token['expires_in'] : 0;
        if ($expiresIn <= 0) {
            return null;
        }

        $obtainedAt = isset($token['obtained_at']) ? (int)$token['obtained_at'] : null;
        if ($obtainedAt === null) {
            return null;
        }

        return $obtainedAt + $expiresIn;
    }

    /**
     * True when the stored token is expiring and needs a refresh now.
     *
     * @return bool
     */
    public static function needsRefresh(array $token, $now = null, $skew = 300)
    {
        $expiresAt = self::expiresAt($token);
        if ($expiresAt === null) {
            return false;
        }

        $now = $now === null ? time() : (int)$now;

        return $expiresAt - (int)$skew <= $now;
    }

    /**
     * @return bool
     */
    public static function hasRefreshToken(array $token)
    {
        return !empty($token['refresh_token']);
    }
}
