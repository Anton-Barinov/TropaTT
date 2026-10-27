<?php

/**
 * Plugin settings normalisation.
 */
class shopTropattConfig
{
    /**
     * @return array
     */
    public static function load()
    {
        $plugin = wa('shop')->getPlugin('tropatt');

        return self::fromSettings($plugin ? $plugin->getSettings() : array());
    }

    /**
     * @return array
     */
    public static function fromSettings($settings)
    {
        $settings = (array)$settings;

        return array(
            'enabled' => !empty($settings['enabled']),
            'gateway_url' => rtrim(trim((string)($settings['gateway_url'] ?? '')), '/'),
            'store_key' => trim((string)($settings['store_key'] ?? '')),
            'store_secret' => trim((string)($settings['store_secret'] ?? '')),
            'webhook_secret' => trim((string)($settings['webhook_secret'] ?? '')),
            'default_stage' => trim((string)($settings['default_stage'] ?? 'new')),
            'status_mapping' => shopTropattStatusMapper::decode((string)($settings['status_mapping'] ?? '')),
            'debug' => !empty($settings['debug']),
            // The storefront must not wait for the CRM.
            'timeout' => min(3, max(1, (int)($settings['timeout'] ?? 3))),
            // Webasyst paths when the platform is present; a temp dir otherwise
            // (the mapper/config are also exercised outside Webasyst in tests).
            'queue_dir' => (string)($settings['queue_dir'] ?? (function_exists('wa')
                ? wa()->getDataPath('queue', true, 'shop', false)
                : sys_get_temp_dir() . '/tropatt-webasyst')),
            'log_file' => (string)($settings['log_file'] ?? (function_exists('wa')
                ? wa()->getDataPath('tropatt.log', false, 'shop')
                : sys_get_temp_dir() . '/tropatt-webasyst.log')),
        );
    }

    /**
     * @return bool
     */
    public static function isConfigured(array $config)
    {
        return !empty($config['gateway_url']) && !empty($config['store_key']) && !empty($config['store_secret']);
    }
}
