<?php

namespace Tropatt\CsCart;

/**
 * CS-Cart order -> canonical TropaTT E-COM-01 payload.
 *
 * `fn_get_order_info()` returns products in `products` (with `company_id` in the
 * Multi-Vendor edition), the customer in `user_data`/`firstname`/`lastname`, the
 * address in `s_address`/`b_address`, taxes in `taxes` and shipping in
 * `shipping`/`shipping_cost`.
 */
class OrderMapper
{
    /**
     * Multi-Vendor: one canonical order per vendor.
     *
     * @return array
     */
    public static function splitByVendor(array $orderInfo, $enabled)
    {
        if (!$enabled) {
            return array($orderInfo);
        }

        $groups = array();
        foreach ((array)($orderInfo['products'] ?? array()) as $product) {
            $companyId = isset($product['company_id']) ? (int)$product['company_id'] : 0;
            if (!isset($groups[$companyId])) {
                $groups[$companyId] = $orderInfo;
                $groups[$companyId]['products'] = array();
            }
            $groups[$companyId]['products'][] = $product;
        }

        return $groups === array() ? array($orderInfo) : array_values($groups);
    }

    /**
     * @return array
     */
    public static function toCanonical(array $orderInfo, $statusCode = 'new')
    {
        $currency = self::currency($orderInfo);
        $items = array();

        foreach ((array)($orderInfo['products'] ?? array()) as $product) {
            $quantity = self::toFloat($product['amount'] ?? 1);
            $priceMinor = self::toMinor($product['price'] ?? 0);
            $subtotalMinor = isset($product['subtotal']) && $product['subtotal'] !== ''
                ? self::toMinor($product['subtotal'])
                : (int)round($priceMinor * $quantity);

            $entry = array(
                'name' => (string)($product['product'] ?? ''),
                'sku' => self::sku($product),
                'quantity' => $quantity,
                'price' => array('amount_minor' => $priceMinor, 'currency' => $currency),
                'line_total' => array('amount_minor' => $subtotalMinor, 'currency' => $currency),
            );

            $options = self::options($product);
            if ($options !== array()) {
                $entry['options'] = $options;
            }

            $items[] = $entry;
        }

        $shippingMinor = self::toMinor($orderInfo['shipping_cost'] ?? 0);
        $subtotalMinor = self::toMinor($orderInfo['subtotal'] ?? 0);
        $totalMinor = self::toMinor($orderInfo['total'] ?? 0);
        $taxMinor = self::taxMinor($orderInfo, $currency);

        $vendorId = self::vendorId($orderInfo);
        $externalId = (string)($orderInfo['order_id'] ?? '');
        if ($vendorId > 0) {
            $externalId .= '-' . $vendorId;
        }

        $customFields = array(
            'cs_cart_order_id' => (string)($orderInfo['order_id'] ?? ''),
            'cs_cart_status' => (string)($orderInfo['status'] ?? ''),
            'cs_cart_payment_method' => (string)($orderInfo['payment_method']['payment'] ?? ''),
            'cs_cart_shipping_method' => (string)($orderInfo['shipping']['shipping'] ?? ''),
            'cs_cart_coupons' => self::coupons($orderInfo),
            'cs_cart_storefront_id' => (string)($orderInfo['storefront_id'] ?? ''),
            'cs_cart_tax_total' => (string)$taxMinor,
            'cs_cart_vendor_id' => (string)$vendorId,
            'cs_cart_notes' => (string)($orderInfo['notes'] ?? ''),
        );

        foreach ((array)($orderInfo['product_groups'] ?? array()) as $index => $group) {
            if (!is_array($group)) {
                continue;
            }
            $customFields['cs_cart_group_' . (int)$index] = (string)($group['name'] ?? '');
        }

        return array(
            'external_id' => $externalId,
            'payload' => array(
                'order_number' => (string)($orderInfo['order_id'] ?? ''),
                'order_status' => (string)$statusCode,
                'items' => $items,
                'subtotal' => array('amount_minor' => $subtotalMinor, 'currency' => $currency),
                'delivery_total' => array('amount_minor' => $shippingMinor, 'currency' => $currency),
                'total' => array('amount_minor' => $totalMinor, 'currency' => $currency),
                'paid' => self::isPaid($orderInfo),
                'customer' => array(
                    'full_name' => self::fullName($orderInfo),
                    'phone' => self::phone($orderInfo),
                    'email' => (string)($orderInfo['email'] ?? ''),
                ),
                'delivery_method' => (string)($orderInfo['shipping']['shipping'] ?? ''),
                'delivery_address' => self::address($orderInfo),
                'payment_method' => (string)($orderInfo['payment_method']['payment'] ?? ''),
                'custom_fields' => $customFields,
            ),
        );
    }

