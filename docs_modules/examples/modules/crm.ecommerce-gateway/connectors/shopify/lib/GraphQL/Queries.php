<?php

namespace Tropatt\Shopify\GraphQL;

/**
 * GraphQL documents sent to the Admin API (kept pure so they are unit tested).
 *
 * The documents target a supported Admin API version (see Config::apiVersion()).
 * Two historical names must never come back:
 *   - `fulfillmentCreateV2` — deprecated in 2024-10, renamed to `fulfillmentCreate`
 *     ("Removing V2 suffix from fulfillmentCreateV2 and fulfillmentTrackingInfoUpdateV2");
 *   - `callbackUrl` on `WebhookSubscriptionInput` — replaced by `uri`.
 */
class Queries
{
    /**
     * Read one order with its line items and fulfilment orders.
     *
     * `remainingQuantity` is required to build a `FulfillmentInput`: the API
     * wants an explicit quantity per fulfilment order line item, so a query
     * without it cannot produce a valid fulfilment.
     *
     * @return string
     */
    public static function order($idVariable = '$id')
    {
        return 'query TropattOrder(' . ltrim($idVariable, '$') . ': ID!) {'
            . ' order(id: ' . $idVariable . ') {'
            . ' id name email currencyCode displayFinancialStatus displayFulfillmentStatus'
            . ' totalPriceSet { shopMoney { amount currencyCode } }'
            . ' subtotalPriceSet { shopMoney { amount currencyCode } }'
            . ' totalShippingPriceSet { shopMoney { amount currencyCode } }'
            . ' totalTaxSet { shopMoney { amount currencyCode } }'
            . ' customer { displayName email phone }'
            . ' shippingAddress { city address1 address2 zip countryCodeV2 }'
            . ' lineItems(first: 100) { nodes { title sku quantity originalUnitPriceSet { shopMoney { amount currencyCode } } variant { id } } }'
            . ' fulfillmentOrders(first: 20) { nodes { id status'
            . ' lineItems(first: 100) { nodes { id remainingQuantity } } } }'
            . ' } }';
    }

    /**
     * Update available quantities for inventory items (Leaky Bucket aware).
     *
     * @return string
     */
    public static function inventorySetQuantities()
    {
        return 'mutation TropattInventorySet($input: InventorySetQuantitiesInput!) {'
            . ' inventorySetQuantities(input: $input) {'
            . ' inventoryAdjustmentGroup { createdAt reason changes { name delta quantityAfterChange } }'
            . ' userErrors { field message } } }';
    }

    /**
     * Create a fulfilment from one or more fulfilment orders.
     *
     * @return string
     */
    public static function fulfillmentCreate()
    {
        return 'mutation TropattFulfillmentCreate($fulfillment: FulfillmentInput!) {'
            . ' fulfillmentCreate(fulfillment: $fulfillment) {'
            . ' fulfillment { id status }'
            . ' userErrors { field message } } }';
    }

    /**
     * Cancel an order.
     *
     * @return string
     */
    public static function orderCancel()
    {
        return 'mutation TropattOrderCancel($orderId: ID!, $reason: OrderCancelReason!, $refund: Boolean!, $restock: Boolean!, $notifyCustomer: Boolean!) {'
            . ' orderCancel(orderId: $orderId, reason: $reason, refund: $refund, restock: $restock, notifyCustomer: $notifyCustomer) {'
            . ' job { id } orderCancelUserErrors { field message } } }';
    }

    /**
     * Close an order (archives it as handled).
     *
     * @return string
     */
    public static function orderClose()
    {
        return 'mutation TropattOrderClose($input: OrderCloseInput!) {'
            . ' orderClose(input: $input) {'
            . ' order { id closed closedAt }'
            . ' userErrors { field message } } }';
    }

    /**
     * Register the webhook subscriptions an app needs.
     *
     * @return string
     */
    public static function webhookSubscriptionCreate()
    {
        return 'mutation TropattWebhookCreate($topic: WebhookSubscriptionTopic!, $webhookSubscription: WebhookSubscriptionInput!) {'
            . ' webhookSubscriptionCreate(topic: $topic, webhookSubscription: $webhookSubscription) {'
            . ' webhookSubscription { id topic uri }'
            . ' userErrors { field message } } }';
    }

    /**
     * Topics the connector subscribes to (GDPR ones are mandatory for apps).
     *
     * @return array
     */
    public static function topics()
    {
        return array(
            'ORDERS_CREATE',
            'ORDERS_UPDATED',
            'ORDERS_CANCELLED',
            'APP_UNINSTALLED',
            'CUSTOMERS_DATA_REQUEST',
            'CUSTOMERS_REDACT',
            'SHOP_REDACT',
        );
    }
}
