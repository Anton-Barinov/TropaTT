<?php

namespace Tropatt\Crm;

use Bitrix\Main\Config\Option;

/**
 * Module settings with the defaults the connector ships with.
 */
class Config
{
    const MODULE_ID = 'tropatt.crm';

    const DEFAULT_PATH_PREFIX = '/_module/crm.ecommerce-gateway/v1';

    public static function isEnabled()
    {
        return Option::get(self::MODULE_ID, 'enabled', 'N') === 'Y';
    }

    /**
     * Base gateway URL, normalised to never carry the API path prefix twice.
     *
     * The setting is filled from the CRM store card, which already includes
     * `/_module/crm.ecommerce-gateway/v1`, while the client signs (and appends) that
     * canonical path itself - see GatewayUrl.
     *
     * @return string
     */
    public static function gatewayUrl()
    {
        return GatewayUrl::base((string)Option::get(self::MODULE_ID, 'gateway_url', ''));
    }

    public static function storeKey()
    {
        return trim((string)Option::get(self::MODULE_ID, 'store_key', ''));
    }

    public static function storeSecret()
    {
        return trim((string)Option::get(self::MODULE_ID, 'store_secret', ''));
    }

    public static function debugEnabled()
    {
        return Option::get(self::MODULE_ID, 'debug', 'N') === 'Y';
    }

    public static function maxAttempts()
    {
        return max(1, (int)Option::get(self::MODULE_ID, 'max_attempts', '8'));
    }

    /**
     * Status mapping: Bitrix STATUS_ID => CRM stage code, stored as a PHP array.
     *
     * @return array
     */
    public static function statusMapping()
    {
        return StatusMapper::decode(Option::get(self::MODULE_ID, 'status_mapping', ''));
    }

    public static function isConfigured()
    {
        return self::gatewayUrl() !== '' && self::storeKey() !== '' && self::storeSecret() !== '';
    }
}
