<?php

namespace Tropatt\Shopify;

/**
 * Signed client for the TropaTT e-commerce gateway.
 */
class CrmClient
{
    const PATH_ORDERS = '/orders';
    const PATH_PING = '/ping';
    const PATH_STOCK = '/stock';

    const TIMEOUT = 10;

    /**
     * Test seam: when set, it replaces the cURL transport and receives
     * ($method, $url, $rawBody, $headers) — exactly the arguments of send().
     *
     * It exists so the argument order of every outbound call is asserted by an
     * executed test instead of a static text match: a swapped $method/$url pair
     * (which once sent every GraphQL request to a host named "POST") is
     * invisible to `str_contains` checks but impossible to miss here.
     *
     * @var callable|null
     */
    public static $transport = null;

    /** @var string */
    private $baseUrl;

    /** @var string */
    private $pathPrefix;

    /** @var string */
    private $storeKey;

    /** @var string */
    private $storeSecret;

    public function __construct($baseUrl = null, $storeKey = null, $storeSecret = null, $pathPrefix = '/_module/crm.ecommerce-gateway/v1')
    {
        $this->baseUrl = $baseUrl === null ? Config::gatewayUrl() : rtrim((string)$baseUrl, '/');
        $this->storeKey = $storeKey === null ? Config::storeKey() : (string)$storeKey;
        $this->storeSecret = $storeSecret === null ? Config::storeSecret() : (string)$storeSecret;
        $this->pathPrefix = rtrim((string)$pathPrefix, '/');
    }

    /**
     * @return array
     */
    public function ping()
    {
        return $this->request('GET', self::PATH_PING);
    }

    /**
     * @return array
     */
    public function pushOrder(array $canonical)
    {
        $raw = json_encode($canonical, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $externalId = isset($canonical['external_id']) ? (string)$canonical['external_id'] : '';

        return $this->request('POST', self::PATH_ORDERS, (string)$raw, $this->storeKey . ':order:' . $externalId);
    }

    /**
     * @return array
     */
    public function request($method, $path, $rawBody = '', $idempotencyKey = '')
    {
        $timestamp = (string)time();
        $nonce = SignatureValidator::nonce();
        $signature = SignatureValidator::signRequest(
            $method,
            $this->pathPrefix . $path,
            $timestamp,
            $nonce,
            $rawBody,
            $this->storeSecret
        );

        $headers = array(
            'Content-Type: application/json',
            'Accept: application/json',
            'X-Store-Key: ' . $this->storeKey,
            'X-TropaTT-Timestamp: ' . $timestamp,
            'X-TropaTT-Nonce: ' . $nonce,
            'X-TropaTT-Signature: ' . $signature,
        );

        if ($idempotencyKey !== '') {
            $headers['X-TropaTT-Idempotency-Key'] = $idempotencyKey;
        }

        return self::send(strtoupper($method), $this->baseUrl . $path, $rawBody, $headers);
    }

    /**
     * @return array
     */
    public static function send($method, $url, $rawBody, array $headers = array())
    {
        $method = strtoupper((string)$method);

        if (self::$transport !== null) {
            $transport = self::$transport;
            return (array)$transport($method, (string)$url, (string)$rawBody, $headers);
        }

        if (!function_exists('curl_init')) {
            return array('success' => false, 'http_code' => 0, 'code' => null, 'error' => 'cURL is not available', 'body' => '', 'headers' => array());
        }

        $flat = array();
        foreach ($headers as $name => $value) {
            // Accept both an associative array (name => value) and a ready-made
            // list of "Name: value" strings: numeric keys must not be prefixed,
            // otherwise the request carries headers literally named "0", "1", ...
            $flat[] = is_int($name) ? (string)$value : $name . ': ' . $value;
        }

        $responseHeaders = array();

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        // Response headers are needed to detect a silent API-version fallback
        // (Shopify serves the request with another version and says so in
        // X-Shopify-API-Version), which a body-only client cannot see.
        curl_setopt($ch, CURLOPT_HEADERFUNCTION, static function ($handle, $line) use (&$responseHeaders) {
            $parts = explode(':', (string)$line, 2);
            if (count($parts) === 2) {
                $responseHeaders[strtolower(trim($parts[0]))] = trim($parts[1]);
            }

            return strlen((string)$line);
        });
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $flat);
        curl_setopt($ch, CURLOPT_TIMEOUT, self::TIMEOUT);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);

        if ($method !== 'GET') {
            curl_setopt($ch, CURLOPT_POSTFIELDS, (string)$rawBody);
        }

        $response = curl_exec($ch);
        $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        $decoded = json_decode((string)$response, true);
        $code = (is_array($decoded) && isset($decoded['code'])) ? (string)$decoded['code'] : null;
        $success = $error === '' && $status >= 200 && $status < 300;

        return array(
            'success' => $success,
            'http_code' => $status,
            'code' => $code,
            'error' => $success ? null : ($error !== '' ? $error : 'HTTP ' . $status),
            'body' => (string)$response,
            'headers' => $responseHeaders,
        );
    }
}
