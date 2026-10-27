<?php

namespace Tropatt\Tilda;

/**
 * Tilda Cart Webhook -> canonical TropaTT E-COM-01 payload.
 *
 * Tilda sends decimal strings in major currency units ("1990.00", "1"), so every
 * amount is converted to integer minor units. Analytics cookies and the free-form
 * `data` block travel in `custom_fields`, and the order id is `orderid` when
 * present (falling back to `tranid`/`invoiceid`).
 */
class TildaMapper
{
    /**
     * @return array
     */
    public static function toCanonical(array $cart, $statusCode = 'new')
    {
        $currency = self::currency($cart);
        $items = array();

        foreach (self::products($cart) as $product) {
            $quantity = self::toFloat($product['quantity'] ?? 1);
            $priceMinor = self::toMinor($product['price'] ?? 0);
            $amountMinor = isset($product['amount']) && $product['amount'] !== ''
                ? self::toMinor($product['amount'])
                : (int)round($priceMinor * $quantity);

            $entry = array(
                'name' => (string)($product['name'] ?? ''),
                'sku' => (string)($product['sku'] ?? ($product['name'] ?? '')),
                'quantity' => $quantity,
                'price' => array('amount_minor' => $priceMinor, 'currency' => $currency),
                'line_total' => array('amount_minor' => $amountMinor, 'currency' => $currency),
            );

            $options = self::options($product);
            if ($options !== array()) {
                $entry['options'] = $options;
            }

            $items[] = $entry;
        }

        $delivery = isset($cart['delivery']) && is_array($cart['delivery']) ? $cart['delivery'] : array();
        $deliveryMinor = self::toMinor($delivery['price'] ?? 0);

        $subtotalMinor = isset($cart['subtotal']) && $cart['subtotal'] !== ''
            ? self::toMinor($cart['subtotal'])
            : self::sumItems($items);

        $totalMinor = isset($cart['total']) && $cart['total'] !== ''
            ? self::toMinor($cart['total'])
            : $subtotalMinor + $deliveryMinor;

        $customFields = array(
            'tilda_formid' => (string)($cart['formid'] ?? ''),
            'tilda_tranid' => (string)($cart['tranid'] ?? ''),
            'tilda_invoiceid' => (string)($cart['invoiceid'] ?? ''),
            'tilda_payment_system' => (string)($cart['payment']['sys'] ?? ''),
            'tilda_promocode' => (string)($cart['promocode'] ?? ''),
            'tilda_comment' => (string)($cart['comment'] ?? ''),
        );

        foreach (self::cookies($cart) as $name => $value) {
            $customFields['analytics_' . $name] = $value;
        }

        if (!empty($cart['data']) && is_array($cart['data'])) {
            foreach ($cart['data'] as $name => $value) {
                if (is_scalar($value)) {
                    $customFields['tilda_data_' . (string)$name] = (string)$value;
                }
            }
        }

        return array(
            'external_id' => self::externalId($cart),
            'payload' => array(
                'order_number' => self::externalId($cart),
                'order_status' => (string)$statusCode,
                'items' => $items,
                'subtotal' => array('amount_minor' => $subtotalMinor, 'currency' => $currency),
                'delivery_total' => array('amount_minor' => $deliveryMinor, 'currency' => $currency),
                'total' => array('amount_minor' => $totalMinor, 'currency' => $currency),
                'paid' => self::isPaid($cart),
                'customer' => array(
                    'full_name' => (string)($cart['name'] ?? ''),
                    'phone' => (string)($cart['phone'] ?? ''),
                    'email' => (string)($cart['email'] ?? ''),
                ),
                'delivery_method' => (string)($delivery['name'] ?? ''),
                'delivery_address' => array(
                    'city' => (string)($cart['city'] ?? ''),
                    'street' => (string)($cart['address'] ?? ''),
                    'postal_code' => (string)($cart['zip'] ?? ''),
                    'country' => (string)($cart['country'] ?? ''),
                ),
                'payment_method' => (string)($cart['payment']['sys'] ?? ''),
                'custom_fields' => $customFields,
            ),
        );
    }

    /**
     * @return string
     */
    public static function externalId(array $cart)
    {
        foreach (array('orderid', 'tranid', 'invoiceid') as $key) {
            if (!empty($cart[$key])) {
                return (string)$cart[$key];
            }
        }

        return 'tilda-' . substr(md5(json_encode($cart)), 0, 12);
    }

    /**
     * @return bool
     */
    public static function isPaid(array $cart)
    {
        $payment = isset($cart['payment']) && is_array($cart['payment']) ? $cart['payment'] : array();
        $status = strtolower((string)($payment['status'] ?? ($cart['payment_status'] ?? '')));

        return in_array($status, array('paid', 'success', 'succeeded', 'оплачен'), true);
    }

    /**
     * Tilda sends products as a list; a single-product cart may arrive flat.
     *
     * @return array
     */
    private static function products(array $cart)
    {
        if (isset($cart['products']) && is_array($cart['products'])) {
            return $cart['products'];
        }

        if (isset($cart['product']) && is_array($cart['product'])) {
            return array($cart['product']);
        }

        return array();
    }

    /**
     * @return array
     */
    private static function options(array $product)
    {
        $result = array();

        if (empty($product['options']) || !is_array($product['options'])) {
            return $result;
        }

        foreach ($product['options'] as $option) {
            if (!is_array($option)) {
                continue;
            }

            $name = (string)($option['option'] ?? '');
            $value = (string)($option['variant'] ?? '');
            if ($name === '' && $value === '') {
                continue;
            }

            $result[] = array('name' => $name, 'value' => $value);
        }

        return $result;
    }

    /**
     * @return array
     */
    private static function cookies(array $cart)
    {
        $result = array();

        if (empty($cart['cookies']) || !is_array($cart['cookies'])) {
            return $result;
        }

        foreach ($cart['cookies'] as $name => $value) {
            if (!is_scalar($value)) {
                continue;
            }

            $name = preg_replace('/[^a-z0-9_]/i', '_', (string)$name);
            $result[$name] = substr((string)$value, 0, 255);
        }

        return $result;
    }

    /**
     * @return string
     */
    private static function currency(array $cart)
    {
        $currency = (string)($cart['currency'] ?? '');

        return $currency === '' ? 'RUB' : strtoupper($currency);
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
}
