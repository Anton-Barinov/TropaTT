<?php

namespace Tropatt\InSales;

/**
 * InSales order JSON -> canonical TropaTT E-COM-01 payload.
 *
 * InSales keeps positions in `order_lines` (with `sale_price`/`price`,
 * `quantity`, `variant_id` and `fields_values` for custom fields), the customer
 * in `client`, the address in `delivery_address` and the money in `total_price`
 * / `discounts`.
 */
class InSalesMapper
{
    /**
     * @return array
     */
    public static function toCanonical(array $order, $statusCode = 'new')
    {
        $currency = self::currency($order);
        $items = array();

        foreach (self::lines($order) as $line) {
            $quantity = self::toFloat($line['quantity'] ?? 1);
            $priceMinor = self::toMinor($line['sale_price'] ?? ($line['price'] ?? 0));
            $lineTotal = isset($line['total_price']) && $line['total_price'] !== ''
                ? self::toMinor($line['total_price'])
                : (int)round($priceMinor * $quantity);

            $entry = array(
                'name' => (string)($line['title'] ?? ''),
                'sku' => self::sku($line),
                'quantity' => $quantity,
                'price' => array('amount_minor' => $priceMinor, 'currency' => $currency),
                'line_total' => array('amount_minor' => $lineTotal, 'currency' => $currency),
            );

            $options = self::options($line);
            if ($options !== array()) {
                $entry['options'] = $options;
            }

            $items[] = $entry;
        }

        $delivery = isset($order['delivery_address']) && is_array($order['delivery_address']) ? $order['delivery_address'] : array();
        $deliveryMinor = self::deliveryMinor($order);

        $customFields = array(
            'insales_order_id' => (string)($order['id'] ?? ''),
            'insales_number' => (string)($order['number'] ?? ''),
            'insales_status' => (string)($order['fulfillment_status'] ?? ''),
            'insales_payment_gateway' => self::paymentTitle($order),
            'insales_delivery_title' => (string)($delivery['title'] ?? ''),
            'insales_comment' => (string)($order['comment'] ?? ''),
            'insales_source' => (string)($order['source'] ?? ''),
        );

        foreach (self::fieldsValues($order) as $name => $value) {
            $customFields['insales_field_' . $name] = $value;
        }

        return array(
            'external_id' => self::externalId($order),
            'payload' => array(
                'order_number' => (string)($order['number'] ?? ($order['id'] ?? '')),
                'order_status' => (string)$statusCode,
                'items' => $items,
                'subtotal' => array('amount_minor' => self::subtotalMinor($order, $items), 'currency' => $currency),
                'delivery_total' => array('amount_minor' => $deliveryMinor, 'currency' => $currency),
                'total' => array('amount_minor' => self::totalMinor($order, $items, $deliveryMinor), 'currency' => $currency),
                'paid' => self::isPaid($order),
                'customer' => array(
                    'full_name' => self::fullName($order),
                    'phone' => self::phone($order),
                    'email' => (string)($order['client']['email'] ?? ($order['email'] ?? '')),
                ),
                'delivery_method' => (string)($delivery['title'] ?? ''),
                'delivery_address' => array(
                    'city' => (string)($delivery['city'] ?? ''),
                    'street' => trim((string)($delivery['address'] ?? '') . ' ' . (string)($delivery['house'] ?? '')),
                    'postal_code' => (string)($delivery['zip'] ?? ''),
                    'country' => (string)($delivery['country'] ?? ''),
                ),
                'payment_method' => self::paymentTitle($order),
                'custom_fields' => $customFields,
            ),
        );
    }

    /**
     * @return string
     */
    public static function externalId(array $order)
    {
        if (!empty($order['id'])) {
            return (string)$order['id'];
        }

        if (!empty($order['number'])) {
            return (string)$order['number'];
        }

        return 'insales-' . substr(md5(json_encode($order)), 0, 12);
    }

