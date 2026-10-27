<?php

namespace Tropatt\Crm;

/**
 * HTTP client for the TropaTT e-commerce gateway.
 *
 * Uses the D7 HTTP client when it is available and falls back to cURL, so the
 * connector also works on minimal Bitrix installations.
 */
class Client
{
    const TIMEOUT = 15;
    const MAX_ATTEMPTS = 3;

    /** @var string */
    private $gatewayUrl;

    /** @var string */
    private $storeKey;

    /** @var string */
    private $storeSecret;

    public function __construct($gatewayUrl = null, $storeKey = null, $storeSecret = null)
    {
        $this->gatewayUrl = GatewayUrl::base($gatewayUrl === null ? Config::gatewayUrl() : (string)$gatewayUrl);
        $this->storeKey = $storeKey === null ? Config::storeKey() : (string)$storeKey;
        $this->storeSecret = $storeSecret === null ? Config::storeSecret() : (string)$storeSecret;
    }

    /**
     * @return array{success:bool, code:?string, http_code:int, error:?string, body:string}
     */
    public function request($method, $path, $rawBody = '', $idempotencyKey = '')
    {
        $timestamp = (string)time();
        $nonce = SignatureValidator::nonce();
        $signature = SignatureValidator::signRequest($method, $path, $timestamp, $nonce, $rawBody, $this->storeSecret);

        $headers = array(
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
            'X-Store-Key' => $this->storeKey,
            'X-TropaTT-Timestamp' => $timestamp,
            'X-TropaTT-Nonce' => $nonce,
            'X-TropaTT-Signature' => $signature,
        );

        if ($idempotencyKey !== '') {
            $headers['X-TropaTT-Idempotency-Key'] = $idempotencyKey;
        }

        $url = $this->endpoint($path);
        $lastResult = null;

        for ($attempt = 1; $attempt <= self::MAX_ATTEMPTS; $attempt++) {
            $lastResult = $this->send($method, $url, $rawBody, $headers);

            if ($lastResult['success'] || ($lastResult['http_code'] > 0 && $lastResult['http_code'] < 500)) {
                return $lastResult;
            }

            if ($attempt < self::MAX_ATTEMPTS) {
                sleep($attempt * 2);
            }
        }

        return $lastResult;
    }

    /**
     * Absolute URL of a canonical gateway path.
     *
     * The API path prefix is kept single even when the stored setting already carries it
     * (see GatewayUrl for the 404 this caused), and the composition is public so it can be
     * asserted without performing a request.
     *
     * @param string $path Canonical path, e.g. `/_module/crm.ecommerce-gateway/v1/orders`.
     * @return string
     */
    public function endpoint($path)
    {
        return GatewayUrl::endpoint($this->gatewayUrl, $path);
    }

    /**
     * @return array
     */
    private function send($method, $url, $rawBody, array $headers)
    {
        if (class_exists('\Bitrix\Main\Web\HttpClient')) {
            return $this->sendWithBitrix($method, $url, $rawBody, $headers);
        }

        return $this->sendWithCurl($method, $url, $rawBody, $headers);
    }

    /**
     * @return array
     */
    private function sendWithBitrix($method, $url, $rawBody, array $headers)
    {
        $http = new \Bitrix\Main\Web\HttpClient(array('socketTimeout' => self::TIMEOUT, 'streamTimeout' => self::TIMEOUT));

        foreach ($headers as $name => $value) {
            $http->setHeader($name, $value);
        }

        if (strtoupper($method) === 'GET') {
            $response = $http->get($url);
        } else {
            $response = $http->post($url, (string)$rawBody);
        }

        $status = (int)$http->getStatus();
        $error = $http->getError();

        return $this->present($status, (string)$response, $error === array() ? null : implode('; ', (array)$error));
    }

    /**
     * @return array
     */
    private function sendWithCurl($method, $url, $rawBody, array $headers)
    {
        $flatHeaders = array();
        foreach ($headers as $name => $value) {
            $flatHeaders[] = $name . ': ' . $value;
        }

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, strtoupper($method));
        curl_setopt($ch, CURLOPT_HTTPHEADER, $flatHeaders);
        curl_setopt($ch, CURLOPT_TIMEOUT, self::TIMEOUT);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);

        if (strtoupper($method) !== 'GET') {
            curl_setopt($ch, CURLOPT_POSTFIELDS, (string)$rawBody);
        }

        $response = curl_exec($ch);
        $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        return $this->present($status, (string)$response, $error === '' ? null : $error);
    }

    /**
     * @return array
     */
    private function present($status, $body, $error)
    {
        $decoded = json_decode($body, true);
        $code = (is_array($decoded) && isset($decoded['code'])) ? (string)$decoded['code'] : null;
        $success = $error === null && $status >= 200 && $status < 300;

        return array(
            'success' => $success,
            'code' => $code,
            'http_code' => $status,
            'error' => $success ? null : ($error !== null ? $error : 'HTTP ' . $status . ': ' . $body),
            'body' => $body,
        );
    }

    /**
     * Signed ping used by the settings page.
     *
     * @return array
     */
    public function ping()
    {
        return $this->request('GET', Config::DEFAULT_PATH_PREFIX . '/ping');
    }

    /**
     * Push one canonical order payload.
     *
     * @return array
     */
    public function pushOrder(array $payload)
    {
        $rawBody = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $externalId = isset($payload['external_id']) ? (string)$payload['external_id'] : '';

        return $this->request(
            'POST',
            Config::DEFAULT_PATH_PREFIX . '/orders',
            (string)$rawBody,
            $this->storeKey . ':order:' . $externalId
        );
    }
}
