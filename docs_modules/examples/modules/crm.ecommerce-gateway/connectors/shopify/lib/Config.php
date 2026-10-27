<?php

namespace Tropatt\Shopify;

/**
 * App configuration (`config.php`, created from `config.example.php`).
 */
class Config
{
    /** @var array */
    private static $values = array();

    /**
     * @return void
     */
    public static function load($file = null)
    {
        $file = $file === null ? dirname(__DIR__) . '/config.php' : $file;

        if (is_file($file)) {
            $values = require $file;
            if (is_array($values)) {
                self::$values = $values;
            }
        }
    }

    /**
     * @return void
     */
    public static function set(array $values)
    {
        self::$values = $values;
    }

    /**
     * @return mixed
     */
    public static function get($key, $default = null)
    {
        return array_key_exists($key, self::$values) ? self::$values[$key] : $default;
    }

    /** @return string */
    public static function gatewayUrl()
    {
        return rtrim(trim((string)self::get('gateway_url', '')), '/');
    }

    /** @return string */
    public static function storeKey()
    {
        return trim((string)self::get('store_key', ''));
    }

    /** @return string */
    public static function storeSecret()
    {
        return trim((string)self::get('store_secret', ''));
    }

    /** @return string Shopify app API key (client id). */
    public static function apiKey()
    {
        return trim((string)self::get('shopify_api_key', ''));
    }

    /** @return string Shopify app API secret (also signs the webhooks). */
    public static function apiSecret()
    {
        return trim((string)self::get('shopify_api_secret', ''));
    }

    /** @return array Scopes requested during install. */
    public static function scopes()
    {
        $scopes = self::get('shopify_scopes', 'read_orders,write_orders,read_products,write_inventory,read_locations');

        return is_array($scopes) ? $scopes : array_filter(array_map('trim', explode(',', (string)$scopes)));
    }

    /**
     * Default Admin API version.
     *
     * Shopify releases a version every quarter and stops serving one 12 months
     * after release; a request for an unserved version is answered with the oldest
     * supported one instead. That fallback is invisible in the body, so
     * ShopifyClient::warnOnVersionFallback() logs the served version from the
     * `X-Shopify-API-Version` response header. Bump this together with the
     * deprecated mutations in GraphQL\Queries.
     */
    const DEFAULT_API_VERSION = '2026-07';

    /** @return string */
    public static function apiVersion()
    {
        $version = trim((string)self::get('shopify_api_version', ''));

        // A malformed value would silently build a URL for a version that does
        // not exist, so fall back to the default instead.
        if (!preg_match('/^\d{4}-\d{2}$/', $version)) {
            return self::DEFAULT_API_VERSION;
        }

        return $version;
    }

    /** @return string */
    public static function stockLocationId()
    {
        return trim((string)self::get('shopify_location_id', ''));
    }

    /** @return string */
    public static function stockSource()
    {
        return trim((string)self::get('stock_source', ''));
    }

    /** @return string */
    public static function defaultStage()
    {
        $stage = trim((string)self::get('default_stage', ''));

        return $stage === '' ? 'new' : $stage;
    }

    /** @return string */
    public static function tokenFile()
    {
        return (string)self::get('token_file', dirname(__DIR__) . '/var/shopify-token.json');
    }

    /** @return bool */
    public static function debugEnabled()
    {
        return (bool)self::get('debug', false);
    }

    /** @return string */
    public static function queueDir()
    {
        return (string)self::get('queue_dir', dirname(__DIR__) . '/var/queue');
    }

    /** @return string */
    public static function logFile()
    {
        return (string)self::get('log_file', dirname(__DIR__) . '/var/connector.log');
    }

    /** @return string */
    public static function redirectUri()
    {
        $uri = trim((string)self::get('shopify_redirect_uri', ''));

        if ($uri !== '') {
            return $uri;
        }

        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = isset($_SERVER['HTTP_HOST']) ? (string)$_SERVER['HTTP_HOST'] : 'localhost';

        return $scheme . '://' . $host . '/shopify/callback.php';
    }

    /** @return bool */
    public static function isConfigured()
    {
        return self::gatewayUrl() !== '' && self::storeKey() !== '' && self::storeSecret() !== '';
    }
}
