<?php

namespace Tropatt\Shopify;

/**
 * Shopify OAuth2 (authorization code) flow for a self-hosted app.
 *
 * Public apps must use **expiring** offline access tokens for the GraphQL Admin
 * API (mandatory from 2027-01-01), so the code exchange asks for one with
 * `expiring=1` and the refresh token it returns is stored and renewed by
 * `cron/refresh_token.php`. Both the exchange and the refresh are sent
 * form-encoded, the body format the token endpoint documents.
 */
class OAuthClient
{
    const AUTHORIZE_PATH = '/admin/oauth/authorize';
    const TOKEN_PATH = '/admin/oauth/access_token';

    /** Refresh once the token has this many seconds (or fewer) left. */
    const REFRESH_SKEW_SECONDS = 300;

    /** @var string */
    private $shop;
    /** @var string */
    private $apiKey;
    /** @var string */
    private $apiSecret;

    public function __construct($shop = null, $apiKey = null, $apiSecret = null)
    {
        $this->shop = ShopifySignature::normalizeShop($shop === null ? Config::get('shopify_shop', '') : $shop);
        $this->apiKey = $apiKey === null ? Config::apiKey() : (string)$apiKey;
        $this->apiSecret = $apiSecret === null ? Config::apiSecret() : (string)$apiSecret;
    }

    /**
     * @return string
     */
    public static function authorizeUrl($shop, $apiKey, array $scopes, $redirectUri, $state = '')
    {
        $query = array(
            'client_id' => (string)$apiKey,
            'scope' => implode(',', $scopes),
            'redirect_uri' => (string)$redirectUri,
        );

        if ($state !== '') {
            $query['state'] = (string)$state;
        }

        return 'https://' . ShopifySignature::normalizeShop($shop) . self::AUTHORIZE_PATH . '?' . http_build_query($query);
    }

    /**
     * Authorization-code token exchange body (pure, unit-testable).
     *
     * `expiring=1` requests an expiring offline token together with a refresh
     * token; without it Shopify issues a non-expiring token that public apps can
     * no longer use for the Admin API.
     *
     * @return array
     */
    public static function tokenRequestBody($apiKey, $apiSecret, $code)
    {
        return array(
            'client_id' => (string)$apiKey,
            'client_secret' => (string)$apiSecret,
            'code' => (string)$code,
            'expiring' => '1',
        );
    }

    /**
     * Refresh-token grant body (pure, unit-testable).
     *
     * @return array
     */
    public static function refreshRequestBody($apiKey, $apiSecret, $refreshToken)
    {
        return array(
            'client_id' => (string)$apiKey,
            'client_secret' => (string)$apiSecret,
            'grant_type' => 'refresh_token',
            'refresh_token' => (string)$refreshToken,
        );
    }

    /**
     * @return string
     */
    public static function encodeForm(array $params)
    {
        return http_build_query($params, '', '&', PHP_QUERY_RFC3986);
    }

    /**
     * @return string
     */
    public function redirectUrl($state = '')
    {
        return self::authorizeUrl($this->shop, $this->apiKey, Config::scopes(), Config::redirectUri(), $state);
    }

    /**
     * @return string
     */
    public function tokenEndpoint()
    {
        return 'https://' . $this->shop . self::TOKEN_PATH;
    }

    /**
     * @return array
     */
    public function exchangeCode($code)
    {
        return $this->postToken(self::tokenRequestBody($this->apiKey, $this->apiSecret, $code));
    }

    /**
     * Renew the stored expiring token server-side (no merchant session needed).
     *
     * @return array
     */
    public function refresh($refreshToken = null)
    {
        if ($refreshToken === null) {
            $stored = TokenStore::load();
            $refreshToken = isset($stored['refresh_token']) ? (string)$stored['refresh_token'] : '';
        }

        if ((string)$refreshToken === '') {
            return array('success' => false, 'error' => 'No refresh token stored: reinstall the app');
        }

        $result = $this->postToken(self::refreshRequestBody($this->apiKey, $this->apiSecret, (string)$refreshToken));

        if (!$result['success']) {
            return array(
                'success' => false,
                'error' => $result['error'],
                // A rejected refresh token is terminal; everything else is worth retrying.
                'reauth_required' => strpos((string)$result['error'], 'HTTP 401') === 0,
            );
        }

        return $result;
    }

    /**
     * Send one token request and persist the result.
     *
     * @return array
     */
    private function postToken(array $body)
    {
        $result = CrmClient::send(
            'POST',
            $this->tokenEndpoint(),
            self::encodeForm($body),
            array('Content-Type: application/x-www-form-urlencoded', 'Accept: application/json')
        );

        if (!$result['success']) {
            return array('success' => false, 'error' => $result['error']);
        }

        $decoded = json_decode((string)$result['body'], true);
        if (!is_array($decoded) || empty($decoded['access_token'])) {
            return array('success' => false, 'error' => 'Token response has no access_token');
        }

        $decoded['shop'] = $this->shop;
        $decoded['obtained_at'] = time();
        TokenStore::save($decoded);

        return array('success' => true, 'token' => $decoded);
    }
}
