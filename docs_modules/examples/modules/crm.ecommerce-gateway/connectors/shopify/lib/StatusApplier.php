<?php

namespace Tropatt\Shopify;

/**
 * Executes a CRM stage change on the Shopify side.
 *
 * The README always promised "CRM stage -> fulfilment/cancel"; nothing actually
 * did it, so `StatusMapper`, `createFulfillment` and `orderCancel` were dead
 * code. `public/status.php` verifies the CRM packet and calls apply() here.
 *
 * The decision itself (which fulfilment orders and quantities to fulfil) is a
 * pure function so it is unit tested without touching Shopify.
 */
class StatusApplier
{
    /** @var array */
    private static $options = array(
        'notify_customer' => false,
        'cancel_refund' => false,
        'cancel_restock' => true,
        'cancel_reason' => 'OTHER',
    );

    /**
     * Turn a GraphQL order node into a `FulfillmentInput` list.
     *
     * Only fulfilment orders that are still actionable are used, and only line
     * items with a positive `remainingQuantity` — Shopify rejects an explicit
     * quantity larger than what is left.
     *
     * @param array $orderNode
     * @return array
     */
    public static function fulfillmentPlan(array $orderNode)
    {
        $fulfillmentOrders = $orderNode['fulfillmentOrders']['nodes'] ?? array();
        if (!is_array($fulfillmentOrders)) {
            return array();
        }

        $plan = array();

        foreach ($fulfillmentOrders as $fulfillmentOrder) {
            if (!is_array($fulfillmentOrder)) {
                continue;
            }

            $status = strtoupper(trim((string)($fulfillmentOrder['status'] ?? '')));
            if (!in_array($status, array('OPEN', 'IN_PROGRESS'), true)) {
                continue;
            }

            $fulfillmentOrderId = trim((string)($fulfillmentOrder['id'] ?? ''));
            if ($fulfillmentOrderId === '') {
                continue;
            }

            $lineItems = array();
            foreach ((array)($fulfillmentOrder['lineItems']['nodes'] ?? array()) as $lineItem) {
                if (!is_array($lineItem)) {
                    continue;
                }

                $lineItemId = trim((string)($lineItem['id'] ?? ''));
                $quantity = (int)($lineItem['remainingQuantity'] ?? 0);
                if ($lineItemId === '' || $quantity <= 0) {
                    continue;
                }

                $lineItems[] = array('id' => $lineItemId, 'quantity' => $quantity);
            }

            if ($lineItems === array()) {
                continue;
            }

            $plan[] = array(
                'fulfillmentOrderId' => $fulfillmentOrderId,
                'fulfillmentOrderLineItems' => $lineItems,
            );
        }

        return $plan;
    }

    /**
     * Resolve the Shopify action for an inbound CRM packet.
     *
     * `external_status` is preferred, but the core mapper may hand back the CRM
     * stage code itself (`done`), so anything that is not a known action falls
     * back to the connector's own `status_mapping`.
     *
     * @return string|null
     */
    public static function resolveAction($externalStatus, $crmStage, array $mapping)
    {
        $action = StatusMapper::normalizeAction($externalStatus);
        if ($action !== null) {
            return $action;
        }

        return StatusMapper::normalizeAction(StatusMapper::actionFor($mapping, $crmStage));
    }

    /**
     * @return array
     */
    public static function defaultOptions()
    {
        return self::$options;
    }

    /**
     * Apply one action to one order.
     *
     * @param string|null $action One of StatusMapper::allowedActions()
     * @param string $orderGid
     * @param ShopifyClient $client
     * @param array $options
     * @return array{applied:bool,action:?string,error:?string,detail:array}
     */
    public static function apply($action, $orderGid, ShopifyClient $client, array $options = array())
    {
        $options = array_merge(self::$options, $options);
        $action = $action === null ? null : strtolower(trim((string)$action));

        if ($action === null || $action === '' || $action === StatusMapper::ACTION_NOOP) {
            return self::result(false, $action, 'Ignored: no Shopify action for the stage', array());
        }

        if (!in_array($action, StatusMapper::allowedActions(), true)) {
            return self::result(false, $action, 'Unknown Shopify action: ' . $action, array());
        }

        $orderGid = trim((string)$orderGid);
        if ($orderGid === '') {
            return self::result(false, $action, 'Missing or invalid external_order_id', array());
        }

        switch ($action) {
            case StatusMapper::ACTION_FULFILL:
                return self::fulfill($orderGid, $client, $options);

            case StatusMapper::ACTION_CANCEL:
                $response = $client->cancelOrder(
                    $orderGid,
                    (string)$options['cancel_reason'],
                    (bool)$options['cancel_refund'],
                    (bool)$options['cancel_restock'],
                    (bool)$options['notify_customer']
                );

                return self::result(
                    (bool)$response['success'],
                    $action,
                    $response['success'] ? null : (string)$response['error'],
                    array('order_id' => $orderGid)
                );

            case StatusMapper::ACTION_CLOSE:
                $response = $client->closeOrder($orderGid);

                return self::result(
                    (bool)$response['success'],
                    $action,
                    $response['success'] ? null : (string)$response['error'],
                    array('order_id' => $orderGid)
                );
        }

        return self::result(false, $action, 'Unsupported Shopify action: ' . $action, array());
    }

    /**
     * Fulfil every actionable fulfilment order of an order.
     *
     * One mutation per fulfilment order: `fulfillmentCreate` requires all
     * fulfilment orders in a single call to share a location, and splitting them
     * keeps the connector correct when an order ships from several.
     *
     * @return array
     */
    private static function fulfill($orderGid, ShopifyClient $client, array $options)
    {
        $order = $client->order($orderGid);
        if (!$order['success']) {
            return self::result(false, StatusMapper::ACTION_FULFILL, (string)$order['error'], array('order_id' => $orderGid));
        }

        $node = isset($order['data']['order']) && is_array($order['data']['order']) ? $order['data']['order'] : null;
        if ($node === null) {
            return self::result(false, StatusMapper::ACTION_FULFILL, 'Order not found: ' . $orderGid, array('order_id' => $orderGid));
        }

        $plan = self::fulfillmentPlan($node);
        if ($plan === array()) {
            return self::result(false, StatusMapper::ACTION_FULFILL, 'Nothing left to fulfil for ' . $orderGid, array('order_id' => $orderGid));
        }

        $fulfilled = array();
        $lastIndex = count($plan) - 1;

        foreach ($plan as $index => $entry) {
            $response = $client->createFulfillment(
                array($entry),
                $index === $lastIndex && !empty($options['notify_customer'])
            );

            if (!$response['success']) {
                return self::result(false, StatusMapper::ACTION_FULFILL, (string)$response['error'], array(
                    'order_id' => $orderGid,
                    'fulfilled' => $fulfilled,
                ));
            }

            $fulfilled[] = (string)$entry['fulfillmentOrderId'];
        }

        return self::result(true, StatusMapper::ACTION_FULFILL, null, array(
            'order_id' => $orderGid,
            'fulfilled' => $fulfilled,
        ));
    }

    /**
     * @return array
     */
    private static function result($applied, $action, $error, array $detail)
    {
        return array(
            'applied' => (bool)$applied,
            'action' => $action,
            'error' => $error,
            'detail' => $detail,
        );
    }
}
