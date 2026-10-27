<?php

namespace Tropatt\Shopify;

/**
 * Shopify order (webhook payload or GraphQL node) -> canonical TropaTT payload.
 */
class OrderMapper
{
    /**
     * @return array
     */
    public static function toCanonical(array $order, $statusCode = 'new')
    {
        $currency = strtoupper((string)($order['currency'] ?? ($order['currencyCode'] ?? 'USD')));
        $items = array();

        foreach (self::lineItems($order) as $line) {
            $quantity = self::toFloat($line['quantity'] ?? 1);
            $priceMinor = self::toMinor($line['price'] ?? 0);
            $lineMinor = isset($line['line_total']) && $line['line_total'] !== ''
                ? self::toMinor($line['line_total'])
                : (int)round($priceMinor * $quantity);
            // Multi-currency orders carry both currencies: `shopMoney` is the
            // settlement currency of the store (the accounting source of truth),
            // `presentmentMoney` is what the buyer saw. Prefer shopMoney and
            // keep the currency that actually belongs to the chosen amount.
            $lineCurrency = $currency;
            if (isset($line['currency']) && $line['currency'] !== '') {
                $lineCurrency = strtoupper((string)$line['currency']);
            } elseif (isset($line['originalUnitPriceSet']['shopMoney']['currencyCode'])) {
                $lineCurrency = strtoupper((string)$line['originalUnitPriceSet']['shopMoney']['currencyCode']);
            } elseif (isset($line['originalUnitPriceSet']['presentmentMoney']['currencyCode'])) {
                $lineCurrency = strtoupper((string)$line['originalUnitPriceSet']['presentmentMoney']['currencyCode']);
            }

            $entry = array(
                'name' => (string)($line['title'] ?? ($line['name'] ?? '')),
                'sku' => self::sku($line),
                'quantity' => $quantity,
                'price' => array('amount_minor' => $priceMinor, 'currency' => $lineCurrency),
                'line_total' => array('amount_minor' => $lineMinor, 'currency' => $lineCurrency),
            );

            if (!empty($line['variant_id'])) {
                $entry['options'] = array(array('name' => 'variant_id', 'value' => (string)$line['variant_id']));
            }

            $items[] = $entry;
        }

        $shipping = (array)($order['shipping_address'] ?? ($order['shippingAddress'] ?? array()));
        $customer = (array)($order['customer'] ?? array());

        $customFields = array(
            'shopify_order_id' => (string)($order['id'] ?? ''),
            'shopify_name' => (string)($order['name'] ?? ''),
            'shopify_financial_status' => (string)($order['financial_status'] ?? ($order['displayFinancialStatus'] ?? '')),
            'shopify_fulfillment_status' => (string)($order['fulfillment_status'] ?? ($order['displayFulfillmentStatus'] ?? '')),
            'shopify_tags' => is_array($order['tags'] ?? null) ? implode(', ', $order['tags']) : (string)($order['tags'] ?? ''),
            'shopify_note' => (string)($order['note'] ?? ''),
            'shopify_shop_domain' => (string)($order['shop_domain'] ?? ''),
        );

        foreach ((array)($order['note_attributes'] ?? array()) as $attribute) {
            if (!is_array($attribute) || empty($attribute['name'])) {
                continue;
            }
            $name = preg_replace('/[^\p{L}\p{N}_]+/u', '_', (string)$attribute['name']);
            $customFields['shopify_attr_' . $name] = substr((string)($attribute['value'] ?? ''), 0, 255);
        }

        $subtotalMinor = self::subtotalMinor($order, $items);
        // Absent shipping set means free delivery, not "unknown": the canonical
        // contract requires an integer amount_minor for delivery_total.
        $shippingMinor = self::moneyAmount($order, 'shipping', 'totalShippingPriceSet');
        if ($shippingMinor === null) {
            $shippingMinor = 0;
        }
        $totalMinor = self::moneyAmount($order, 'total', 'totalPriceSet');
        if ($totalMinor === null) {
            $totalMinor = $subtotalMinor + $shippingMinor;
        }

        return array(
            'external_id' => self::externalId($order),
            'payload' => array(
                'order_number' => (string)($order['name'] ?? ($order['order_number'] ?? ($order['id'] ?? ''))),
                'order_status' => (string)$statusCode,
                'items' => $items,
                'subtotal' => array('amount_minor' => $subtotalMinor, 'currency' => $currency),
                'delivery_total' => array('amount_minor' => $shippingMinor, 'currency' => $currency),
                'total' => array('amount_minor' => $totalMinor, 'currency' => $currency),
                'paid' => self::isPaid($order),
                'customer' => array(
                    'full_name' => (string)($customer['displayName'] ?? ($customer['display_name'] ?? ($order['customer_name'] ?? ''))),
                    'phone' => (string)($customer['phone'] ?? ($order['phone'] ?? '')),
                    'email' => (string)($order['email'] ?? ($customer['email'] ?? '')),
                ),
                'delivery_method' => (string)($order['shipping_method'] ?? ''),
                'delivery_address' => array(
                    'city' => (string)($shipping['city'] ?? ''),
                    'street' => trim((string)($shipping['address1'] ?? '') . ' ' . (string)($shipping['address2'] ?? '')),
                    'postal_code' => (string)($shipping['zip'] ?? ''),
                    'country' => (string)($shipping['countryCodeV2'] ?? ($shipping['country_code'] ?? '')),
                ),
                'payment_method' => (string)($order['payment_method'] ?? ($order['gateway'] ?? '')),
                'custom_fields' => $customFields,
            ),
        );
    }

