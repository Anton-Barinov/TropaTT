<?php

namespace Tropatt\Tilda;

/**
 * Connector configuration: `config.php` is created from `config.example.php`
 * next to the public webhook and is never committed.
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
        if ($file === null) {
            $file = dirname(__DIR__) . '/config.php';
        }

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
     * Secret token configured in the Tilda cart webhook.
     *
     * @return string
     */
    public static function tildaSecret()
    {
        return trim((string)self::get('tilda_secret', ''));
    }

    /**
     * @return array
     */
    public static function allowedIps()
    {
        $ips = self::get('allowed_ips', array());

        return is_array($ips) ? $ips : array_filter(array_map('trim', explode(',', (string)$ips)));
    }

    /**
     * Tilda Store API key (Tilda → Настройки → API).
     *
     * @return string
     */
    public static function tildaStoreApiKey()
    {
        return trim((string)self::get('tilda_store_api_key', ''));
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
        $default = rtrim(sys_get_temp_dir(), '/\\') . '/tropatt-tilda-' . substr(hash('sha256', dirname(__DIR__)), 0, 16) . '/queue';

        return (string)self::get('queue_dir', $default);
    }

    /**
     * @return string
     */
    public static function logFile()
    {
        $privateDir = rtrim(sys_get_temp_dir(), '/\\') . '/tropatt-tilda-' . substr(hash('sha256', dirname(__DIR__)), 0, 16);

        return (string)self::get('log_file', $privateDir . '/connector.log');
    }

    /**
     * @return bool
     */
    public static function isConfigured()
    {
        return self::gatewayUrl() !== '' && self::storeKey() !== '' && self::storeSecret() !== '';
    }
}
