<?php

namespace Tropatt\Crm;

/**
 * Incoming CRM webhook: `POST /tropatt/v1/webhook`.
 *
 * The CRM signs the packet as base64(HMAC-SHA256(secret, timestamp . '.' . body));
 * the handler answers with JSON and never lets a replay be applied twice.
 */
class WebhookHandler
{
    /**
     * @return array{0:int,1:array}
     */
    public static function handle(array $server, $rawBody)
    {
        $timestamp = isset($server['HTTP_X_TROPATT_TIMESTAMP']) ? (string)$server['HTTP_X_TROPATT_TIMESTAMP'] : '';
        $signature = isset($server['HTTP_X_TROPATT_SIGNATURE']) ? (string)$server['HTTP_X_TROPATT_SIGNATURE'] : '';
        $event = isset($server['HTTP_X_TROPATT_EVENT']) ? (string)$server['HTTP_X_TROPATT_EVENT'] : '';

        $secret = Config::storeSecret();

        if ($secret === '' || $signature === '' || $timestamp === '') {
            return array(401, array('error' => 'Missing authentication headers'));
        }

        if (!SignatureValidator::withinTolerance($timestamp)) {
            return array(401, array('error' => 'Timestamp out of tolerance window'));
        }

        if (!SignatureValidator::verifyWebhook($timestamp, $rawBody, $signature, $secret)) {
            return array(401, array('error' => 'Invalid cryptographic signature'));
        }

        if (self::isReplay($signature, $timestamp)) {
            return array(409, array('error' => 'Replay detected: this packet was already processed'));
        }

        $data = json_decode((string)$rawBody, true);
        if (!is_array($data)) {
            return array(400, array('error' => 'Invalid JSON payload'));
        }

        if ($event === 'ping') {
            return array(200, array('success' => true, 'code' => 'PONG'));
        }

        $orderId = (int)($data['external_order_id'] ?? 0);
        if ($orderId <= 0) {
            return array(422, array('error' => 'Missing external_order_id'));
        }

        $mapping = Config::statusMapping();
        $statusId = StatusMapper::resolveIncoming($mapping, $data);

        if ($statusId === null) {
            return array(200, array('success' => true, 'notice' => 'Ignored: no mapping for status'));
        }

        $order = self::loadOrder($orderId);
        if ($order === null) {
            return array(404, array('error' => 'Order not found: ' . $orderId));
        }

        $comment = 'Статус обновлен из TropaTT CRM';
        if (!empty($data['crm_task_public_id'])) {
            $comment .= ' (Задача: ' . (string)$data['crm_task_public_id'] . ')';
        }

        if (!OrderHandler::applyStatus($order, $statusId, $comment)) {
            return array(500, array('error' => 'Failed to apply the status'));
        }

        return array(200, array('success' => true, 'order_id' => $orderId, 'new_status' => $statusId));
    }

    /**
     * Replay guard based on the module's cache.
     *
     * @return bool
     */
    public static function isReplay($signature, $timestamp)
    {
        if (!class_exists('\Bitrix\Main\Data\Cache')) {
            return false;
        }

        $cache = \Bitrix\Main\Data\Cache::createInstance();
        $key = 'tropatt_wh_' . md5((string)$signature . '|' . (string)$timestamp);

        if ($cache->initCache(600, $key, '/tropatt.crm')) {
            return true;
        }

        $cache->startDataCache();
        $cache->endDataCache(array('seen' => true));

        return false;
    }

    /**
     * @return object|null
     */
    private static function loadOrder($orderId)
    {
        if (!class_exists('\Bitrix\Sale\Order')) {
            return null;
        }

        try {
            return \Bitrix\Sale\Order::load((int)$orderId);
        } catch (\Throwable $exception) {
            ConnectorLogger::write('order load failed', array('order_id' => (int)$orderId, 'error' => $exception->getMessage()));

            return null;
        }
    }
}
