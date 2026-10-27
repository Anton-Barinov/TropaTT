<?php

namespace Tropatt\Moguta;

/**
 * Moguta order -> canonical TropaTT E-COM-01 payload.
 *
 * Moguta keeps the basket in `order_content` (a JSON string in current versions
 * and a serialized array in older ones), the customer in `name`/`phone`/`email`
 * and the money in `summ`/`total`.
 */
class OrderMapper
{
    /**
     * @return array
     */
    public static function toCanonical(array $order, $statusCode = 'new')
    {
        $currency = self::currency($order);
        $items = array();

        foreach (self::items($order) as $position) {
            $quantity = self::toFloat($position['count'] ?? ($position['quantity'] ?? 1));
            $priceMinor = self::toMinor($position['price'] ?? 0);
            $lineMinor = isset($position['summ']) && $position['summ'] !== ''
                ? self::toMinor($position['summ'])
                : (int)round($priceMinor * $quantity);

            $entry = array(
                'name' => (string)($position['title'] ?? ($position['name'] ?? '')),
                'sku' => self::sku($position),
                'quantity' => $quantity,
                'price' => array('amount_minor' => $priceMinor, 'currency' => $currency),
                'line_total' => array('amount_minor' => $lineMinor, 'currency' => $currency),
            );

            $variants = self::variants($position);
            if ($variants !== array()) {
                $entry['options'] = $variants;
            }

            $items[] = $entry;
        }

        $deliveryMinor = self::toMinor($order['delivery_cost'] ?? ($order['delivery'] ?? 0));
        $subtotalMinor = self::subtotalMinor($order, $items);
        $totalMinor = isset($order['summ']) && $order['summ'] !== ''
            ? self::toMinor($order['summ'])
            : $subtotalMinor + $deliveryMinor;

        $customFields = array(
            'moguta_order_id' => (string)($order['id'] ?? ''),
            'moguta_number' => (string)($order['number'] ?? ''),
            'moguta_status' => (string)($order['status'] ?? ''),
            'moguta_payment' => (string)($order['payment'] ?? ($order['payment_method'] ?? '')),
            'moguta_delivery' => (string)($order['delivery'] ?? ($order['delivery_method'] ?? '')),
            'moguta_comment' => (string)($order['comment'] ?? ''),
            'moguta_user_email' => (string)($order['email'] ?? ''),
        );

        if (!empty($order['add_fields']) && is_array($order['add_fields'])) {
            foreach ($order['add_fields'] as $name => $value) {
                if (!is_scalar($value) || trim((string)$value) === '') {
                    continue;
                }
                $name = preg_replace('/[^\p{L}\p{N}_]+/u', '_', (string)$name);
                $customFields['moguta_field_' . $name] = substr((string)$value, 0, 255);
            }
        }

        return array(
            'external_id' => self::externalId($order),
            'payload' => array(
                'order_number' => (string)($order['number'] ?? ($order['id'] ?? '')),
                'order_status' => (string)$statusCode,
                'items' => $items,
                'subtotal' => array('amount_minor' => $subtotalMinor, 'currency' => $currency),
                'delivery_total' => array('amount_minor' => $deliveryMinor, 'currency' => $currency),
                'total' => array('amount_minor' => $totalMinor, 'currency' => $currency),
                'paid' => self::isPaid($order),
                'customer' => array(
                    'full_name' => (string)($order['name'] ?? ''),
                    'phone' => (string)($order['phone'] ?? ''),
                    'email' => (string)($order['email'] ?? ''),
                ),
                'delivery_method' => (string)($order['delivery'] ?? ($order['delivery_method'] ?? '')),
                'delivery_address' => array(
                    'city' => (string)($order['city'] ?? ($order['delivery_city'] ?? '')),
                    'street' => (string)($order['address'] ?? ($order['delivery_address'] ?? '')),
                    'postal_code' => (string)($order['zip'] ?? ($order['index'] ?? '')),
                    'country' => (string)($order['country'] ?? ''),
                ),
                'payment_method' => (string)($order['payment'] ?? ($order['payment_method'] ?? '')),
                'custom_fields' => $customFields,
            ),
        );
    }

    /**
     * `order_content` may be JSON, a serialized array, or already an array.
     *
     * @return array
     */
    public static function items(array $order)
    {
        $content = $order['order_content'] ?? ($order['items'] ?? array());

        if (is_string($content)) {
            $decoded = json_decode($content, true);
            if (!is_array($decoded)) {
                $decoded = self::decodeLegacyArray($content);
            }
            $content = is_array($decoded) ? $decoded : array();
        }

        if (!is_array($content)) {
            return array();
        }

        if (isset($content['items']) && is_array($content['items'])) {
            return $content['items'];
        }

        return $content;
    }