    /**
     * @return bool
     */
    public static function isPaid(array $orderInfo)
    {
        if (!empty($orderInfo['is_paid'])) {
            return true;
        }

        $status = strtolower((string)($orderInfo['payment_status'] ?? ($orderInfo['status'] ?? '')));

        return in_array($status, array('paid', 'complete', 'processed'), true);
    }

    /**
     * @return int
     */
    private static function vendorId(array $orderInfo)
    {
        if (empty($orderInfo['products'])) {
            return 0;
        }

        $first = reset($orderInfo['products']);

        return is_array($first) && isset($first['company_id']) ? (int)$first['company_id'] : 0;
    }

    /**
     * @return string
     */
    private static function sku(array $product)
    {
        foreach (array('product_code', 'sku', 'product_id') as $key) {
            if (!empty($product[$key])) {
                return (string)$product[$key];
            }
        }

        return (string)($product['product'] ?? '');
    }

    /**
     * @return array
     */
    private static function options(array $product)
    {
        $result = array();

        foreach ((array)($product['product_options'] ?? array()) as $name => $value) {
            if (is_array($value)) {
                $value = isset($value['variant_name']) ? $value['variant_name'] : implode(', ', array_map('strval', $value));
            }

            if (!is_scalar($value) || trim((string)$value) === '') {
                continue;
            }

            $result[] = array('name' => (string)$name, 'value' => (string)$value);
        }

        return $result;
    }

    /**
     * @return array
     */
    private static function address(array $orderInfo)
    {
        $shipping = isset($orderInfo['s_address']) && is_array($orderInfo['s_address']) ? $orderInfo['s_address'] : array();
        $billing = isset($orderInfo['b_address']) && is_array($orderInfo['b_address']) ? $orderInfo['b_address'] : array();
        $address = $shipping !== array() ? $shipping : $billing;

        return array(
            'city' => (string)($address['city'] ?? ''),
            'street' => trim((string)($address['address'] ?? '')),
            'postal_code' => (string)($address['zipcode'] ?? ''),
            'country' => (string)($address['country'] ?? ''),
        );
    }

    /**
     * @return string
     */
    private static function fullName(array $orderInfo)
    {
        $name = trim((string)($orderInfo['firstname'] ?? '') . ' ' . (string)($orderInfo['lastname'] ?? ''));

        if ($name === '' && !empty($orderInfo['user_data'])) {
            $name = trim((string)($orderInfo['user_data']['firstname'] ?? '') . ' ' . (string)($orderInfo['user_data']['lastname'] ?? ''));
        }

        return $name;
    }

    /**
     * @return string
     */
    private static function phone(array $orderInfo)
    {
        foreach (array('phone', 's_phone', 'b_phone') as $key) {
            if (!empty($orderInfo[$key])) {
                return (string)$orderInfo[$key];
            }
        }

        return '';
    }

    /**
     * @return string
     */
    private static function coupons(array $orderInfo)
    {
        $codes = array();

        foreach ((array)($orderInfo['coupons'] ?? array()) as $coupon) {
            if (is_array($coupon) && !empty($coupon['code'])) {
                $codes[] = (string)$coupon['code'];
            }
        }

        return implode(', ', $codes);
    }

    /**
     * @return int
     */
    private static function taxMinor(array $orderInfo, $currency)
    {
        if (isset($orderInfo['tax_total']) && $orderInfo['tax_total'] !== '') {
            return self::toMinor($orderInfo['tax_total']);
        }

        $sum = 0;
        foreach ((array)($orderInfo['taxes'] ?? array()) as $tax) {
            if (is_array($tax) && isset($tax['tax_subtotal'])) {
                $sum += self::toMinor($tax['tax_subtotal']);
            }
        }

        return $sum;
    }

    /**
     * @return string
     */
    private static function currency(array $orderInfo)
    {
        $currency = (string)($orderInfo['secondary_currency'] ?? ($orderInfo['currency_code'] ?? ''));

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
