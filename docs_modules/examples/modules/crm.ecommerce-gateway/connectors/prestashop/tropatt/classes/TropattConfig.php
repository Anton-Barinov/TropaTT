<?php

/**
 * Module settings, stored through the PrestaShop Configuration API.
 *
 * The class degrades gracefully when PrestaShop is absent, so the mapper and the
 * client can be unit tested (and the queue/logger used) outside the platform.
 */
class TropattConfig
{
    const KEYS = array(
        'enabled' => 'TROPATT_ENABLED',
        'gateway_url' => 'TROPATT_GATEWAY_URL',
        'store_key' => 'TROPATT_STORE_KEY',
        'store_secret' => 'TROPATT_STORE_SECRET',
        'webhook_secret' => 'TROPATT_WEBHOOK_SECRET',
        'default_stage' => 'TROPATT_DEFAULT_STAGE',
        'status_mapping' => 'TROPATT_STATUS_MAPPING',
        'debug' => 'TROPATT_DEBUG',
        'timeout' => 'TROPATT_TIMEOUT',
    );

    const DEFAULT_STATUS_MAPPING = "2=in_progress\n3=review\n4=done\n6=canceled";

    /**
     * Raw values as stored (used by the settings form).
     *
     * @return array
     */
    public static function loadRaw()
    {
        $values = array();
        foreach (self::KEYS as $name => $key) {
            $values[$key] = self::configurationGet($key, '');
        }
        $values['TROPATT_STATUS_MAPPING'] = $values['TROPATT_STATUS_MAPPING'] === ''
            ? self::DEFAULT_STATUS_MAPPING
            : $values['TROPATT_STATUS_MAPPING'];
        $values['TROPATT_DEFAULT_STAGE'] = $values['TROPATT_DEFAULT_STAGE'] === '' ? 'new' : $values['TROPATT_DEFAULT_STAGE'];
        $values['TROPATT_TIMEOUT'] = $values['TROPATT_TIMEOUT'] === '' ? 5 : (int)$values['TROPATT_TIMEOUT'];

        return $values;
    }

    /**
     * @return array
     */
    public static function load()
    {
        return self::normalize(self::loadRaw());
    }

    /**
     * @return array
     */
    public static function normalize(array $values)
    {
        $enabled = isset($values['TROPATT_ENABLED']) ? $values['TROPATT_ENABLED'] : (isset($values['enabled']) ? $values['enabled'] : 0);
        $debug = isset($values['TROPATT_DEBUG']) ? $values['TROPATT_DEBUG'] : (isset($values['debug']) ? $values['debug'] : 0);
        $url = isset($values['TROPATT_GATEWAY_URL']) ? $values['TROPATT_GATEWAY_URL'] : (isset($values['gateway_url']) ? $values['gateway_url'] : '');
        $key = isset($values['TROPATT_STORE_KEY']) ? $values['TROPATT_STORE_KEY'] : (isset($values['store_key']) ? $values['store_key'] : '');
        $secret = isset($values['TROPATT_STORE_SECRET']) ? $values['TROPATT_STORE_SECRET'] : (isset($values['store_secret']) ? $values['store_secret'] : '');
        $webhookSecret = isset($values['TROPATT_WEBHOOK_SECRET']) ? $values['TROPATT_WEBHOOK_SECRET'] : (isset($values['webhook_secret']) ? $values['webhook_secret'] : '');
        $stage = isset($values['TROPATT_DEFAULT_STAGE']) ? $values['TROPATT_DEFAULT_STAGE'] : (isset($values['default_stage']) ? $values['default_stage'] : 'new');
        $mapping = isset($values['TROPATT_STATUS_MAPPING']) ? $values['TROPATT_STATUS_MAPPING'] : (isset($values['status_mapping']) ? $values['status_mapping'] : self::DEFAULT_STATUS_MAPPING);
        $timeout = isset($values['TROPATT_TIMEOUT']) ? $values['TROPATT_TIMEOUT'] : (isset($values['timeout']) ? $values['timeout'] : 5);

        $statusMapping = TropattStatusMapper::decode($mapping);

        return array(
            'enabled' => !empty($enabled),
            'gateway_url' => rtrim(trim((string)$url), '/'),
            'store_key' => trim((string)$key),
            'store_secret' => trim((string)$secret),
            'webhook_secret' => trim((string)$webhookSecret),
            'default_stage' => trim((string)$stage) === '' ? 'new' : trim((string)$stage),
            'status_mapping' => $statusMapping,
            'debug' => !empty($debug),
            'timeout' => min(10, max(1, (int)$timeout)),
            'queue_dir' => dirname(__DIR__) . '/var/queue',
            'log_file' => dirname(__DIR__) . '/var/tropatt.log',
        );
    }

