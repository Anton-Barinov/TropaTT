<?php

namespace Tropatt\Moguta;

/**
 * Plugin settings (stored by Moguta in its `mg_setting` table, exposed through
 * `MG::getSetting()` when the platform is present, or supplied as an array in
 * tests).
 */
class Config
{
    /**
     * @return array
     */
    public static function current()
    {
        $settings = array();

        if (function_exists('mgGetSetting')) {
            $settings = array(
                'enabled' => mgGetSetting('tropatt_enabled'),
                'gateway_url' => mgGetSetting('tropatt_gateway_url'),
                'store_key' => mgGetSetting('tropatt_store_key'),
                'store_secret' => mgGetSetting('tropatt_store_secret'),
                'webhook_secret' => mgGetSetting('tropatt_webhook_secret'),
                'default_stage' => mgGetSetting('tropatt_default_stage'),
                'status_mapping' => mgGetSetting('tropatt_status_mapping'),
                'debug' => mgGetSetting('tropatt_debug'),
            );
        }

        return self::normalize($settings);
    }

    /**
     * @return array
     */
    public static function normalize(array $settings)
    {
        return array(
            'enabled' => self::isOn($settings['enabled'] ?? null),
            'gateway_url' => rtrim(trim((string)($settings['gateway_url'] ?? '')), '/'),
            'store_key' => trim((string)($settings['store_key'] ?? '')),
            'store_secret' => trim((string)($settings['store_secret'] ?? '')),
            'webhook_secret' => trim((string)($settings['webhook_secret'] ?? '')),
            'default_stage' => trim((string)($settings['default_stage'] ?? 'new')),
            'debug' => self::isOn($settings['debug'] ?? null),
            'status_mapping' => StatusMapper::decode((string)($settings['status_mapping'] ?? '')),
            // Moguta plugins must answer the storefront quickly: the request
            // timeout is capped below 3 seconds.
            'timeout' => min(3, max(1, (int)($settings['timeout'] ?? 3))),
            'queue_dir' => (string)($settings['queue_dir'] ?? sys_get_temp_dir() . '/tropatt-moguta'),
            'log_file' => (string)($settings['log_file'] ?? sys_get_temp_dir() . '/tropatt-moguta.log'),
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
    private static function isOn($value)
    {
        return in_array($value, array('Y', 'y', '1', 1, true, 'true'), true);
    }
}
