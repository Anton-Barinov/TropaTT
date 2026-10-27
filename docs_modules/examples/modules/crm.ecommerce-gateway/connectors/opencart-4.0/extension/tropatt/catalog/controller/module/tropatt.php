<?php
namespace Opencart\Catalog\Controller\Extension\Tropatt\Module;

class Tropatt extends \Opencart\System\Engine\Controller {
    public static bool $suppress_echo = false;

    public function onOrderHistoryAdd(string &$route, array &$args, mixed &$output): void {
        if (self::$suppress_echo) {
            return;
        }

        if (!$this->config->get('module_tropatt_status')) {
            return;
        }

        $order_id = isset($args[0]) ? (int)$args[0] : 0;
        $order_status_id = isset($args[1]) ? (int)$args[1] : 0;

        if ($order_id <= 0) {
            return;
        }

        $this->load->model('extension/tropatt/module/tropatt');
        $this->model_extension_tropatt_module_tropatt->pushOrderToCrm($order_id, $order_status_id);
    }

    public function webhook(): void {
        $raw_body = (string)file_get_contents('php://input');
        $timestamp = isset($_SERVER['HTTP_X_TROPATT_TIMESTAMP']) ? (string)$_SERVER['HTTP_X_TROPATT_TIMESTAMP'] : '';
        $signature = isset($_SERVER['HTTP_X_TROPATT_SIGNATURE']) ? (string)$_SERVER['HTTP_X_TROPATT_SIGNATURE'] : '';
        $event = isset($_SERVER['HTTP_X_TROPATT_EVENT']) ? (string)$_SERVER['HTTP_X_TROPATT_EVENT'] : '';

        $store_secret = (string)$this->config->get('module_tropatt_store_secret');

        if (empty($store_secret) || empty($signature) || empty($timestamp)) {
            $this->respondJson(401, ['error' => 'Missing authentication headers']);
            return;
        }

        if (abs(time() - (int)$timestamp) > 300) {
            $this->respondJson(401, ['error' => 'Timestamp out of tolerance window']);
            return;
        }

        $expected = base64_encode(hash_hmac('sha256', $timestamp . '.' . $raw_body, $store_secret, true));
        if (!hash_equals($expected, $signature)) {
            $this->respondJson(401, ['error' => 'Invalid cryptographic signature']);
            return;
        }

        $data = json_decode($raw_body, true);
        if (!is_array($data)) {
            $this->respondJson(400, ['error' => 'Invalid JSON payload']);
            return;
        }

        if ($event === 'ping') {
            $this->respondJson(200, ['success' => true, 'code' => 'PONG']);
            return;
        }

        $order_id = (int)($data['external_order_id'] ?? 0);
        $external_status = $data['external_status'] ?? null;
        $crm_task_public_id = (string)($data['crm_task_public_id'] ?? '');

        if ($order_id <= 0) {
            $this->respondJson(422, ['error' => 'Missing external_order_id']);
            return;
        }

        $this->load->model('checkout/order');
        $order_info = $this->model_checkout_order->getOrder($order_id);

        if (!$order_info) {
            $this->respondJson(404, ['error' => 'Order not found: ' . $order_id]);
            return;
        }

        $target_status_id = null;
        if ($external_status !== null && is_numeric($external_status)) {
            $target_status_id = (int)$external_status;
        } else {
            $mapping = (array)$this->config->get('module_tropatt_status_mapping');
            $new_crm_status = (string)($data['new_status'] ?? '');
            foreach ($mapping as $oc_id => $crm_code) {
                if (strcasecmp((string)$crm_code, $new_crm_status) === 0) {
                    $target_status_id = (int)$oc_id;
                    break;
                }
            }
        }

        if ($target_status_id === null) {
            $this->respondJson(200, ['success' => true, 'notice' => 'Status change ignored: no mapping for ' . ($data['new_status'] ?? 'unknown')]);
            return;
        }

        self::$suppress_echo = true;
        try {
            $comment = 'Статус обновлен из TropaTT CRM';
            if ($crm_task_public_id !== '') {
                $comment .= ' (Задача: ' . $crm_task_public_id . ')';
            }
            $this->model_checkout_order->addHistory($order_id, $target_status_id, $comment, false);
        } finally {
            self::$suppress_echo = false;
        }

        $this->respondJson(200, ['success' => true, 'order_id' => $order_id, 'updated_status_id' => $target_status_id]);
    }

    private function respondJson(int $status_code, array $data): void {
        $this->response->addHeader('HTTP/1.1 ' . $status_code);
        $this->response->addHeader('Content-Type: application/json; charset=utf-8');
        $this->response->setOutput(json_encode($data));
    }
}
