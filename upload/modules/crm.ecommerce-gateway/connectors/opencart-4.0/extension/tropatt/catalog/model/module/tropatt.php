<?php
namespace Opencart\Catalog\Model\Extension\Tropatt\Module;

class Tropatt extends \Opencart\System\Engine\Model {
    public function pushOrderToCrm(int $order_id, int $order_status_id): array {
        $this->load->model('checkout/order');
        $order_info = $this->model_checkout_order->getOrder($order_id);

        if (!$order_info) {
            return ['success' => false, 'code' => null, 'error' => 'Order not found'];
        }

        $gateway_url = rtrim((string)$this->config->get('module_tropatt_gateway_url'), '/');
        $store_key = (string)$this->config->get('module_tropatt_store_key');
        $store_secret = (string)$this->config->get('module_tropatt_store_secret');

        if (empty($gateway_url) || empty($store_key) || empty($store_secret)) {
            return ['success' => false, 'code' => null, 'error' => 'Module configuration missing'];
        }

        $this->load->model('account/order');
        $order_products = $this->model_account_order->getProducts($order_id);
        $order_totals = $this->model_account_order->getTotals($order_id);

        $currency = !empty($order_info['currency_code']) ? strtoupper((string)$order_info['currency_code']) : 'RUB';

        $items = [];
        foreach ($order_products as $product) {
            $price_minor = (int)round(((float)$product['price']) * 100);
            $total_minor = (int)round(((float)$product['total']) * 100);

            $items[] = [
                'name' => (string)$product['name'],
                'sku' => !empty($product['model']) ? (string)$product['model'] : (string)$product['product_id'],
                'quantity' => (float)$product['quantity'],
                'price' => ['amount_minor' => $price_minor, 'currency' => $currency],
                'line_total' => ['amount_minor' => $total_minor, 'currency' => $currency]
            ];
        }

        $total_minor = (int)round(((float)$order_info['total']) * 100);
        $shipping_minor = 0;
        $subtotal_minor = $total_minor;

        foreach ($order_totals as $t) {
            if ($t['code'] === 'shipping') {
                $shipping_minor = (int)round(((float)$t['value']) * 100);
            } elseif ($t['code'] === 'sub_total') {
                $subtotal_minor = (int)round(((float)$t['value']) * 100);
            }
        }

        $customer_name = trim($order_info['firstname'] . ' ' . $order_info['lastname']);
        if ($customer_name === '') {
            $customer_name = 'Покупатель #' . $order_id;
        }

        $payload = [
            'external_id' => (string)$order_id,
            'payload' => [
                'order_number' => (string)$order_id,
                'order_status' => (string)$order_status_id,
                'items' => $items,
                'subtotal' => ['amount_minor' => $subtotal_minor, 'currency' => $currency],
                'delivery_total' => ['amount_minor' => $shipping_minor, 'currency' => $currency],
                'total' => ['amount_minor' => $total_minor, 'currency' => $currency],
                'customer' => [
                    'full_name' => $customer_name,
                    'phone' => (string)$order_info['telephone'],
                    'email' => (string)$order_info['email']
                ],
                'delivery_method' => (string)($order_info['shipping_method'] ?? ''),
                'delivery_address' => [
                    'city' => (string)($order_info['shipping_city'] ?? ''),
                    'street' => trim((string)($order_info['shipping_address_1'] ?? '') . ' ' . (string)($order_info['shipping_address_2'] ?? '')),
                    'country' => (string)($order_info['shipping_country'] ?? 'RU')
                ],
                'payment_method' => (string)($order_info['payment_method'] ?? ''),
                'paid' => false,
                'custom_fields' => [
                    'opencart_comment' => (string)$order_info['comment'],
                    'opencart_ip' => (string)$order_info['ip']
                ]
            ]
        ];

        $raw_body = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $timestamp = (string)time();
        $nonce = bin2hex(random_bytes(16));

        $canonical = "POST\n/_module/crm.ecommerce-gateway/v1/orders\n" . $timestamp . "\n" . $nonce . "\n" . hash('sha256', $raw_body);
        $signature = base64_encode(hash_hmac('sha256', $canonical, $store_secret, true));

        $headers = [
            'Content-Type: application/json',
            'Accept: application/json',
            'X-Store-Key: ' . $store_key,
            'X-TropaTT-Timestamp: ' . $timestamp,
            'X-TropaTT-Nonce: ' . $nonce,
            'X-TropaTT-Signature: ' . $signature,
            'X-TropaTT-Idempotency-Key: ' . $store_key . ':order:' . $order_id
        ];

        $endpoint = $gateway_url . '/orders';
        $ch = curl_init($endpoint);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $raw_body);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);

        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);

        if ($err) {
            return ['success' => false, 'code' => null, 'error' => $err];
        }

        $decoded = json_decode((string)$response, true);
        if ($http_code >= 200 && $http_code < 300) {
            return ['success' => true, 'code' => $decoded['code'] ?? 'OK', 'error' => null];
        }

        return ['success' => false, 'code' => $decoded['code'] ?? null, 'error' => $response];
    }
}
