<?php
if (!defined('ABSPATH')) {
    exit;
}

class Tropatt_Client {
    public static $suppress_echo = false;

    public static function on_order_created($order_id, $posted_data, $order) {
        if (self::$suppress_echo) {
            return;
        }

        $enabled = get_option('tropatt_enabled', 'no');
        if ($enabled !== 'yes') {
            return;
        }

        self::sync_order($order);
    }

    public static function on_order_status_changed($order_id, $old_status, $new_status, $order) {
        if (self::$suppress_echo) {
            return;
        }

        $enabled = get_option('tropatt_enabled', 'no');
        if ($enabled !== 'yes') {
            return;
        }

        self::sync_order($order);
    }

    public static function sync_order($order) {
        if (!is_a($order, 'WC_Order')) {
            return array('success' => false, 'error' => 'Invalid order object');
        }

        $gateway_url = rtrim(get_option('tropatt_gateway_url', ''), '/');
        $store_key = get_option('tropatt_store_key', '');
        $store_secret = get_option('tropatt_store_secret', '');

        if (empty($gateway_url) || empty($store_key) || empty($store_secret)) {
            return array('success' => false, 'error' => 'Gateway configuration missing');
        }

        $order_id = $order->get_id();
        $currency = strtoupper((string)$order->get_currency());
        $items = array();

        foreach ($order->get_items() as $item) {
            $product = $item->get_product();
            $sku = $product ? $product->get_sku() : '';
            if (empty($sku)) {
                $sku = (string)$item->get_product_id();
            }

            $price_minor = (int)round(((float)$order->get_item_subtotal($item, false)) * 100);
            $line_total_minor = (int)round(((float)$item->get_total()) * 100);

            $items[] = array(
                'name' => (string)$item->get_name(),
                'sku' => (string)$sku,
                'quantity' => (float)$item->get_quantity(),
                'price' => array('amount_minor' => $price_minor, 'currency' => $currency),
                'line_total' => array('amount_minor' => $line_total_minor, 'currency' => $currency)
            );
        }

        $total_minor = (int)round(((float)$order->get_total()) * 100);
        $shipping_minor = (int)round(((float)$order->get_shipping_total()) * 100);
        $discount_minor = (int)round(((float)$order->get_discount_total()) * 100);
        $tax_minor = (int)round(((float)$order->get_total_tax()) * 100);
        $subtotal_minor = (int)round(((float)$order->get_subtotal()) * 100);

        $customer_name = trim($order->get_billing_first_name() . ' ' . $order->get_billing_last_name());
        if ($customer_name === '') {
            $customer_name = 'Покупатель #' . $order_id;
        }

        $payload = array(
            'external_id' => (string)$order_id,
            'payload' => array(
                'order_number' => (string)$order->get_order_number(),
                'order_status' => (string)$order->get_status(),
                'items' => $items,
                'subtotal' => array('amount_minor' => $subtotal_minor, 'currency' => $currency),
                'discount_total' => array('amount_minor' => $discount_minor, 'currency' => $currency),
                'delivery_total' => array('amount_minor' => $shipping_minor, 'currency' => $currency),
                'tax_total' => array('amount_minor' => $tax_minor, 'currency' => $currency),
                'total' => array('amount_minor' => $total_minor, 'currency' => $currency),
                'paid' => $order->is_paid(),
                'customer' => array(
                    'full_name' => $customer_name,
                    'phone' => (string)$order->get_billing_phone(),
                    'email' => (string)$order->get_billing_email()
                ),
                'delivery_method' => (string)$order->get_shipping_method(),
                'delivery_address' => array(
                    'country' => (string)$order->get_shipping_country(),
                    'city' => (string)$order->get_shipping_city(),
                    'street' => trim((string)$order->get_shipping_address_1() . ' ' . (string)$order->get_shipping_address_2()),
                    'postal_code' => (string)$order->get_shipping_postcode()
                ),
                'payment_method' => (string)$order->get_payment_method_title(),
                'custom_fields' => array(
                    'customer_note' => (string)$order->get_customer_note(),
                    'customer_ip' => (string)$order->get_customer_ip_address()
                )
            )
        );

        $raw_body = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $timestamp = (string)time();
        $nonce = bin2hex(random_bytes(16));

        $canonical = "POST\n/_module/crm.ecommerce-gateway/v1/orders\n" . $timestamp . "\n" . $nonce . "\n" . hash('sha256', $raw_body);
        $signature = base64_encode(hash_hmac('sha256', $canonical, $store_secret, true));

        $headers = array(
            'Content-Type: application/json',
            'Accept: application/json',
            'X-Store-Key: ' . $store_key,
            'X-TropaTT-Timestamp: ' . $timestamp,
            'X-TropaTT-Nonce: ' . $nonce,
            'X-TropaTT-Signature: ' . $signature,
            'X-TropaTT-Idempotency-Key: ' . $store_key . ':order:' . $order_id
        );

        $response = wp_remote_post($gateway_url . '/orders', array(
            'headers' => $headers,
            'body' => $raw_body,
            'timeout' => 15,
            'sslverify' => true
        ));

        if (is_wp_error($response)) {
            return array('success' => false, 'error' => $response->get_error_message());
        }

        $code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $decoded = json_decode($body, true);

        if ($code >= 200 && $code < 300) {
            return array('success' => true, 'code' => $decoded['code'] ?? 'OK', 'error' => null);
        }

        return array('success' => false, 'code' => $decoded['code'] ?? null, 'error' => $body);
    }
}
