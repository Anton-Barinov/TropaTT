<?php

namespace Tropatt\Shopify;

/**
 * Shopify Admin GraphQL client with cost-aware throttling.
 *
 * Shopify answers with `extensions.cost.throttleStatus`; the client waits for
 * the leaky bucket to refill instead of hammering the API.
 */
class ShopifyClient
{
    const THROTTLE_FLOOR = 100.0;

    /** @var string */
    private $shop;
    /** @var string */
    private $accessToken;
    /** @var string */
    private $apiVersion;

    public function __construct($shop = null, $accessToken = null, $apiVersion = null)
    {
        $token = TokenStore::load();
        $this->shop = ShopifySignature::normalizeShop($shop === null ? ($token['shop'] ?? '') : $shop);
        $this->accessToken = $accessToken === null ? (string)($token['access_token'] ?? '') : (string)$accessToken;
        $this->apiVersion = $apiVersion === null ? Config::apiVersion() : (string)$apiVersion;
    }

    /**
     * @return string
     */
    public function endpoint()
    {
        return 'https://' . $this->shop . '/admin/api/' . $this->apiVersion . '/graphql.json';
    }

    /**
     * @return string
     */
    public function apiVersion()
    {
        return $this->apiVersion;
    }

    /**
     * Seconds to wait before the next call, based on the leaky bucket status.
     *
     * @return float
     */
    public static function waitSeconds(array $cost, $required = 0.0)
    {
        $status = isset($cost['throttleStatus']) ? (array)$cost['throttleStatus'] : array();
        $available = isset($status['currentlyAvailable']) ? (float)$status['currentlyAvailable'] : null;
        $restoreRate = isset($status['restoreRate']) ? (float)$status['restoreRate'] : 0.0;

        if ($available === null) {
            return 0.0;
        }

        $needed = max(0.0, (float)$required - $available);
        if ($needed <= 0.0) {
            return 0.0;
        }

        if ($restoreRate <= 0.0) {
            return 1.0;
        }

        return (float)ceil(($needed / $restoreRate) * 100) / 100;
    }

