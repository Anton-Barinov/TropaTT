<?php

/**
 * Inbound CRM webhook: `https://shop.example.com/tropatt/webhook/`.
 *
 * The CRM signs the packet as base64(HMAC-SHA256(secret, timestamp . '.' . body)).
 * The action verifies it, applies the mapped Shop-Script workflow action through
 * `shopWorkflow::getAction()->run()` and suppresses the echo with a storage flag.
 */
class shopTropattPluginFrontendWebhookAction extends waViewAction
{
    public function execute()
    {
        wa()->getResponse()->addHeader('Content-Type', 'application/json; charset=utf-8');
        wa()->getResponse()->addHeader('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');

        if (waRequest::method() !== 'post') {
            $this->respond(405, array('error' => 'POST only'));

            return;
        }

        $config = shopTropattConfig::load();
        $rawBody = (string)file_get_contents('php://input');
        $timestamp = (string)waRequest::server('HTTP_X_TROPATT_TIMESTAMP', '');
        $signature = (string)waRequest::server('HTTP_X_TROPATT_SIGNATURE', '');
        $event = (string)waRequest::server('HTTP_X_TROPATT_EVENT', '');

        if ($config['webhook_secret'] === '' || $signature === '' || $timestamp === '') {
            $this->respond(401, array('error' => 'Missing authentication headers'));

            return;
        }

        if (!shopTropattSignature::withinTolerance($timestamp)) {
            $this->respond(401, array('error' => 'Timestamp out of tolerance window'));

            return;
        }

        if (!shopTropattSignature::verifyWebhook($timestamp, $rawBody, $signature, $config['webhook_secret'])) {
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

        $orderId = (int)($data['external_order_id'] ?? 0);
        if ($orderId <= 0) {
            $this->respond(422, array('error' => 'Missing external_order_id'));

            return;
        }

        $actionId = !empty($data['external_status'])
            ? (string)$data['external_status']
            : shopTropattStatusMapper::webasystActionFor($config['status_mapping'], (string)($data['new_status'] ?? ''));

        if ($actionId === null || $actionId === '') {
            $this->respond(200, array('success' => true, 'notice' => 'Ignored: no mapping for status'));

            return;
        }

        // Anti-echo: the workflow action below must not be pushed back to the CRM.
        wa()->getStorage()->write(shopTropattPlugin::STORAGE_KEY, true);

        try {
            $action = shopWorkflow::getAction($actionId);
            if (!$action) {
                wa()->getStorage()->delete(shopTropattPlugin::STORAGE_KEY);
                $this->respond(422, array('error' => 'Unknown workflow action: ' . $actionId));

                return;
            }

            $action->run($orderId);
        } catch (Exception $exception) {
            wa()->getStorage()->delete(shopTropattPlugin::STORAGE_KEY);
            shopTropattLogger::write($config, 'failed to run the workflow action', array(
                'order_id' => $orderId,
                'action' => $actionId,
                'error' => $exception->getMessage(),
            ));
            $this->respond(500, array('error' => 'Failed to apply the status'));

            return;
        }

        wa()->getStorage()->delete(shopTropattPlugin::STORAGE_KEY);
        $this->respond(200, array('success' => true, 'order_id' => $orderId, 'new_status' => $actionId));
    }

    /**
     * @param int   $status
     * @param array $payload
     * @return void
     */
    private function respond($status, array $payload)
    {
        wa()->getResponse()->setStatus($status);
        wa()->getResponse()->setBody(json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        exit;
    }
}
