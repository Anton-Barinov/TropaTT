<?php

namespace Tropatt\InSales;

/**
 * InSales Admin API client with token refresh and rate limiting.
 */
class InSalesClient
{
    /** @var string */
    private $shop;

    public function __construct($shop = null)
    {
        $this->shop = OAuthClient::normalizeShop($shop === null ? Config::shop() : (string)$shop);
    }

    /**
     * GET /admin/orders/{id}.json
     *
     * @return array
     */
    public function getOrder($orderId)
    {
        return $this->request('GET', '/admin/orders/' . (int)$orderId . '.json');
    }

    /**
     * PUT /admin/orders/{id}.json — fulfilment status change.
     *
     * @return array
     */
    public function updateOrderStatus($orderId, $status, $comment = '')
    {
        $order = array('fulfillment_status' => (string)$status);
        if ($comment !== '') {
            $order['manager_comment'] = (string)$comment;
        }

        return $this->request('PUT', '/admin/orders/' . (int)$orderId . '.json', array('order' => $order));
    }

    /**
     * Build the stock update request for /admin/products/variants_group_update.json.
     *
     * @return array
     */
    public static function variantsUpdateRequest(array $variants)
    {
        $payload = array();

        foreach ($variants as $variant) {
            $entry = array();

            if (!empty($variant['id'])) {
                $entry['id'] = (int)$variant['id'];
            } elseif (!empty($variant['sku'])) {
                $entry['sku'] = (string)$variant['sku'];
            } else {
                continue;
            }

            if (array_key_exists('quantity', $variant)) {
                $entry['quantity'] = max(0, (int)$variant['quantity']);
            }

            if (array_key_exists('price', $variant) && $variant['price'] !== null && $variant['price'] !== '') {
                $entry['price'] = number_format((float)$variant['price'], 2, '.', '');
            }

            $payload[] = $entry;
        }

        return array('variants' => $payload);
    }

    /**
     * CRM stock rows -> InSales variants (pure).
     *
     * @return array
     */
    public static function mapStockRows(array $rows)
    {
        $variants = array();

        foreach ($rows as $row) {
            if (!is_array($row) || empty($row['sku'])) {
                continue;
            }

            $variant = array('sku' => (string)$row['sku'], 'quantity' => isset($row['quantity']) ? (int)$row['quantity'] : 0);

            if (isset($row['price_minor']) && $row['price_minor'] !== null) {
                $variant['price'] = (float)(((int)$row['price_minor']) / 100);
            }

            $variants[] = $variant;
        }

        return $variants;
    }

    /**
     * @return array
     */
    public function pushStock(array $variants)
    {
        if ($variants === array()) {
            return array('success' => false, 'sent' => 0, 'error' => 'No variants to update');
        }

        // InSales Admin API: групповое обновление модификаций —
        // PUT /admin/products/variants_group_update.json (см. официальную схему API).
        $result = $this->request('PUT', '/admin/products/variants_group_update.json', self::variantsUpdateRequest($variants));

        return array(
            'success' => $result['success'],
            'sent' => $result['success'] ? count($variants) : 0,
            'error' => $result['success'] ? null : (string)$result['error'],
        );
    }

    /**
     * @return array
     */
    public function request($method, $path, ?array $body = null)
    {
        $token = TokenStore::load();

        if ($token === array()) {
            return array('success' => false, 'http_code' => 0, 'status' => 0, 'error' => 'The application is not installed: no token stored', 'body' => '');
        }

        if (TokenStore::isExpired($token)) {
            $refreshed = (new OAuthClient())->refresh($token);
            if (!$refreshed['success']) {
                return array('success' => false, 'http_code' => 0, 'status' => 0, 'error' => 'Token refresh failed: ' . $refreshed['error'], 'body' => '');
            }
            $token = $refreshed['token'];
        }

        $allowed = $this->consumeRateLimit();
        if (!$allowed['success']) {
            return array('success' => false, 'http_code' => 429, 'status' => 429, 'error' => 'InSales rate limit reached, retry in ' . (int)$allowed['retry_after'] . 's', 'body' => '');
        }

        $rawBody = $body === null ? '' : json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $headers = array(
            'Content-Type: application/json',
            'Accept: application/json',
            'Authorization: Basic ' . base64_encode(Config::clientId() . ':' . Config::clientSecret()),
            'X-InSales-Access-Token: ' . (string)$token['access_token'],
        );

        $result = CrmClient::send(strtoupper($method), 'https://' . $this->shop . $path, (string)$rawBody, $headers);

        $this->recordServerRateLimit((array)($result['headers'] ?? array()), (int)$result['http_code']);

        $decoded = json_decode((string)$result['body'], true);

        return array(
            'success' => $result['success'],
            'http_code' => (int)$result['http_code'],
            'status' => (int)$result['http_code'],
            'error' => $result['success'] ? null : (string)$result['error'],
            'body' => (string)$result['body'],
            'data' => is_array($decoded) ? $decoded : null,
        );
    }

    /**
     * Records InSales' own rate-limit accounting.
     *
     * The platform reports `API-Usage-Limit: <used>/<limit>` on every response and
     * answers 429/503 with `Retry-After` once the quota (500 requests / 5 minutes)
     * is exhausted; the next calls then wait instead of hammering the API.
     *
     * @param array<string,string> $headers
     */
    private function recordServerRateLimit(array $headers, int $httpCode)
    {
        if ($headers === array() && $httpCode < 400) {
            return;
        }

        $file = Config::rateLimitFile();
        $state = $this->readState($file);
        $now = time();
        $changed = false;

        $usage = (string)($headers['api-usage-limit'] ?? '');
        if ($usage !== '' && strpos($usage, '/') !== false) {
            list($used, $limit) = array_map('trim', explode('/', $usage, 2));
            if (is_numeric($used) && is_numeric($limit)) {
                $state['api_usage_limit'] = array('used' => (int)$used, 'limit' => (int)$limit, 'at' => $now);
                $changed = true;
            }
        }

        if ($httpCode === 429 || $httpCode === 503) {
            $retryAfter = (int)($headers['retry-after'] ?? 0);
            if ($retryAfter <= 0) {
                $retryAfter = 60;
            }
            $state['blocked_until'] = $now + $retryAfter;
            $state['retry_after'] = $retryAfter;
            $changed = true;
        }

        if ($changed) {
            $this->writeState($file, $state);
        }
    }

    /**
     * @return array<string,mixed>
     */
    private function readState(string $file)
    {
        if (!is_file($file)) {
            return array();
        }

        $decoded = json_decode((string)@file_get_contents($file), true);

        return is_array($decoded) ? $decoded : array();
    }

    /**
     * @param array<string,mixed> $state
     */
    private function writeState(string $file, array $state)
    {
        $dir = dirname($file);
        if (!is_dir($dir)) {
            @mkdir($dir, 0770, true);
        }

        @file_put_contents($file, (string)json_encode($state), LOCK_EX);
    }

    /**
     * Token bucket persisted next to the token file.
     *
     * @return array
     */
    private function consumeRateLimit()
    {
        $file = Config::rateLimitFile();
        $state = $this->readState($file);

        list($allowed, $state) = RateLimiter::allow($state, Config::rateLimit(), Config::rateWindow());

        $this->writeState($file, $state);

        return array(
            'success' => $allowed,
            'retry_after' => isset($state['retry_after']) ? (int)$state['retry_after'] : 0,
        );
    }
}