    /**
     * Send one GraphQL document.
     *
     * @return array
     */
    public function query($query, array $variables = array())
    {
        if ($this->accessToken === '' || !ShopifySignature::isValidShop($this->shop)) {
            return array('success' => false, 'error' => 'The app is not installed: no token for ' . $this->shop, 'data' => null, 'cost' => array());
        }

        $body = (string)json_encode(array('query' => $query, 'variables' => $variables), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        // Argument order matters: CrmClient::send() is
        // send($method, $url, $rawBody, array $headers). Passing the endpoint
        // first sent every request to a host literally named "POST".
        $result = CrmClient::send('POST', $this->endpoint(), $body, array(
            'Content-Type: application/json',
            'Accept: application/json',
            'X-Shopify-Access-Token: ' . $this->accessToken,
        ));

        if (!$result['success']) {
            return array('success' => false, 'error' => $result['error'], 'data' => null, 'cost' => array());
        }

        $this->warnOnVersionFallback(isset($result['headers']) ? (array)$result['headers'] : array());

        $decoded = json_decode((string)$result['body'], true);
        $data = is_array($decoded) ? ($decoded['data'] ?? null) : null;
        $errors = is_array($decoded) ? ($decoded['errors'] ?? null) : null;
        $cost = is_array($decoded) && isset($decoded['extensions']['cost'])
            ? (array)$decoded['extensions']['cost']
            : array();

        if ($errors !== null) {
            return array('success' => false, 'error' => (string)json_encode($errors, JSON_UNESCAPED_UNICODE), 'data' => $data, 'cost' => $cost);
        }

        if (is_array($data) && $this->hasUserErrors($data)) {
            return array('success' => false, 'error' => (string)$this->userErrorsMessage($data), 'data' => $data, 'cost' => $cost);
        }

        return array('success' => true, 'error' => null, 'data' => $data, 'cost' => $cost);
    }

    /**
     * Shopify serves a request whose version is retired with the oldest
     * supported version and reports it in `X-Shopify-API-Version`. Without this
     * check a stale `shopify_api_version` keeps "working" silently, so nobody
     * notices that the pinned version is gone.
     *
     * @param array<string,string> $headers
     * @return void
     */
    private function warnOnVersionFallback(array $headers)
    {
        $served = isset($headers['x-shopify-api-version']) ? trim((string)$headers['x-shopify-api-version']) : '';

        if ($served === '' || strcasecmp($served, $this->apiVersion) === 0) {
            return;
        }

        ConnectorLogger::write('shopify api version fallback', array(
            'requested' => $this->apiVersion,
            'served' => $served,
            'hint' => 'Shopify no longer serves the pinned version; bump shopify_api_version',
        ));
    }

    /**
     * @return bool
     */
    private function hasUserErrors(array $data)
    {
        return $this->userErrorsMessage($data) !== '';
    }

    /**
     * Flatten `userErrors` of whichever mutation the payload carries.
     *
     * @return string
     */
    private function userErrorsMessage(array $data)
    {
        foreach ($data as $payload) {
            if (!is_array($payload) || !isset($payload['userErrors']) || !is_array($payload['userErrors'])) {
                continue;
            }

            $messages = array();
            foreach ($payload['userErrors'] as $error) {
                if (is_array($error) && isset($error['message'])) {
                    $field = isset($error['field']) && is_array($error['field']) ? implode('.', $error['field']) : '';
                    $messages[] = ($field !== '' ? $field . ': ' : '') . (string)$error['message'];
                }
            }

            if ($messages !== array()) {
                return implode('; ', $messages);
            }
        }

        return '';
    }

    /**
     * Fetch one order by its GID (used by the CRM→Shopify status receiver).
     *
     * @return array
     */
    public function order($orderGid)
    {
        return $this->query(GraphQL\Queries::order(), array('id' => (string)$orderGid));
    }

    /**
     * @return array
     */
    public function setInventoryQuantities(array $quantities, $locationId = null, $idempotencyKey = null)
    {
        $location = $locationId === null ? Config::stockLocationId() : (string)$locationId;
        $mutation = GraphQL\Queries::inventorySetQuantities();

        return $this->query($mutation, array(
            'input' => array(
                'reason' => 'correction',
                'name' => 'available',
                'referenceDocumentUri' => 'tropatt://' . ($idempotencyKey === null ? 'stock-sync' : (string)$idempotencyKey),
                'quantities' => array_map(static function (array $row) use ($location) {
                    return array(
                        'inventoryItemId' => (string)$row['inventory_item_id'],
                        'locationId' => (string)$location,
                        'quantity' => (int)$row['quantity'],
                    );
                }, $quantities),
            ),
        ));
    }

    /**
     * Create a fulfilment (non-deprecated `fulfillmentCreate`).
     *
     * @param array $lineItemsByFulfillmentOrder
     * @return array
     */
    public function createFulfillment(array $lineItemsByFulfillmentOrder, $notifyCustomer = false)
    {
        return $this->query(GraphQL\Queries::fulfillmentCreate(), array(
            'fulfillment' => array(
                'lineItemsByFulfillmentOrder' => $lineItemsByFulfillmentOrder,
                'notifyCustomer' => (bool)$notifyCustomer,
            ),
        ));
    }

    /**
     * @return array
     */
    public function cancelOrder($orderGid, $reason = 'OTHER', $refund = false, $restock = true, $notifyCustomer = false)
    {
        return $this->query(GraphQL\Queries::orderCancel(), array(
            'orderId' => (string)$orderGid,
            'reason' => (string)$reason,
            'refund' => (bool)$refund,
            'restock' => (bool)$restock,
            'notifyCustomer' => (bool)$notifyCustomer,
        ));
    }

    /**
     * @return array
     */
    public function closeOrder($orderGid)
    {
        return $this->query(GraphQL\Queries::orderClose(), array(
            'input' => array('id' => (string)$orderGid),
        ));
    }
}
