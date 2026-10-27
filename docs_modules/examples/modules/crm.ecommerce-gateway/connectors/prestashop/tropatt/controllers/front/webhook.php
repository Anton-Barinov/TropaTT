<?php

/**
 * Inbound CRM webhook:
 *   https://shop.example.com/index.php?fc=module&module=tropatt&controller=webhook
 *
 * The CRM signs the packet as base64(HMAC-SHA256(secret, timestamp . '.' . body)).
 * The controller verifies it, applies the mapped order state through the
 * PrestaShop state machine and suppresses the echo while doing so.
 */
class TropattWebhookModuleFrontController extends ModuleFrontController
{
    public function initContent()
    {
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

        if (Tools::strtoupper($_SERVER['REQUEST_METHOD']) !== 'POST') {
            $this->respond(405, array('error' => 'POST only'));

            return;
        }

        $config = TropattConfig::load();
        $rawBody = (string)file_get_contents('php://input');
        $timestamp = isset($_SERVER['HTTP_X_TROPATT_TIMESTAMP']) ? (string)$_SERVER['HTTP_X_TROPATT_TIMESTAMP'] : '';
        $signature = isset($_SERVER['HTTP_X_TROPATT_SIGNATURE']) ? (string)$_SERVER['HTTP_X_TROPATT_SIGNATURE'] : '';
        $event = isset($_SERVER['HTTP_X_TROPATT_EVENT']) ? (string)$_SERVER['HTTP_X_TROPATT_EVENT'] : '';

        if ($config['webhook_secret'] === '' || $signature === '' || $timestamp === '') {
            $this->respond(401, array('error' => 'Missing authentication headers'));

            return;
        }

        if (!TropattSignature::withinTolerance($timestamp)) {
            $this->respond(401, array('error' => 'Timestamp out of tolerance window'));

            return;
        }

        if (!TropattSignature::verifyWebhook($timestamp, $rawBody, $signature, $config['webhook_secret'])) {
            $this->respond(401, array('error' => 'Invalid cryptographic signature'));

            return;
        }

        $data = json_decode($rawBody, true);
        if (!is_array($data)) {
            $this->respond(400, array('error' => 'Invalid JSON payload'));

            return;
        }

        if ($event === 'ping') {
            $this->respond(200, array('success' => true, 'code' => 'PONG'));

            return;
        }

        $orderId = (int)(isset($data['external_order_id']) ? $data['external_order_id'] : 0);
        if ($orderId <= 0) {
            $this->respond(422, array('error' => 'Missing external_order_id'));

            return;
        }

        $stateId = !empty($data['external_status'])
            ? (string)$data['external_status']
            : TropattStatusMapper::prestashopStateFor($config['status_mapping'], (string)(isset($data['new_status']) ? $data['new_status'] : ''));

        if ($stateId === null || $stateId === '') {
            $this->respond(200, array('success' => true, 'notice' => 'Ignored: no mapping for status'));

            return;
        }

        $order = new Order($orderId);
        if (!Validate::isLoadedObject($order)) {
            $this->respond(404, array('error' => 'Order not found: ' . $orderId));

            return;
        }

        // Anti-echo: the state change below must not be pushed back to the CRM.
        TropattConfig::setEchoSuppressed(true);

        try {
            $order->setCurrentState((int)$stateId);
            $order->save();
        } catch (Exception $exception) {
            TropattConfig::setEchoSuppressed(false);
            TropattLogger::write($config, 'failed to apply the order state', array(
                'order_id' => $orderId,
                'state_id' => $stateId,
                'error' => $exception->getMessage(),
            ));
            $this->respond(500, array('error' => 'Failed to apply the status'));

            return;
        }

        TropattConfig::setEchoSuppressed(false);

        $this->respond(200, array('success' => true, 'order_id' => $orderId, 'new_status' => $stateId));
    }

    /**
     * @param int   $status
     * @param array $payload
     * @return void
     */
    private function respond($status, array $payload)
    {
        http_response_code($status);
        echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
}
