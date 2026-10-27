<?php

namespace Tropatt\InSales;

/**
 * Connector configuration (`config.php`, created from `config.example.php`).
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

    /**
     * @return string
     */
    public static function gatewayUrl()
    {
        return rtrim(trim((string)self::get('gateway_url', '')), '/');
    }

    /**
     * @return string
     */
    public static function storeKey()
    {
        return trim((string)self::get('store_key', ''));
    }

    /**
     * @return string
     */
    public static function storeSecret()
    {
        return trim((string)self::get('store_secret', ''));
    }

    /**
     * InSales shop domain, e.g. `my-shop.myinsales.ru`.
     *
     * @return string
     */
    public static function shop()
    {
        return trim((string)self::get('insales_shop', ''));
    }

    /**
     * @return string
     */
    public static function clientId()
    {
        return trim((string)self::get('insales_client_id', ''));
    }

    /**
     * @return string
     */
    public static function clientSecret()
    {
        return trim((string)self::get('insales_client_secret', ''));
    }

    /**
     * Password of the InSales application (used to verify webhook signatures).
     *
     * @return string
     */
    public static function appPassword()
    {
        return trim((string)self::get('insales_app_password', ''));
    }

    /**
     * @return string
     */
    public static function redirectUri()
    {
        $uri = trim((string)self::get('insales_redirect_uri', ''));

        if ($uri !== '') {
            return $uri;
        }

        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = isset($_SERVER['HTTP_HOST']) ? (string)$_SERVER['HTTP_HOST'] : 'localhost';

        return $scheme . '://' . $host . '/insales/install.php';
    }

    /**
     * @return string
     */
    public static function tokenFile()
    {
        return (string)self::get('token_file', dirname(__DIR__) . '/var/insales-token.json');
    }

    /**
     * @return string
     */
    public static function rateLimitFile()
    {
        return (string)self::get('rate_limit_file', dirname(__DIR__) . '/var/insales-rate.json');
    }

    /**
     * InSales allows ~500 requests per 5 minutes.
     *
     * @return int
     */
    public static function rateLimit()
    {
        return max(1, (int)self::get('rate_limit', 480));
    }

    /**
     * @return int
     */
    public static function rateWindow()
    {
        return max(1, (int)self::get('rate_window', 300));
    }

    /**
     * @return string
     */
    public static function stockSource()
    {
        return trim((string)self::get('stock_source', ''));
    }

    /**
     * @return bool
     */
    public static function debugEnabled()
    {
        return (bool)self::get('debug', false);
    }

    /**
     * @return string
     */
    public static function queueDir()
    {
        return (string)self::get('queue_dir', dirname(__DIR__) . '/var/queue');
    }

    /**
     * @return string
     */
    public static function logFile()
    {
        return (string)self::get('log_file', dirname(__DIR__) . '/var/connector.log');
    }

    /**
     * @return bool
     */
    public static function isConfigured()
    {
        return self::gatewayUrl() !== '' && self::storeKey() !== '' && self::storeSecret() !== '';
    }
}
