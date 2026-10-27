<?php

namespace Tropatt\Shopify;

/**
 * Shopify request authentication.
 *
 *   webhook:  base64(HMAC-SHA256(raw_body, app_secret))  in `X-Shopify-Hmac-Sha256`
 *   OAuth:    hex(HMAC-SHA256(sorted_query_without_hmac, app_secret)) in `hmac`
 */
class ShopifySignature
{
    /**
     * @return bool
     */
    public static function verifyWebhook($appSecret, $rawBody, $signature)
    {
        $appSecret = (string)$appSecret;
        $signature = trim((string)$signature);

        if ($appSecret === '' || $signature === '') {
            return false;
        }

        $expected = base64_encode(hash_hmac('sha256', (string)$rawBody, $appSecret, true));

        return SignatureValidator::secureEquals($expected, $signature);
    }

    /**
     * @return string
     */
    public static function webhookHeader(array $server)
    {
        return isset($server['HTTP_X_SHOPIFY_HMAC_SHA256']) ? (string)$server['HTTP_X_SHOPIFY_HMAC_SHA256'] : '';
    }

    /**
     * Verify the `hmac` parameter of an OAuth redirect / app load.
     *
     * @return bool
     */
    public static function verifyQuery($appSecret, array $query)
    {
        if (empty($query['hmac'])) {
            return false;
        }

        $hmac = (string)$query['hmac'];
        unset($query['hmac']);

        ksort($query);

        $pairs = array();
        foreach ($query as $key => $value) {
            if (is_array($value)) {
                $value = implode(',', array_map('strval', $value));
            }
            $pairs[] = $key . '=' . $value;
        }

        $expected = hash_hmac('sha256', implode('&', $pairs), (string)$appSecret);

        return SignatureValidator::secureEquals($expected, $hmac);
    }

    /**
     * Verify the `state` parameter (CSRF protection of the install flow).
     *
     * @return bool
     */
    public static function verifyState($expected, $given)
    {
        return $expected !== '' && SignatureValidator::secureEquals((string)$expected, (string)$given);
    }

    /**
     * @return string
     */
    public static function state()
    {
        return SignatureValidator::nonce();
    }

    /**
     * Normalise a shop domain (`my-shop` -> `my-shop.myshopify.com`).
     *
     * @return string
     */
    public static function normalizeShop($shop)
    {
        $shop = strtolower(trim((string)$shop));
        $shop = preg_replace('#^https?://#i', '', $shop);
        $shop = rtrim($shop, '/');

        if ($shop !== '' && strpos($shop, '.') === false) {
            $shop .= '.myshopify.com';
        }

        return $shop;
    }

    /**
     * @return bool
     */
    public static function isValidShop($shop)
    {
        return (bool)preg_match('/^[a-z0-9][a-z0-9-]*\.myshopify\.com$/', (string)$shop);
    }
}
