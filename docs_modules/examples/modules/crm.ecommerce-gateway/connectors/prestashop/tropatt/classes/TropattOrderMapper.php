<?php

/**
 * PrestaShop order -> canonical TropaTT E-COM-01 payload.
 *
 * `collect()` reads the order through the PrestaShop API (Order, Customer,
 * Address, Currency, product combinations); `toCanonical()` is pure PHP and is
 * unit tested offline.
 */
class TropattOrderMapper
{
    /**
     * @param Order $order
     * @return array
     */
    public static function collect($order)
    {
        $currency = new Currency((int)$order->id_currency);
        $customer = new Customer((int)$order->id_customer);
        $address = new Address((int)$order->id_address_delivery);
        $country = (int)$address->id_country > 0 ? new Country((int)$address->id_country) : null;

        $items = array();
        foreach ($order->getProducts() as $product) {
            $items[] = array(
                'name' => (string)$product['product_name'],
                'sku' => self::sku($product),
                'quantity' => (float)$product['product_quantity'],
                'price_minor' => self::toMinor($product['unit_price_tax_incl']),
                'price_tax_excl_minor' => self::toMinor($product['unit_price_tax_excl']),
                'line_total_minor' => self::toMinor($product['total_price_tax_incl']),
                'options' => self::options($product),
            );
        }

        return array(
            'id' => (int)$order->id,
            'reference' => (string)$order->reference,
            'state_id' => (string)$order->current_state,
            'currency' => (string)$currency->iso_code,
            'total_minor' => self::toMinor($order->total_paid),
            'total_tax_excl_minor' => self::toMinor($order->total_paid_tax_excl),
            'subtotal_minor' => self::toMinor($order->total_products_wt),
            'shipping_minor' => self::toMinor($order->total_shipping_tax_incl),
            'paid' => (bool)$order->hasBeenPaid(),
            'items' => $items,
            'customer' => array(
                'full_name' => trim((string)$customer->firstname . ' ' . (string)$customer->lastname),
                'phone' => (string)$address->phone ? (string)$address->phone : (string)$address->phone_mobile,
                'email' => (string)$customer->email,
            ),
            'shipping_method' => (string)$order->shipping_number,
            'payment_method' => (string)$order->payment,
            'address' => array(
                'city' => (string)$address->city,
                'street' => trim((string)$address->address1 . ' ' . (string)$address->address2),
                'postal_code' => (string)$address->postcode,
                'country' => $country !== null ? (string)$country->iso_code : '',
            ),
            'id_shop' => (int)$order->id_shop,
            'note' => (string)$order->note,
        );
    }

