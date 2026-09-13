<?php
class ControllerExtensionModuleTropatt extends Controller {
    public static $suppress_echo = false;

    public function onOrderHistoryAdd(&$route, &$args, &$output) {
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

        $this->load->model('extension/module/tropatt');
        $this->model_extension_module_tropatt->pushOrderToCrm($order_id, $order_status_id);
    }

    public function webhook() {
        $raw_body = file_get_contents('php://input');
        $timestamp = isset($_SERVER['HTTP_X_TROPATT_TIMESTAMP']) ? (string)$_SERVER['HTTP_X_TROPATT_TIMESTAMP'] : '';
        $signature = isset($_SERVER['HTTP_X_TROPATT_SIGNATURE']) ? (string)$_SERVER['HTTP_X_TROPATT_SIGNATURE'] : '';
        $event = isset($_SERVER['HTTP_X_TROPATT_EVENT']) ? (string)$_SERVER['HTTP_X_TROPATT_EVENT'] : '';

        $store_secret = (string)$this->config->get('module_tropatt_store_secret');

        if (empty($store_secret) || empty($signature) || empty($timestamp)) {
            $this->respondJson(401, array('error' => 'Missing authentication headers'));
            return;
        }

        if (abs(time() - (int)$timestamp) > 300) {
            $this->respondJson(401, array('error' => 'Timestamp out of tolerance window'));
            return;
        }

        $expected = base64_encode(hash_hmac('sha256', $timestamp . '.' . $raw_body, $store_secret, true));
        if (!hash_equals($expected, $signature)) {
            $this->respondJson(401, array('error' => 'Invalid cryptographic signature'));
            return;
        }

        $data = json_decode($raw_body, true);
        if (!is_array($data)) {
            $this->respondJson(400, array('error' => 'Invalid JSON payload'));
            return;
        }

        if ($event === 'ping') {
            $this->respondJson(200, array('success' => true, 'code' => 'PONG'));
            return;
        }

        $order_id = (int)($data['external_order_id'] ?? 0);
        $external_status = $data['external_status'] ?? null;
        $crm_task_public_id = (string)($data['crm_task_public_id'] ?? '');

        if ($order_id <= 0) {
            $this->respondJson(422, array('error' => 'Missing external_order_id'));
            return;
        }

        $this->load->model('checkout/order');
        $order_info = $this->model_checkout_order->getOrder($order_id);

        if (!$order_info) {
            $this->respondJson(404, array('error' => 'Order not found: ' . $order_id));
            return;
        }

        $target_status_id = null;
        if ($external_status !== null && is_numeric($external_status)) {
            $target_status_id = (int)$external_status;
        } else {
            $mapping = (array)$this->config->get('module_tropatt_status_mapping');
            $new_crm_status = $data['new_status'] ?? '';
            foreach ($mapping as $oc_id => $crm_code) {
                if (strcasecmp((string)$crm_code, (string)$new_crm_status) === 0) {
                    $target_status_id = (int)$oc_id;
                    break;
                }
            }
        }

        if ($target_status_id === null) {
            $this->respondJson(200, array('success' => true, 'notice' => 'Status change ignored: no mapping for ' . ($data['new_status'] ?? 'unknown')));
            return;
        }

        self::$suppress_echo = true;
        try {
            $comment = 'Статус обновлен из TropaTT CRM';
            if ($crm_task_public_id !== '') {
                $comment .= ' (Задача: ' . $crm_task_public_id . ')';
            }
            $this->model_checkout_order->addOrderHistory($order_id, $target_status_id, $comment, false);
        } finally {
            self::$suppress_echo = false;
        }

        $this->respondJson(200, array('success' => true, 'order_id' => $order_id, 'updated_status_id' => $target_status_id));
    }

    private function respondJson($status_code, array $data) {
        $this->response->addHeader('HTTP/1.1 ' . $status_code);
        $this->response->addHeader('Content-Type: application/json; charset=utf-8');
        $this->response->setOutput(json_encode($data));
    }
}
