<?php
if (!defined('ABSPATH')) {
    exit;
}

class Tropatt_Webhook_Handler {
    public static function init() {
        add_action('rest_api_init', function() {
            register_rest_route('tropatt/v1', '/webhook', array(
                'methods' => 'POST',
                'callback' => array('Tropatt_Webhook_Handler', 'handle_webhook'),
                'permission_callback' => '__return_true'
            ));
        });
    }

    public static function handle_webhook(WP_REST_Request $request) {
        $raw_body = $request->get_body();
        $timestamp = $request->get_header('x-tropatt-timestamp');
        $signature = $request->get_header('x-tropatt-signature');
        $event = $request->get_header('x-tropatt-event');

        $store_secret = (string)get_option('tropatt_store_secret', '');

        if (empty($store_secret) || empty($signature) || empty($timestamp)) {
            return new WP_REST_Response(array('error' => 'Missing authentication headers'), 401);
        }

        if (abs(time() - (int)$timestamp) > 300) {
            return new WP_REST_Response(array('error' => 'Timestamp out of tolerance window'), 401);
        }

        $expected = base64_encode(hash_hmac('sha256', $timestamp . '.' . $raw_body, $store_secret, true));
        if (!hash_equals($expected, $signature)) {
            return new WP_REST_Response(array('error' => 'Invalid cryptographic signature'), 401);
        }

        $data = json_decode($raw_body, true);
        if (!is_array($data)) {
            return new WP_REST_Response(array('error' => 'Invalid JSON payload'), 400);
        }

        if ($event === 'ping') {
            return new WP_REST_Response(array('success' => true, 'code' => 'PONG'), 200);
        }

        $order_id = (int)($data['external_order_id'] ?? 0);
        $external_status = $data['external_status'] ?? null;
        $crm_task_public_id = (string)($data['crm_task_public_id'] ?? '');

        if ($order_id <= 0) {
            return new WP_REST_Response(array('error' => 'Missing external_order_id'), 422);
        }

        $order = wc_get_order($order_id);
        if (!$order) {
            return new WP_REST_Response(array('error' => 'Order not found: ' . $order_id), 404);
        }

        $target_status = null;
        if (!empty($external_status)) {
            $target_status = sanitize_text_field($external_status);
        } else {
            $mappings = (array)get_option('tropatt_status_mappings', array());
            $new_crm_status = (string)($data['new_status'] ?? '');
            foreach ($mappings as $wc_st => $crm_st) {
                if (strcasecmp($crm_st, $new_crm_status) === 0) {
                    $target_status = $wc_st;
                    break;
                }
            }
        }

        if (empty($target_status)) {
            return new WP_REST_Response(array('success' => true, 'notice' => 'Ignored: no mapping for status'), 200);
        }

        Tropatt_Client::$suppress_echo = true;
        try {
            $note = 'Статус обновлен из TropaTT CRM';
            if (!empty($crm_task_public_id)) {
                $note .= ' (Задача: ' . $crm_task_public_id . ')';
            }
            $order->update_status($target_status, $note);
        } finally {
            Tropatt_Client::$suppress_echo = false;
        }

        return new WP_REST_Response(array('success' => true, 'order_id' => $order_id, 'new_status' => $target_status), 200);
    }
}
