<?php

namespace Tropatt\Crm;

/**
 * Gateway URL composition.
 *
 * The settings field shows the gateway URL exactly as the TropaTT CRM store card
 * hands it out, i.e. *with* the API path prefix already in place:
 *
 *     https://crm.example.com/api/index.php?route=/_module/crm.ecommerce-gateway/v1
 *
 * The connector signs the canonical path (`/_module/crm.ecommerce-gateway/v1/orders`,
 * see SignatureValidator::signRequest()), so that prefix has to appear in the request
 * URL exactly once. Appending Config::DEFAULT_PATH_PREFIX unconditionally produced
 *
 *     ...?route=/_module/crm.ecommerce-gateway/v1/_module/crm.ecommerce-gateway/v1/orders
 *
 * which the gateway answered with 404 - no order and not even the ping ever reached
 * the CRM. Both accepted input forms (with and without the prefix, with or without a
 * trailing slash) now resolve to the same endpoint.
 */
class GatewayUrl
{
    /**
     * Strip a duplicated API path prefix and the trailing slashes.
     *
     * @param string $gatewayUrl Raw setting value.
     * @return string Base URL without the API path prefix.
     */
    public static function base($gatewayUrl)
    {
        $url = rtrim(trim((string)$gatewayUrl), '/');
        $prefix = Config::DEFAULT_PATH_PREFIX;
        $prefixLength = strlen($prefix);

        if ($url === '' || $prefixLength === 0) {
            return $url;
        }

        // Strip every trailing occurrence: the setting is shown with the prefix, and an
        // installation configured while this bug was live can hold it twice over.
        while (substr($url, -$prefixLength) === $prefix) {
            $url = rtrim(substr($url, 0, -$prefixLength), '/');
        }

        return $url;
    }

    /**
     * Compose the absolute request URL for a canonical gateway path.
     *
     * Idempotent: the path is appended to the normalised base, so a base that still
     * carries the prefix never yields the doubled path.
     *
     * @param string $gatewayUrl Raw or normalised gateway URL.
     * @param string $path Canonical path, e.g. `/_module/crm.ecommerce-gateway/v1/orders`.
     * @return string Empty string when the gateway URL is not configured.
     */
    public static function endpoint($gatewayUrl, $path)
    {
        $base = self::base($gatewayUrl);
        if ($base === '') {
            return '';
        }

        return $base . '/' . ltrim((string)$path, '/');
    }
}
