<?php
/**
 * Inbound CRM webhook: `index.php?ajax=tropattWebhook` (also reachable as
 * `mg-plugins/tropatt/ajax.php` on shops that route plugin ajax that way).
 *
 * The CRM signs the packet as base64(HMAC-SHA256(secret, timestamp . '.' . body)).
 * The plugin verifies it, applies the mapped Moguta status through
 * `Models_Order::updateOrder()` and suppresses the echo with a global flag.
 */

if (!defined('MG')) {
    die('Access denied');
}

require_once __DIR__ . '/lib/Config.php';
require_once __DIR__ . '/lib/SignatureValidator.php';
require_once __DIR__ . '/lib/StatusMapper.php';
require_once __DIR__ . '/lib/ConnectorLogger.php';

use Tropatt\Moguta\Config;
use Tropatt\Moguta\ConnectorLogger;
use Tropatt\Moguta\SignatureValidator;
use Tropatt\Moguta\StatusMapper;

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(array('error' => 'POST only'));
    return;
}

$config = Config::current();
$rawBody = (string)file_get_contents('php://input');
$timestamp = isset($_SERVER['HTTP_X_TROPATT_TIMESTAMP']) ? (string)$_SERVER['HTTP_X_TROPATT_TIMESTAMP'] : '';
$signature = isset($_SERVER['HTTP_X_TROPATT_SIGNATURE']) ? (string)$_SERVER['HTTP_X_TROPATT_SIGNATURE'] : '';
$event = isset($_SERVER['HTTP_X_TROPATT_EVENT']) ? (string)$_SERVER['HTTP_X_TROPATT_EVENT'] : '';

if ($config['webhook_secret'] === '' || $signature === '' || $timestamp === '') {
    http_response_code(401);
    echo json_encode(array('error' => 'Missing authentication headers'));
    return;
}

if (!SignatureValidator::withinTolerance($timestamp)) {
    http_response_code(401);
    echo json_encode(array('error' => 'Timestamp out of tolerance window'));
    return;
}

if (!SignatureValidator::verifyWebhook($timestamp, $rawBody, $signature, $config['webhook_secret'])) {
    http_response_code(401);
    echo json_encode(array('error' => 'Invalid cryptographic signature'));
    return;
}

$data = json_decode($rawBody, true);
if (!is_array($data)) {
    http_response_code(400);
    echo json_encode(array('error' => 'Invalid JSON payload'));
    return;
}

if ($event === 'ping') {
    echo json_encode(array('success' => true, 'code' => 'PONG'));
    return;
}

$orderId = (int)($data['external_order_id'] ?? 0);
if ($orderId <= 0) {
    http_response_code(422);
    echo json_encode(array('error' => 'Missing external_order_id'));
    return;
}

$status = !empty($data['external_status'])
    ? (string)$data['external_status']
    : StatusMapper::mogutaStatusFor($config['status_mapping'], (string)($data['new_status'] ?? ''));

if ($status === null || $status === '') {
    echo json_encode(array('success' => true, 'notice' => 'Ignored: no mapping for status'));
    return;
}

// Anti-echo: the status write below must not be pushed back to the CRM.
$GLOBALS['tropatt_crm_skip_status_sync'] = true;

try {
    $order = new Models_Order();
    $order->updateOrder($orderId, array('status' => $status));
} catch (Exception $exception) {
    $GLOBALS['tropatt_crm_skip_status_sync'] = false;
    ConnectorLogger::write($config, 'failed to apply the status', array(
        'order_id' => $orderId,
        'error' => $exception->getMessage(),
    ));
    http_response_code(500);
    echo json_encode(array('error' => 'Failed to apply the status'));
    return;
}

$GLOBALS['tropatt_crm_skip_status_sync'] = false;

echo json_encode(array('success' => true, 'order_id' => $orderId, 'new_status' => $status));
