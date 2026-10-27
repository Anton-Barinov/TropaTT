<?php

/**
 * Shop-Script order -> canonical TropaTT E-COM-01 payload.
 *
 * `loadOrder()` reads the order, its items and the customer through
 * `shopOrderModel` / `waContact` and pulls custom parameters and UTM marks from
 * `shop_order_params`; `toCanonical()` is pure PHP and unit tested offline.
 */
class shopTropattOrderMapper
{
    /**
     * @param int $orderId
     * @return array
     */
    public static function loadOrder($orderId)
    {
        $orderModel = new shopOrderModel();
        $order = $orderModel->getOrder((int)$orderId);

        if (!$order) {
            return array();
        }

        $items = array();
        foreach ((array)($order['items'] ?? array()) as $item) {
            $items[] = array(
                'name' => (string)($item['name'] ?? ''),
                'sku' => (string)($item['sku'] ?? ($item['product_id'] ?? '')),
                'quantity' => (float)($item['quantity'] ?? 1),
                'price_minor' => self::toMinor($item['price'] ?? 0),
                'line_total_minor' => self::toMinor($item['total'] ?? ($item['price'] ?? 0)),
                'options' => self::itemOptions($item),
            );
        }

        $contact = null;
        if (!empty($order['contact_id'])) {
            $contact = new waContact((int)$order['contact_id']);
        }

        $params = array();
        if (class_exists('shopOrderParamsModel')) {
            $params = (new shopOrderParamsModel())->get($orderId);
        }

        return array(
            'id' => (int)$order['id'],
            'number' => (string)($order['number'] ?? $order['id']),
            'state_id' => (string)($order['state_id'] ?? ($order['status'] ?? '')),
            'currency' => (string)($order['currency'] ?? 'RUB'),
            'total_minor' => self::toMinor($order['total'] ?? 0),
            'subtotal_minor' => self::toMinor($order['subtotal'] ?? 0),
            'shipping_minor' => self::toMinor($order['shipping'] ?? 0),
            'paid' => !empty($order['paid_date']) || (string)($order['paid'] ?? '') !== '',
            'items' => $items,
            'customer' => array(
                'full_name' => $contact ? trim((string)$contact->getName()) : (string)($order['contact_name'] ?? ''),
                'phone' => $contact ? (string)$contact->get('phone', 'default') : '',
                'email' => $contact ? (string)$contact->get('email', 'default') : '',
            ),
            'shipping_method' => (string)($order['shipping_name'] ?? ($params['shipping_name'] ?? '')),
            'payment_method' => (string)($order['payment_name'] ?? ($params['payment_name'] ?? '')),
            'params' => is_array($params) ? $params : array(),
            'comment' => (string)($order['comment'] ?? ($params['comment'] ?? '')),
        );
    }

    /**
     * @return array
     */
    public static function toCanonical(array $order, $statusCode = 'new')
    {
        $currency = self::currency($order);
        $items = array();

        foreach ((array)($order['items'] ?? array()) as $item) {
            $quantity = isset($item['quantity']) ? (float)$item['quantity'] : 1.0;
            $priceMinor = isset($item['price_minor']) ? (int)$item['price_minor'] : self::toMinor($item['price'] ?? 0);
            $lineMinor = isset($item['line_total_minor']) ? (int)$item['line_total_minor'] : (int)round($priceMinor * $quantity);

            $entry = array(
                'name' => (string)($item['name'] ?? ''),
                'sku' => (string)($item['sku'] ?? ''),
                'quantity' => $quantity,
                'price' => array('amount_minor' => $priceMinor, 'currency' => $currency),
                'line_total' => array('amount_minor' => $lineMinor, 'currency' => $currency),
            );

            if (!empty($item['options'])) {
                $entry['options'] = $item['options'];
            }

            $items[] = $entry;
        }

        $params = (array)($order['params'] ?? array());
        $customer = (array)($order['customer'] ?? array());

        $customFields = array(
            'webasyst_order_id' => (string)($order['id'] ?? ''),
            'webasyst_number' => (string)($order['number'] ?? ''),
            'webasyst_state' => (string)($order['state_id'] ?? ''),
            'webasyst_payment' => (string)($order['payment_method'] ?? ''),
            'webasyst_shipping' => (string)($order['shipping_method'] ?? ''),
            'webasyst_comment' => (string)($order['comment'] ?? ''),
        );

        foreach ($params as $name => $value) {
            if (!is_scalar($value) || trim((string)$value) === '') {
                continue;
            }

            $key = preg_replace('/[^\p{L}\p{N}_]+/u', '_', (string)$name);
            $customFields['webasyst_param_' . $key] = substr((string)$value, 0, 255);
        }

        $subtotalMinor = isset($order['subtotal_minor']) ? (int)$order['subtotal_minor'] : self::sumItems($items);
        $shippingMinor = isset($order['shipping_minor']) ? (int)$order['shipping_minor'] : 0;
        $totalMinor = isset($order['total_minor']) ? (int)$order['total_minor'] : $subtotalMinor + $shippingMinor;

        return array(
            'external_id' => (string)($order['id'] ?? ''),
            'payload' => array(
                'order_number' => (string)($order['number'] ?? ($order['id'] ?? '')),
                'order_status' => (string)$statusCode,
                'items' => $items,
                'subtotal' => array('amount_minor' => $subtotalMinor, 'currency' => $currency),
                'delivery_total' => array('amount_minor' => $shippingMinor, 'currency' => $currency),
                'total' => array('amount_minor' => $totalMinor, 'currency' => $currency),
                'paid' => !empty($order['paid']),
                'customer' => array(
                    'full_name' => (string)($customer['full_name'] ?? ''),
                    'phone' => (string)($customer['phone'] ?? ''),
                    'email' => (string)($customer['email'] ?? ''),
                ),
                'delivery_method' => (string)($order['shipping_method'] ?? ''),
                'delivery_address' => array(
                    'city' => (string)($params['shipping_city'] ?? ''),
                    'street' => (string)($params['shipping_address'] ?? ''),
                    'postal_code' => (string)($params['shipping_zip'] ?? ''),
                    'country' => (string)($params['shipping_country'] ?? ''),
                ),
                'payment_method' => (string)($order['payment_method'] ?? ''),
                'custom_fields' => $customFields,
            ),
        );
    }

    /**
     * @return array
     */
    private static function itemOptions(array $item)
    {
        $result = array();

        foreach ((array)($item['sku_options'] ?? ($item['options'] ?? array())) as $name => $value) {
            if (is_array($value)) {
                $value = implode(', ', array_map('strval', $value));
            }

            if (!is_scalar($value) || trim((string)$value) === '') {
                continue;
            }

            $result[] = array('name' => (string)$name, 'value' => (string)$value);
        }

        return $result;
    }

    /**
     * @return int
     */
    private static function sumItems(array $items)
    {
        $sum = 0;
        foreach ($items as $item) {
            $sum += (int)$item['line_total']['amount_minor'];
        }

        return $sum;
    }

    /**
     * @return string
     */
    private static function currency(array $order)
    {
        $currency = (string)($order['currency'] ?? '');

        return $currency === '' ? 'RUB' : strtoupper($currency);
    }

    /**
     * @return int
     */
    private static function toMinor($amount)
    {
        if (is_array($amount) || is_object($amount)) {
            return 0;
        }

        return (int)round(((float)str_replace(array(' ', ','), array('', '.'), (string)$amount)) * 100);
    }
}
