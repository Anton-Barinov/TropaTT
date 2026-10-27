<?php

namespace Tropatt\Tilda;

/**
 * Tilda Store API client: pushes prices and stock.
 *
 *   POST https://store.tildaapi.com/api/v1/store/products/update
 *   Authorization: Bearer <TILDA_STORE_API_KEY>
 *   {"products": [{"sku": "ART-1", "quantity": 5, "price": 1990}]}
 */
class StoreApiClient
{
    const ENDPOINT = 'https://store.tildaapi.com/api/v1/store/products/update';
    const BATCH_SIZE = 100;

    /** @var string */
    private $apiKey;

    /** @var string */
    private $endpoint;

    public function __construct($apiKey = null, $endpoint = null)
    {
        $this->apiKey = $apiKey === null ? Config::tildaStoreApiKey() : (string)$apiKey;
        $this->endpoint = $endpoint === null ? self::ENDPOINT : (string)$endpoint;
    }

    /**
     * Build the request body for one batch (pure, unit-testable).
     *
     * @return array
     */
    public static function buildRequest(array $products)
    {
        $payload = array();

        foreach ($products as $product) {
            $entry = array();

            // Tilda identifies a product by sku or external_id, so a row with
            // neither cannot be updated and is skipped.
            if (isset($product['sku']) && $product['sku'] !== '') {
                $entry['sku'] = (string)$product['sku'];
            }

            if (isset($product['external_id']) && $product['external_id'] !== '') {
                $entry['external_id'] = (string)$product['external_id'];
            }

            if ($entry === array()) {
                continue;
            }

            if (array_key_exists('quantity', $product)) {
                $entry['quantity'] = max(0, (int)$product['quantity']);
            }

            if (array_key_exists('price', $product) && $product['price'] !== null && $product['price'] !== '') {
                $entry['price'] = round((float)$product['price'], 2);
            }

            $payload[] = $entry;
        }

        return array('products' => $payload);
    }

    /**
     * CRM stock rows (as accepted by the gateway) -> Tilda products.
     *
     * @return array
     */
    public static function mapStockRows(array $rows)
    {
        $products = array();

        foreach ($rows as $row) {
            if (!is_array($row) || empty($row['sku'])) {
                continue;
            }

            $product = array(
                'sku' => (string)$row['sku'],
                'quantity' => isset($row['quantity']) ? (int)$row['quantity'] : 0,
            );

            if (isset($row['price_minor']) && $row['price_minor'] !== null) {
                $product['price'] = (float)(((int)$row['price_minor']) / 100);
            }

            $products[] = $product;
        }

        return $products;
    }

    /**
     * @return bool
     */
    public function isConfigured()
    {
        return $this->apiKey !== '';
    }

    /**
     * @return array
     */
    public function pushProducts(array $products)
    {
        if (!$this->isConfigured()) {
            return array('success' => false, 'sent' => 0, 'error' => 'Tilda Store API key is not configured');
        }

        $sent = 0;
        $errors = array();

        foreach (array_chunk($products, self::BATCH_SIZE) as $batch) {
            $body = json_encode(self::buildRequest($batch), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $result = CrmClient::send('POST', $this->endpoint, (string)$body, array(
                'Content-Type: application/json',
                'Accept: application/json',
                'Authorization: Bearer ' . $this->apiKey,
            ));

            if ($result['success']) {
                $sent += count($batch);

                continue;
            }

            $errors[] = (string)$result['error'];
        }

        return array('success' => $errors === array(), 'sent' => $sent, 'error' => $errors === array() ? null : implode('; ', $errors));
    }
}
