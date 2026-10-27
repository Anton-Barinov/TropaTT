<?php
/**
 * TropaTT CRM — gateway connector for OpenCart / ocStore 1.5.x.
 *
 * Inbound side: the CRM webhook (`order.status_changed`) and the signed
 * stock batch endpoint (`syncStock`). Both are authenticated with the store
 * secret (HMAC-SHA256), never with an admin session token.
 */
class ControllerModuleTropatt extends Controller {
    /**
     * Second entry point of the outbound push, used by OpenCart 1.5 installs whose
     * vQmod is unavailable: `$this->load->controller(
     * 'module/tropatt/fallbackOrderHistory', array($order_id, $order_status_id))`
     * from `catalog/model/checkout/order.php` (see README, section «Альтернатива vQmod»).
     *
     * The echo guard lives in the model (`ModelModuleTropatt::$suppress_echo`), because
     * vQmod-injected model code cannot reach controller state.
     *
     * @param array<int,mixed>|int $data
     * @param int $order_status_id
     * @return void
     */
    public function fallbackOrderHistory($data = array(), $order_status_id = 0) {
        if (is_array($data)) {
            $order_id = isset($data[0]) ? (int)$data[0] : 0;
            $order_status_id = isset($data[1]) ? (int)$data[1] : (int)$order_status_id;
        } else {
            $order_id = (int)$data;
            $order_status_id = (int)$order_status_id;
        }

        if ($order_id <= 0) {
            return;
        }

        $this->load->model('module/tropatt');
        $this->model_module_tropatt->handleOrderStatusChange($order_id, $order_status_id);
    }

    /**
     * Inbound CRM webhook: route=module/tropatt/webhook
     *
     * Signature scheme of the gateway outbound webhooks (E-COM-04):
     *   X-TropaTT-Signature = base64(HMAC-SHA256(secret, timestamp . '.' . raw_body))
     *
     * @return void
     */
    public function webhook() {
        $raw_body = $this->rawBody();
        $timestamp = isset($this->request->server['HTTP_X_TROPATT_TIMESTAMP']) ? (string)$this->request->server['HTTP_X_TROPATT_TIMESTAMP'] : '';
        $signature = isset($this->request->server['HTTP_X_TROPATT_SIGNATURE']) ? (string)$this->request->server['HTTP_X_TROPATT_SIGNATURE'] : '';
        $event = isset($this->request->server['HTTP_X_TROPATT_EVENT']) ? (string)$this->request->server['HTTP_X_TROPATT_EVENT'] : '';

        if (!$this->verifySignature($raw_body, $timestamp, $signature, $error)) {
            $this->respondJson($error['status'], array('error' => $error['message']));
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

        if (!$this->config->get('module_tropatt_status')) {
            $this->respondJson(200, array('success' => true, 'notice' => 'TropaTT module is disabled, event ignored'));
            return;
        }

        $order_id = isset($data['external_order_id']) ? (int)$data['external_order_id'] : 0;
        $external_status = isset($data['external_status']) ? $data['external_status'] : null;
        $crm_task_public_id = isset($data['crm_task_public_id']) ? (string)$data['crm_task_public_id'] : '';

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
            $new_crm_status = isset($data['new_status']) ? (string)$data['new_status'] : '';

            if ($new_crm_status !== '') {
                foreach ($mapping as $oc_id => $crm_code) {
                    if (strcasecmp((string)$crm_code, $new_crm_status) === 0) {
                        $target_status_id = (int)$oc_id;
                        break;
                    }
                }
            }
        }

        if ($target_status_id === null) {
            $this->respondJson(200, array(
                'success' => true,
                'notice' => 'Status change ignored: no mapping for ' . (isset($data['new_status']) ? (string)$data['new_status'] : 'unknown'),
            ));
            return;
        }

        if ((int)$order_info['order_status_id'] === $target_status_id) {
            $this->respondJson(200, array(
                'success' => true,
                'order_id' => $order_id,
                'updated_status_id' => $target_status_id,
                'notice' => 'Status already set, nothing to do',
            ));
            return;
        }

        // Guard the push that our own status write would otherwise trigger: the vQmod
        // hook runs inside the order model, so the flag is switched on the model.
        // OpenCart 1.5 runs on PHP 5.3, which has no exception-finalisation block, so
        // the flag is restored on both paths explicitly.
        $this->load->model('module/tropatt');
        $this->model_module_tropatt->suppressEcho(true);

        $comment = 'Статус обновлен из TropaTT CRM';
        if ($crm_task_public_id !== '') {
            $comment .= ' (Задача: ' . $crm_task_public_id . ')';
        }

        try {
            // OpenCart 1.5 signature:
            // update($order_id, $order_status_id, $comment = '', $notify = false).
            // The 2.x admin-side method that writes a status has a different name and
            // is not part of the 1.5 storefront model at all.
            $this->model_checkout_order->update($order_id, $target_status_id, $comment, false);
        } catch (Exception $exception) {
            $this->model_module_tropatt->suppressEcho(false);
            $this->respondJson(500, array('error' => 'Failed to apply the status: ' . $exception->getMessage()));
            return;
        }

        $this->model_module_tropatt->suppressEcho(false);

        $this->respondJson(200, array(
            'success' => true,
            'order_id' => $order_id,
            'updated_status_id' => $target_status_id,
        ));
    }