    /**
     * @return array
     */
    public static function toCanonical(array $order, $statusCode = 'new')
    {
        $currency = isset($order['currency']) && $order['currency'] !== '' ? strtoupper((string)$order['currency']) : 'EUR';
        $items = array();

        foreach ((array)(isset($order['items']) ? $order['items'] : array()) as $item) {
            $quantity = isset($item['quantity']) ? (float)$item['quantity'] : 1.0;
            $priceMinor = isset($item['price_minor']) ? (int)$item['price_minor'] : self::toMinor(isset($item['price']) ? $item['price'] : 0);
            $lineMinor = isset($item['line_total_minor']) ? (int)$item['line_total_minor'] : (int)round($priceMinor * $quantity);

            $entry = array(
                'name' => (string)(isset($item['name']) ? $item['name'] : ''),
                'sku' => (string)(isset($item['sku']) ? $item['sku'] : ''),
                'quantity' => $quantity,
                'price' => array('amount_minor' => $priceMinor, 'currency' => $currency),
                'line_total' => array('amount_minor' => $lineMinor, 'currency' => $currency),
            );

            // Both prices are reported: the gross one is the contract amount and
            // the net one travels in the item options (tax accounting).
            $options = (array)(isset($item['options']) ? $item['options'] : array());
            if (isset($item['price_tax_excl_minor'])) {
                $options[] = array('name' => 'price_tax_excl_minor', 'value' => (string)(int)$item['price_tax_excl_minor']);
            }
            if ($options !== array()) {
                $entry['options'] = $options;
            }

            $items[] = $entry;
        }

        $address = (array)(isset($order['address']) ? $order['address'] : array());
        $customer = (array)(isset($order['customer']) ? $order['customer'] : array());

        $customFields = array(
            'prestashop_order_id' => (string)(isset($order['id']) ? $order['id'] : ''),
            'prestashop_reference' => (string)(isset($order['reference']) ? $order['reference'] : ''),
            'prestashop_state' => (string)(isset($order['state_id']) ? $order['state_id'] : ''),
            'prestashop_payment' => (string)(isset($order['payment_method']) ? $order['payment_method'] : ''),
            'prestashop_shipping' => (string)(isset($order['shipping_method']) ? $order['shipping_method'] : ''),
            'prestashop_shop_id' => (string)(isset($order['id_shop']) ? $order['id_shop'] : ''),
            'prestashop_total_tax_excl' => (string)(isset($order['total_tax_excl_minor']) ? $order['total_tax_excl_minor'] : ''),
            'prestashop_note' => (string)(isset($order['note']) ? $order['note'] : ''),
        );

        $subtotalMinor = isset($order['subtotal_minor']) ? (int)$order['subtotal_minor'] : self::sumItems($items);
        $shippingMinor = isset($order['shipping_minor']) ? (int)$order['shipping_minor'] : 0;
        $totalMinor = isset($order['total_minor']) ? (int)$order['total_minor'] : $subtotalMinor + $shippingMinor;

        return array(
            'external_id' => (string)(isset($order['id']) ? $order['id'] : ''),
            'payload' => array(
                'order_number' => (string)(isset($order['reference']) && $order['reference'] !== '' ? $order['reference'] : (isset($order['id']) ? $order['id'] : '')),
                'order_status' => (string)$statusCode,
                'items' => $items,
                'subtotal' => array('amount_minor' => $subtotalMinor, 'currency' => $currency),
                'delivery_total' => array('amount_minor' => $shippingMinor, 'currency' => $currency),
                'total' => array('amount_minor' => $totalMinor, 'currency' => $currency),
                'paid' => !empty($order['paid']),
                'customer' => array(
                    'full_name' => (string)(isset($customer['full_name']) ? $customer['full_name'] : ''),
                    'phone' => (string)(isset($customer['phone']) ? $customer['phone'] : ''),
                    'email' => (string)(isset($customer['email']) ? $customer['email'] : ''),
                ),
                'delivery_method' => (string)(isset($order['shipping_method']) ? $order['shipping_method'] : ''),
                'delivery_address' => array(
                    'city' => (string)(isset($address['city']) ? $address['city'] : ''),
                    'street' => (string)(isset($address['street']) ? $address['street'] : ''),
                    'postal_code' => (string)(isset($address['postal_code']) ? $address['postal_code'] : ''),
                    'country' => (string)(isset($address['country']) ? $address['country'] : ''),
                ),
                'payment_method' => (string)(isset($order['payment_method']) ? $order['payment_method'] : ''),
                'custom_fields' => $customFields,
            ),
        );
    }

    /**
     * @return string
     */
    public static function sku(array $product)
    {
        foreach (array('product_reference', 'reference', 'product_ean13', 'id_product') as $key) {
            if (!empty($product[$key])) {
                return (string)$product[$key];
            }
        }

        return (string)(isset($product['product_name']) ? $product['product_name'] : '');
    }

    /**
     * Combination attributes (size, colour, …) of a product line.
     *
     * @return array
     */
    public static function options(array $product)
    {
        $result = array();

        $attributes = isset($product['attributes']) ? $product['attributes'] : array();
        if (is_string($attributes) && $attributes !== '') {
            $result[] = array('name' => 'combination', 'value' => $attributes);
        } elseif (is_array($attributes)) {
            foreach ($attributes as $name => $value) {
                if (!is_scalar($value) || trim((string)$value) === '') {
                    continue;
                }
                $result[] = array('name' => (string)$name, 'value' => (string)$value);
            }
        }

        if (!empty($product['id_product_attribute'])) {
            $result[] = array('name' => 'combination_id', 'value' => (string)$product['id_product_attribute']);
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