    /**
     * @return bool
     */
    public static function isConfigured(array $config)
    {
        return !empty($config['gateway_url']) && !empty($config['store_key']) && !empty($config['store_secret']);
    }

    /**
     * @return bool
     */
    public static function save(array $values)
    {
        $ok = true;

        foreach (array(
            'TROPATT_ENABLED' => (int)$values['enabled'],
            'TROPATT_GATEWAY_URL' => trim((string)$values['gateway_url']),
            'TROPATT_STORE_KEY' => trim((string)$values['store_key']),
            'TROPATT_STORE_SECRET' => trim((string)$values['store_secret']),
            'TROPATT_WEBHOOK_SECRET' => trim((string)$values['webhook_secret']),
            'TROPATT_DEFAULT_STAGE' => trim((string)$values['default_stage']),
            'TROPATT_STATUS_MAPPING' => (string)$values['status_mapping'],
            'TROPATT_DEBUG' => (int)$values['debug'],
        ) as $key => $value) {
            $ok = self::configurationUpdate($key, $value) && $ok;
        }

        return $ok;
    }

    /**
     * @return bool
     */
    public static function installDefaults()
    {
        self::configurationUpdate('TROPATT_TIMEOUT', 5);
        self::configurationUpdate('TROPATT_STATUS_MAPPING', self::DEFAULT_STATUS_MAPPING);
        self::configurationUpdate('TROPATT_DEFAULT_STAGE', 'new');

        return true;
    }

    /**
     * @return bool
     */
    public static function removeDefaults()
    {
        $ok = true;
        foreach (self::KEYS as $key) {
            $ok = self::configurationDelete($key) && $ok;
        }

        return $ok;
    }

    /**
     * Echo suppression flag: set while the webhook writes a status.
     *
     * @return bool
     */
    public static function isEchoSuppressed()
    {
        if (class_exists('Configuration')) {
            return (int)Configuration::get('TROPATT_SKIP_SYNC') === 1;
        }

        return !empty($GLOBALS['TROPATT_SKIP_SYNC']);
    }

    /**
     * @return void
     */
    public static function setEchoSuppressed($flag)
    {
        if (class_exists('Configuration')) {
            Configuration::updateValue('TROPATT_SKIP_SYNC', $flag ? 1 : 0);

            return;
        }

        $GLOBALS['TROPATT_SKIP_SYNC'] = (bool)$flag;
    }

    /**
     * @return string
     */
    public static function webhookUrl()
    {
        if (class_exists('Context')) {
            $shop = Context::getContext()->shop;
            $base = $shop && isset($shop->domain) ? 'https://' . $shop->domain : 'https://shop.example.com';

            return $base . '/index.php?fc=module&module=tropatt&controller=webhook';
        }

        return 'https://shop.example.com/index.php?fc=module&module=tropatt&controller=webhook';
    }

    /**
     * @return mixed
     */
    private static function configurationGet($key, $default)
    {
        return class_exists('Configuration') ? Configuration::get($key) : $default;
    }

    /**
     * @return bool
     */
    private static function configurationUpdate($key, $value)
    {
        return class_exists('Configuration') ? (bool)Configuration::updateValue($key, $value) : true;
    }

    /**
     * @return bool
     */
    private static function configurationDelete($key)
    {
        return class_exists('Configuration') ? (bool)Configuration::deleteByName($key) : true;
    }
}