    /**
     * Signed stock batch: route=module/tropatt/syncStock
     *
     * `mode=pull` (default) returns one page of the catalogue stock read as a
     * consistent snapshot; `mode=push` applies a submitted batch inside one
     * transaction with row locks. Both accept GET and POST; the signature is
     * always computed over the raw request body (empty for GET).
     *
     * @return void
     */
    public function syncStock() {
        $raw_body = $this->rawBody();
        $timestamp = isset($this->request->server['HTTP_X_TROPATT_TIMESTAMP']) ? (string)$this->request->server['HTTP_X_TROPATT_TIMESTAMP'] : '';
        $signature = isset($this->request->server['HTTP_X_TROPATT_SIGNATURE']) ? (string)$this->request->server['HTTP_X_TROPATT_SIGNATURE'] : '';

        if (!$this->verifySignature($raw_body, $timestamp, $signature, $error)) {
            $this->respondJson($error['status'], array('error' => $error['message']));
            return;
        }

        if (!$this->config->get('module_tropatt_status')) {
            $this->respondJson(409, array('error' => 'TropaTT module is disabled'));
            return;
        }

        $body = array();
        if (trim($raw_body) !== '') {
            $body = json_decode($raw_body, true);
            if (!is_array($body)) {
                $this->respondJson(400, array('error' => 'Invalid JSON payload'));
                return;
            }
        }

        $mode = '';
        if (isset($body['mode'])) {
            $mode = (string)$body['mode'];
        } elseif (isset($this->request->get['mode'])) {
            $mode = (string)$this->request->get['mode'];
        }
        if ($mode === '') {
            $mode = 'pull';
        }

        $page = 1;
        $limit = 50;
        foreach (array('page', 'limit') as $key) {
            if (isset($body[$key])) {
                ${$key} = (int)$body[$key];
            } elseif (isset($this->request->get[$key])) {
                ${$key} = (int)$this->request->get[$key];
            }
        }

        $this->load->model('module/tropatt');

        if ($mode === 'pull') {
            $this->respondJson(200, $this->model_module_tropatt->getStockPage($page, $limit));
            return;
        }

        if ($mode !== 'push') {
            $this->respondJson(400, array('error' => 'Unknown mode: ' . $mode, 'supported' => array('pull', 'push')));
            return;
        }

        $lines = isset($body['items']) ? $body['items'] : (isset($body['lines']) ? $body['lines'] : null);
        if (!is_array($lines)) {
            $this->respondJson(422, array('error' => 'mode=push requires an "items" array'));
            return;
        }

        $page = max(1, $page);
        $limit = min(200, max(1, $limit));
        $slice = array_slice(array_values($lines), ($page - 1) * $limit, $limit);

        $dry_run = !empty($body['dry_run']);

        try {
            $result = $this->model_module_tropatt->applyStockBatch($slice, $dry_run);
        } catch (Exception $exception) {
            $this->respondJson(500, array('error' => 'Stock batch failed', 'message' => $exception->getMessage()));
            return;
        }

        $result['page'] = $page;
        $result['limit'] = $limit;
        $result['total'] = count($lines);
        $result['has_more'] = (($page - 1) * $limit + count($slice)) < count($lines);

        $this->respondJson(200, $result);
    }

    /**
     * Raw request body. Extracted so the signature and the JSON payload are
     * verifiable from a test harness (php://input is not readable in CLI).
     *
     * @return string
     */
    protected function rawBody() {
        return (string)file_get_contents('php://input');
    }

    /**
     * @param string $raw_body
     * @param string $timestamp
     * @param string $signature
     * @param array{status:int,message:string}|null $error
     * @return bool
     */
    private function verifySignature($raw_body, $timestamp, $signature, &$error) {
        $error = null;

        $store_secret = (string)$this->config->get('module_tropatt_store_secret');

        if ($store_secret === '' || $signature === '' || $timestamp === '') {
            $error = array('status' => 401, 'message' => 'Missing authentication headers');
            return false;
        }

        if (abs(time() - (int)$timestamp) > 300) {
            $error = array('status' => 401, 'message' => 'Timestamp out of tolerance window');
            return false;
        }

        $this->load->model('module/tropatt');
        $expected = base64_encode(hash_hmac('sha256', $timestamp . '.' . $raw_body, $store_secret, true));

        if (!$this->model_module_tropatt->secureEquals($expected, $signature)) {
            $error = array('status' => 401, 'message' => 'Invalid cryptographic signature');
            return false;
        }

        return true;
    }

    /**
     * @param int $status_code
     * @param array<string,mixed> $data
     * @return void
     */
    private function respondJson($status_code, array $data) {
        $this->response->addHeader('HTTP/1.1 ' . (int)$status_code);
        $this->response->addHeader('Content-Type: application/json; charset=utf-8');
        $this->response->addHeader('Cache-Control: no-store');
        $this->response->setOutput(json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }
}