    /**
     * @return bool
     */
    public static function isPaid(array $order)
    {
        $status = strtolower((string)($order['financial_status'] ?? ($order['payment_status'] ?? '')));

        return in_array($status, array('paid', 'partially_paid'), true);
    }

    /**
     * @return array
     */
    private static function lines(array $order)
    {
        foreach (array('order_lines', 'order_lines_attributes', 'lines') as $key) {
            if (isset($order[$key]) && is_array($order[$key])) {
                return $order[$key];
            }
        }

        return array();
    }

    /**
     * @return string
     */
    private static function sku(array $line)
    {
        foreach (array('sku', 'article', 'variant_id', 'product_id') as $key) {
            if (!empty($line[$key])) {
                return (string)$line[$key];
            }
        }

        return (string)($line['title'] ?? '');
    }

    /**
     * @return array
     */
    private static function options(array $line)
    {
        $result = array();

        foreach ((array)($line['characteristics'] ?? array()) as $name => $value) {
            if (!is_scalar($value) || trim((string)$value) === '') {
                continue;
            }

            $result[] = array('name' => (string)$name, 'value' => (string)$value);
        }

        return $result;
    }

    /**
     * Custom fields of the order (`fields_values` is a map of name => value).
     *
     * @return array
     */
    private static function fieldsValues(array $order)
    {
        $result = array();

        foreach ((array)($order['fields_values'] ?? array()) as $name => $value) {
            if (!is_scalar($value) || trim((string)$value) === '') {
                continue;
            }

            // Keep letters (including Cyrillic) and digits, replace the rest.
            $name = preg_replace('/[^\p{L}\p{N}_]+/u', '_', (string)$name);
            $result[$name] = substr((string)$value, 0, 255);
        }

        return $result;
    }

    /**
     * @return string
     */
    private static function fullName(array $order)
    {
        $client = isset($order['client']) && is_array($order['client']) ? $order['client'] : array();
        $name = trim((string)($client['name'] ?? ($order['client_name'] ?? '')));

        if ($name !== '') {
            return $name;
        }

        return trim((string)($client['surname'] ?? '') . ' ' . (string)($client['first_name'] ?? ''));
    }

    /**
     * @return string
     */
    private static function phone(array $order)
    {
        $client = isset($order['client']) && is_array($order['client']) ? $order['client'] : array();

        return (string)($client['phone'] ?? ($order['phone'] ?? ''));
    }

    /**
     * @return string
     */
    private static function paymentTitle(array $order)
    {
        foreach (array('payment_gateway_title', 'payment_gateway', 'payment_title') as $key) {
            if (!empty($order[$key]) && is_string($order[$key])) {
                return (string)$order[$key];
            }
        }

        return '';
    }

    /**
     * @return int
     */
    private static function deliveryMinor(array $order)
    {
        $delivery = isset($order['delivery_address']) && is_array($order['delivery_address']) ? $order['delivery_address'] : array();

        foreach (array('delivery_price', 'price') as $key) {
            if (isset($delivery[$key]) && $delivery[$key] !== '') {
                return self::toMinor($delivery[$key]);
            }
        }

        if (isset($order['delivery_price']) && $order['delivery_price'] !== '') {
            return self::toMinor($order['delivery_price']);
        }

        return 0;
    }

    /**
     * @return int
     */
    private static function subtotalMinor(array $order, array $items)
    {
        if (isset($order['subtotal_price']) && $order['subtotal_price'] !== '') {
            return self::toMinor($order['subtotal_price']);
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
    private static function totalMinor(array $order, array $items, $deliveryMinor)
    {
        if (isset($order['total_price']) && $order['total_price'] !== '') {
            return self::toMinor($order['total_price']);
        }

        return self::subtotalMinor($order, $items) + (int)$deliveryMinor;
    }

    /**
     * @return string
     */
    private static function currency(array $order)
    {
        $currency = (string)($order['currency'] ?? ($order['currency_code'] ?? ''));

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
}
