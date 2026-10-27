<?php

namespace Tropatt\InSales;

/**
 * OAuth2 authorization-code flow for an InSales application.
 */
class OAuthClient
{
    const AUTHORIZE_PATH = '/admin/oauth/authorize';
    const TOKEN_PATH = '/admin/oauth/token';

    /** @var string */
    private $shop;

    /** @var string */
    private $clientId;

    /** @var string */
    private $clientSecret;

    public function __construct($shop = null, $clientId = null, $clientSecret = null)
    {
        $this->shop = self::normalizeShop($shop === null ? Config::shop() : (string)$shop);
        $this->clientId = $clientId === null ? Config::clientId() : (string)$clientId;
        $this->clientSecret = $clientSecret === null ? Config::clientSecret() : (string)$clientSecret;
    }

    /**
     * @return string
     */
    public static function normalizeShop($shop)
    {
        $shop = trim((string)$shop);
        $shop = preg_replace('#^https?://#i', '', $shop);
        $shop = rtrim($shop, '/');

        if ($shop !== '' && strpos($shop, '.') === false) {
            $shop .= '.myinsales.ru';
        }

        return $shop;
    }

    /**
     * @return string
     */
    public static function authorizeUrl($shop, $clientId, $redirectUri, $state = '')
    {
        $query = array(
            'client_id' => (string)$clientId,
            'redirect_uri' => (string)$redirectUri,
            'response_type' => 'code',
        );

        if ($state !== '') {
            $query['state'] = (string)$state;
        }

        return 'https://' . self::normalizeShop($shop) . self::AUTHORIZE_PATH . '?' . http_build_query($query);
    }

    /**
     * Token endpoint body (pure, unit-testable).
     *
     * @return array
     */
    public static function tokenRequestBody($grantType, $clientId, $clientSecret, array $extra = array())
    {
        return array_merge(array(
            'grant_type' => (string)$grantType,
            'client_id' => (string)$clientId,
            'client_secret' => (string)$clientSecret,
        ), $extra);
    }

    /**
     * @return string
     */
    public function authorizeUrlForRedirect($state = '')
    {
        return self::authorizeUrl($this->shop, $this->clientId, Config::redirectUri(), $state);
    }

    /**
     * Exchange the authorization code for tokens and store them.
     *
     * @return array
     */
    public function exchangeCode($code)
    {
        return $this->requestToken(self::tokenRequestBody('authorization_code', $this->clientId, $this->clientSecret, array(
            'code' => (string)$code,
            'redirect_uri' => Config::redirectUri(),
        )));
    }

    /**
     * @return array
     */
    public function refresh(array $token)
    {
        if (empty($token['refresh_token'])) {
            return array('success' => false, 'error' => 'No refresh token stored');
        }

        return $this->requestToken(self::tokenRequestBody('refresh_token', $this->clientId, $this->clientSecret, array(
            'refresh_token' => (string)$token['refresh_token'],
        )));
    }

    /**
     * @return array
     */
    private function requestToken(array $body)
    {
        $result = CrmClient::send('POST', 'https://' . $this->shop . self::TOKEN_PATH, http_build_query($body), array(
            'Content-Type: application/x-www-form-urlencoded',
            'Accept: application/json',
        ));

        if (!$result['success']) {
            return array('success' => false, 'error' => (string)$result['error']);
        }

        $decoded = json_decode((string)$result['body'], true);
        if (!is_array($decoded) || empty($decoded['access_token'])) {
            return array('success' => false, 'error' => 'Token response has no access_token');
        }

        $decoded['expires_at'] = time() + (int)($decoded['expires_in'] ?? 3600);
        TokenStore::save($decoded);

        return array('success' => true, 'token' => $decoded);
    }
}
