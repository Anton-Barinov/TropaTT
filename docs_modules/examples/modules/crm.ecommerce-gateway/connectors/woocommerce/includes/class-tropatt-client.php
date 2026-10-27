<?php
if (!defined('ABSPATH')) {
    exit;
}

class Tropatt_Client {
    /**
     * Set while the plugin writes a status that came from the CRM, so the
     * resulting woocommerce_order_status_changed hook does not bounce back.
     */
    public static $suppress_echo = false;

    /** Action Scheduler hook used for asynchronous order delivery. */
    const AS_HOOK = 'tropatt_send_order_event';

    /**
     * Orders already queued in this request, so a store that fires both the
     * legacy checkout hook and the Store API one does not send the same order
     * twice (the gateway deduplicates by idempotency key as well, but a second
     * webhook is needless load and noise in the store log).
     */
    private static $queued = array();

    public static function on_order_created($order_id, $posted_data, $order) {
        self::dispatch((int)$order_id, 'created');
    }

    /**
     * Cart/Checkout block (Store API) equivalent of
     * `woocommerce_checkout_order_processed`.
     *
     * Orders placed through the block checkout never fire the legacy hook (the
     * Store API fires `woocommerce_store_api_checkout_order_processed` instead,
     * WC 7.2+), so without this handler every order from the block checkout was
     * silently never sent to the CRM.
     */
    public static function on_store_api_order_processed($order) {
        if (is_object($order) && method_exists($order, 'get_id')) {
            self::dispatch((int)$order->get_id(), 'created');
        }
    }

    public static function on_order_status_changed($order_id, $old_status, $new_status, $order) {
        self::dispatch((int)$order_id, 'status_changed');
    }

    /**
     * Queue the order event instead of sending it inside the checkout request.
     *
     * Action Scheduler ships with WooCommerce; when it is unavailable (an old or
     * stripped install) the event is sent directly so no order is ever lost.
     */
    public static function dispatch($order_id, $context) {
        if (self::$suppress_echo) {
            return;
        }

        if (get_option('tropatt_enabled', 'no') !== 'yes') {
            return;
        }

        if ($order_id <= 0) {
            return;
        }

        $queue_key = (int)$order_id . '|' . (string)$context;
        if (isset(self::$queued[$queue_key])) {
            return;
        }
        self::$queued[$queue_key] = true;

        if (function_exists('as_enqueue_async_action')) {
            as_enqueue_async_action(
                self::AS_HOOK,
                array('order_id' => (int)$order_id, 'context' => (string)$context),
                'tropatt'
            );
            return;
        }

        self::process_queued_event($order_id, $context);
    }

    /**
     * Action Scheduler callback: load the order again (it may have changed since
     * it was queued) and push it to the CRM.
     */
    public static function process_queued_event($order_id, $context = '') {
        $order = function_exists('wc_get_order') ? wc_get_order((int)$order_id) : null;

        if (!$order) {
            return array('success' => false, 'error' => 'Order not found: ' . (int)$order_id);
        }

        return self::sync_order($order);
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
        $currency = self::resolve_currency($order);
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
                'custom_fields' => self::collect_custom_fields($order)
            )
        );

        $raw_body = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $timestamp = (string)time();
        $nonce = bin2hex(random_bytes(16));

        $canonical = "POST\n/_module/crm.ecommerce-gateway/v1/orders\n" . $timestamp . "\n" . $nonce . "\n" . hash('sha256', $raw_body);
        $signature = base64_encode(hash_hmac('sha256', $canonical, $store_secret, true));

        // wp_remote_post() expects an associative header map; a plain list would
        // be sent as headers literally named "0", "1", ... and the gateway would
        // never see X-Store-Key / X-TropaTT-Signature.
        $headers = array(
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
            'X-Store-Key' => $store_key,
            'X-TropaTT-Timestamp' => $timestamp,
            'X-TropaTT-Nonce' => $nonce,
            'X-TropaTT-Signature' => $signature,
            'X-TropaTT-Idempotency-Key' => $store_key . ':order:' . $order_id
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

    /**
     * The order currency, honouring the multi-currency plugins that store the
     * transaction currency in order meta (WOOCS, WPML/WCML).
     */
    private static function resolve_currency($order) {
        $currency = strtoupper((string)$order->get_currency());

        foreach (array('_woocs_order_currency', '_wcml_order_currency') as $meta_key) {
            $meta_value = $order->get_meta($meta_key);
            if (is_string($meta_value) && trim($meta_value) !== '') {
                return strtoupper(trim($meta_value));
            }
        }

        return $currency;
    }

    /**
     * Public order meta plus the fields written by third-party checkout field
     * plugins, so custom checkout questions reach the CRM instead of being lost.
     */
    private static function collect_custom_fields($order) {
        $fields = array(
            'customer_note' => (string)$order->get_customer_note(),
            'customer_ip' => (string)$order->get_customer_ip_address()
        );

        // Underscored keys of these plugins hold real checkout answers.
        $plugin_prefixes = '/^_(wccf|wc_other|checkout_field|checkout|woocs|shipping_|billing_)/i';
        $added = 0;

        foreach ($order->get_meta_data() as $meta) {
            if ($added >= 50) {
                break;
            }

            $data = $meta->get_data();
            $key = isset($data['key']) ? (string)$data['key'] : '';
            $value = isset($data['value']) ? $data['value'] : null;

            if ($key === '' || !is_scalar($value)) {
                continue;
            }

            $is_public = strpos($key, '_') !== 0;
            if (!$is_public && !preg_match($plugin_prefixes, $key)) {
                continue;
            }

            $fields['wp_' . ltrim($key, '_')] = substr((string)$value, 0, 255);
            $added++;
        }

        return $fields;
    }
}
