<?php

namespace Tropatt\CsCart;

/**
 * Addon settings: read from the CS-Cart registry, or supplied as an array so the
 * mapper and the client can be unit tested without the platform.
 */
class Config
{
    /**
     * @return array
     */
    public static function fromRegistry()
    {
        $settings = array();

        if (class_exists('Registry')) {
            $settings = (array)Registry::get('addons.tropatt');
        }

        return self::normalize($settings);
    }

    /**
     * @return array
     */
    public static function normalize(array $settings)
    {
        return array(
            'enabled' => isset($settings['enabled']) && $settings['enabled'] === 'Y',
            'gateway_url' => rtrim(trim((string)($settings['gateway_url'] ?? '')), '/'),
            'store_key' => trim((string)($settings['store_key'] ?? '')),
            'store_secret' => trim((string)($settings['store_secret'] ?? '')),
            'webhook_secret' => trim((string)($settings['webhook_secret'] ?? '')),
            'default_stage' => trim((string)($settings['default_stage'] ?? 'new')),
            'split_by_vendor' => isset($settings['split_by_vendor']) && $settings['split_by_vendor'] === 'Y',
            'debug' => isset($settings['debug']) && $settings['debug'] === 'Y',
            'status_mapping' => StatusMapper::decode((string)($settings['status_mapping'] ?? '')),
            'queue_dir' => (string)($settings['queue_dir'] ?? sys_get_temp_dir() . '/tropatt-cs-cart'),
            'log_file' => (string)($settings['log_file'] ?? sys_get_temp_dir() . '/tropatt-cs-cart.log'),
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