    /**
     * @return array
     */
    public static function lineItems(array $order)
    {
        if (!empty($order['line_items']) && is_array($order['line_items'])) {
            return $order['line_items'];
        }

        if (!empty($order['lineItems']['nodes']) && is_array($order['lineItems']['nodes'])) {
            $lines = array();
            foreach ($order['lineItems']['nodes'] as $node) {
                $money = (array)($node['originalUnitPriceSet']['shopMoney'] ?? array());
                if ($money === array()) {
                    $money = (array)($node['originalUnitPriceSet']['presentmentMoney'] ?? array());
                }
                $lines[] = array(
                    'title' => (string)($node['title'] ?? ''),
                    'sku' => (string)($node['sku'] ?? ''),
                    'quantity' => $node['quantity'] ?? 1,
                    'price' => $money['amount'] ?? 0,
                    'currency' => (string)($money['currencyCode'] ?? ''),
                    'variant_id' => (string)($node['variant']['id'] ?? ''),
                );
            }

            return $lines;
        }

        return array();
    }

    /**
     * @return string
     */
    public static function externalId(array $order)
    {
        if (!empty($order['id'])) {
            return (string)$order['id'];
        }

        if (!empty($order['name'])) {
            return (string)$order['name'];
        }

        return 'shopify-' . substr(md5(json_encode($order)), 0, 12);
    }

    /**
     * @return bool
     */
    public static function isPaid(array $order)
    {
        $status = strtolower((string)($order['financial_status'] ?? ($order['displayFinancialStatus'] ?? '')));

        return in_array($status, array('paid', 'partially_paid'), true);
    }

    /**
     * @return string
     */
    private static function sku(array $line)
    {
        foreach (array('sku', 'variant_id') as $key) {
            if (!empty($line[$key])) {
                return (string)$line[$key];
            }
        }

        return (string)($line['title'] ?? '');
    }

    /**
     * Amount from a flat REST field or from a GraphQL `*Set { shopMoney { amount } }`.
     *
     * @return int|null
     */
    private static function moneyAmount(array $order, $field, $setField)
    {
        if (isset($order[$field]) && $order[$field] !== '') {
            return self::toMinor($order[$field]);
        }

        if (isset($order[$setField]['shopMoney']['amount'])) {
            return self::toMinor($order[$setField]['shopMoney']['amount']);
        }

        if (isset($order[$setField]['presentmentMoney']['amount'])) {
            return self::toMinor($order[$setField]['presentmentMoney']['amount']);
        }

        return null;
    }

    /**
     * @return int
     */
    private static function subtotalMinor(array $order, array $items)
    {
        $subtotal = self::moneyAmount($order, 'subtotal', 'subtotalPriceSet');
        if ($subtotal !== null) {
            return $subtotal;
        }

        $sum = 0;
        foreach ($items as $item) {
            $sum += (int)$item['line_total']['amount_minor'];
        }

        return $sum;
    }

    /**
     * @return int
     */
    private static function toMinor($amount)
    {
        return (int)round(self::toFloat($amount) * 100);
    }

    /**
     * @return float
     */
    private static function toFloat($value)
    {
        if (is_array($value) || is_object($value)) {
            return 0.0;
        }

        return (float)str_replace(array(' ', ','), array('', '.'), (string)$value);
    }
}
