<?php

/**
 * Signed client for the TropaTT gateway with a shop-friendly timeout.
 */
class TropattClient
{
    const PATH_ORDERS = '/orders';
    const PATH_PING = '/ping';
    const PATH_PREFIX = '/_module/crm.ecommerce-gateway/v1';
    const DEFAULT_TIMEOUT = 5;

    /** @var string */
    private $baseUrl;
    /** @var string */
    private $storeKey;
    /** @var string */
    private $storeSecret;
    /** @var int */
    private $timeout;

    public function __construct($baseUrl = '', $storeKey = '', $storeSecret = '', $timeout = self::DEFAULT_TIMEOUT)
    {
        $this->baseUrl = rtrim((string)$baseUrl, '/');
        $this->storeKey = (string)$storeKey;
        $this->storeSecret = (string)$storeSecret;
        $this->timeout = min(10, max(1, (int)$timeout));
    }

    /**
     * @return int
     */
    public function timeout()
    {
        return $this->timeout;
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
        $nonce = TropattSignature::nonce();
        $signature = TropattSignature::signRequest($method, self::PATH_PREFIX . $path, $timestamp, $nonce, $rawBody, $this->storeSecret);

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

        return self::send(strtoupper($method), $this->baseUrl . $path, $rawBody, $headers, $this->timeout);
    }

    /**
     * @return array
     */
    public static function send($method, $url, $rawBody, array $headers = array(), $timeout = self::DEFAULT_TIMEOUT)
    {
        if (function_exists('curl_init')) {
            return self::sendWithCurl($method, $url, $rawBody, $headers, $timeout);
        }

        return self::sendWithStreams($method, $url, $rawBody, $headers, $timeout);
    }

    /**
     * @return array
     */
    private static function sendWithCurl($method, $url, $rawBody, array $headers, $timeout)
    {
        $flat = array();
        foreach ($headers as $name => $value) {
            // Accept both an associative array (name => value) and a ready-made
            // list of "Name: value" strings: numeric keys must not be prefixed,
            // otherwise the request carries headers literally named "0", "1", ...
            $flat[] = is_int($name) ? (string)$value : $name . ': ' . $value;
        }

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $flat);
        curl_setopt($ch, CURLOPT_TIMEOUT, (int)$timeout);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, (int)$timeout);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);

        if ($method !== 'GET') {
            curl_setopt($ch, CURLOPT_POSTFIELDS, (string)$rawBody);
        }

        $response = curl_exec($ch);
        $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        return self::present($status, (string)$response, $error === '' ? null : $error);
    }

    /**
     * Shared-hosting friendly fallback when cURL is unavailable.
     *
     * @return array
     */
    private static function sendWithStreams($method, $url, $rawBody, array $headers, $timeout)
    {
        $flat = array();
        foreach ($headers as $name => $value) {
            // Accept both an associative array (name => value) and a ready-made
            // list of "Name: value" strings: numeric keys must not be prefixed,
            // otherwise the request carries headers literally named "0", "1", ...
            $flat[] = is_int($name) ? (string)$value : $name . ': ' . $value;
        }

        $context = stream_context_create(array('http' => array(
            'method' => $method,
            'header' => implode("\r\n", $flat),
            'content' => (string)$rawBody,
            'timeout' => (int)$timeout,
            'ignore_errors' => true,
        )));

        // `$http_response_header` is deprecated from PHP 8.4, so the status is
        // read from the stream metadata instead.
        $handle = @fopen($url, 'rb', false, $context);
        if ($handle === false) {
            return self::present(0, '', 'stream request failed');
        }

        $status = 0;
        $meta = stream_get_meta_data($handle);
        foreach ((array)(isset($meta['wrapper_data']) ? $meta['wrapper_data'] : array()) as $header) {
            if (preg_match('#\s(\d{3})\s#', (string)$header, $match)) {
                $status = (int)$match[1];
                break;
            }
        }

        $body = (string)stream_get_contents($handle);
        fclose($handle);

        return self::present($status, $body, null);
    }

    /**
     * @return array
     */
    private static function present($status, $body, $error)
    {
        $decoded = json_decode($body, true);
        $code = (is_array($decoded) && isset($decoded['code'])) ? (string)$decoded['code'] : null;
        $success = $error === null && $status >= 200 && $status < 300;

        return array(
            'success' => $success,
            'http_code' => $status,
            'code' => $code,
            'error' => $success ? null : ($error !== null ? $error : 'HTTP ' . $status),
            'body' => $body,
        );
    }
}
