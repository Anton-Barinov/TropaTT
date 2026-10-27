<?php

namespace Tropatt\Crm\Model\Http;

use Magento\Framework\HTTP\Client\Curl;
use Tropatt\Crm\Model\Config;
use Tropatt\Crm\Model\Signature;

/**
 * Signed client for the TropaTT gateway.
 */
class Client
{
    public const PATH_ORDERS = '/orders';
    public const PATH_PING = '/ping';
    public const PATH_PREFIX = '/_module/crm.ecommerce-gateway/v1';
    public const TIMEOUT = 15;

    /** @var Curl */
    private $curl;

    /** @var Config */
    private $config;

    /** @var Signature */
    private $signature;

    public function __construct(Curl $curl, Config $config, Signature $signature)
    {
        $this->curl = $curl;
        $this->config = $config;
        $this->signature = $signature;
    }

    /**
     * @return array
     */
    public function ping($storeId = null)
    {
        return $this->request('GET', self::PATH_PING, '', null, $storeId);
    }

    /**
     * @return array
     */
    public function pushOrder(array $canonical, $storeId = null)
    {
        $raw = json_encode($canonical, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $externalId = (string)($canonical['external_id'] ?? '');

        return $this->request('POST', self::PATH_ORDERS, (string)$raw, $this->config->storeKey($storeId) . ':order:' . $externalId, $storeId);
    }

    /**
     * @return array
     */
    public function request($method, $path, $rawBody = '', $idempotencyKey = null, $storeId = null)
    {
        $gateway = $this->config->gatewayUrl($storeId);
        $key = $this->config->storeKey($storeId);
        $secret = $this->config->storeSecret($storeId);

        if ($gateway === '' || $key === '' || $secret === '') {
            return ['success' => false, 'http_code' => 0, 'code' => null, 'error' => 'Gateway is not configured', 'body' => ''];
        }

        $timestamp = (string)time();
        $nonce = $this->signature->nonce();
        $signature = $this->signature->signRequest($method, self::PATH_PREFIX . $path, $timestamp, $nonce, $rawBody, $secret);

        $this->curl->setTimeout(self::TIMEOUT);
        $this->curl->addHeader('Content-Type', 'application/json');
        $this->curl->addHeader('Accept', 'application/json');
        $this->curl->addHeader('X-Store-Key', $key);
        $this->curl->addHeader('X-TropaTT-Timestamp', $timestamp);
        $this->curl->addHeader('X-TropaTT-Nonce', $nonce);
        $this->curl->addHeader('X-TropaTT-Signature', $signature);

        if ($idempotencyKey !== null && $idempotencyKey !== '') {
            $this->curl->addHeader('X-TropaTT-Idempotency-Key', $idempotencyKey);
        }

        $url = $gateway . $path;

        try {
            if (strtoupper($method) === 'GET') {
                $this->curl->get($url);
            } else {
                $this->curl->post($url, (string)$rawBody);
            }
        } catch (\Throwable $exception) {
            return ['success' => false, 'http_code' => 0, 'code' => null, 'error' => $exception->getMessage(), 'body' => ''];
        }

        $status = (int)$this->curl->getStatus();
        $body = (string)$this->curl->getBody();
        $decoded = json_decode($body, true);
        $code = is_array($decoded) && isset($decoded['code']) ? (string)$decoded['code'] : null;
        $success = $status >= 200 && $status < 300;

        return [
            'success' => $success,
            'http_code' => $status,
            'code' => $code,
            'error' => $success ? null : 'HTTP ' . $status . ': ' . $body,
            'body' => $body,
        ];
    }
}