    /**
     * Decode only the scalar/array subset used by legacy Moguta baskets.
     * Objects, references, custom classes and trailing data are rejected.
     *
     * @return array|null
     */
    private static function decodeLegacyArray($serialized)
    {
        if (!is_string($serialized) || strlen($serialized) > 1048576) {
            return null;
        }

        $offset = 0;
        $entries = 0;
        $value = self::parseLegacyValue($serialized, $offset, 0, $entries);
        if ($value instanceof \stdClass || $offset !== strlen($serialized) || !is_array($value)) {
            return null;
        }

        return $value;
    }

    /** @return mixed|null */
    private static function parseLegacyValue($input, &$offset, $depth, &$entries)
    {
        if ($depth > 8 || $offset >= strlen($input)) {
            return new \stdClass();
        }

        $type = $input[$offset++];
        if ($type === 'N') {
            if (substr($input, $offset, 1) !== ';') {
                return new \stdClass();
            }
            $offset++;
            return null;
        }

        if (!in_array($type, array('b', 'i', 'd', 's', 'a'), true)
            || substr($input, $offset, 1) !== ':') {
            return new \stdClass();
        }
        $offset++;

        if ($type === 'b' || $type === 'i' || $type === 'd') {
            $end = strpos($input, ';', $offset);
            if ($end === false) {
                return new \stdClass();
            }
            $raw = substr($input, $offset, $end - $offset);
            $offset = $end + 1;
            if ($type === 'b') {
                return ($raw === '0' || $raw === '1') ? $raw === '1' : new \stdClass();
            }
            if ($type === 'i') {
                return preg_match('/^-?(?:0|[1-9][0-9]*)$/D', $raw) === 1 ? (int)$raw : new \stdClass();
            }
            return is_numeric($raw) && is_finite((float)$raw) ? (float)$raw : new \stdClass();
        }

        if ($type === 's') {
            if (preg_match('/\G([0-9]{1,7}):"/A', $input, $matches, 0, $offset) !== 1) {
                return new \stdClass();
            }
            $stringLength = (int)$matches[1];
            $offset += strlen($matches[0]);
            if ($stringLength > 262144 || $offset + $stringLength + 2 > strlen($input)) {
                return new \stdClass();
            }
            $value = substr($input, $offset, $stringLength);
            $offset += $stringLength;
            if (substr($input, $offset, 2) !== '";') {
                return new \stdClass();
            }
            $offset += 2;
            return $value;
        }

        if (preg_match('/\G([0-9]{1,5}):\\{/A', $input, $matches, 0, $offset) !== 1) {
            return new \stdClass();
        }
        $count = (int)$matches[1];
        $entries += $count;
        if ($count > 2000 || $entries > 5000) {
            return new \stdClass();
        }
        $offset += strlen($matches[0]);
        $result = array();
        for ($index = 0; $index < $count; $index++) {
            $key = self::parseLegacyValue($input, $offset, $depth + 1, $entries);
            $value = self::parseLegacyValue($input, $offset, $depth + 1, $entries);
            if ((!is_int($key) && !is_string($key)) || $key instanceof \stdClass || $value instanceof \stdClass) {
                return new \stdClass();
            }
            $result[$key] = $value;
        }
        if (substr($input, $offset, 1) !== '}') {
            return new \stdClass();
        }
        $offset++;
        return $result;
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

        return 'moguta-' . substr(md5(json_encode($order)), 0, 12);
    }

    /**
     * @return bool
     */
    public static function isPaid(array $order)
    {
        if (!empty($order['is_paid']) || !empty($order['paid'])) {
            return true;
        }

        $status = strtolower((string)($order['pay_status'] ?? ($order['payment_status'] ?? '')));

        return in_array($status, array('paid', 'оплачен', 'success'), true);
    }

    /**
     * @return string
     */
    private static function sku(array $position)
    {
        foreach (array('code', 'sku', 'article', 'product_id') as $key) {
            if (!empty($position[$key])) {
                return (string)$position[$key];
            }
        }

        return (string)($position['title'] ?? ($position['name'] ?? ''));
    }

    /**
     * @return array
     */
    private static function variants(array $position)
    {
        $result = array();

        foreach ((array)($position['variants'] ?? ($position['properties'] ?? array())) as $name => $value) {
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
    private static function subtotalMinor(array $order, array $items)
    {
        if (isset($order['subtotal']) && $order['subtotal'] !== '') {
            return self::toMinor($order['subtotal']);
        }

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
