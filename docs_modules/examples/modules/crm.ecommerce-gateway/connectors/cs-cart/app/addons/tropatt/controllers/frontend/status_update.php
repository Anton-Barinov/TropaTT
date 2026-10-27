<?php
/**
 * Inbound CRM webhook: `index.php?dispatch=tropatt.webhook`.
 *
 * The CRM signs the packet as base64(HMAC-SHA256(secret, timestamp . '.' . body));
 * the handler verifies it, applies the mapped CS-Cart status and suppresses the
 * echo through `Registry::set('runtime.tropatt_skip_status_sync', true)`.
 */

use Tropatt\CsCart\Config;
use Tropatt\CsCart\ConnectorLogger;
use Tropatt\CsCart\SignatureValidator;
use Tropatt\CsCart\StatusMapper;

if (!defined('BOOTSTRAP')) {
    die('Access denied');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Content-Type: application/json; charset=utf-8');
    http_response_code(405);
    echo json_encode(array('error' => 'POST only'));
    exit;
}

$config = Config::fromRegistry();
$rawBody = (string)file_get_contents('php://input');
$timestamp = isset($_SERVER['HTTP_X_TROPATT_TIMESTAMP']) ? (string)$_SERVER['HTTP_X_TROPATT_TIMESTAMP'] : '';
$signature = isset($_SERVER['HTTP_X_TROPATT_SIGNATURE']) ? (string)$_SERVER['HTTP_X_TROPATT_SIGNATURE'] : '';
$event = isset($_SERVER['HTTP_X_TROPATT_EVENT']) ? (string)$_SERVER['HTTP_X_TROPATT_EVENT'] : '';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

/**
 * @return void
 */
function fn_tropatt_webhook_respond($status, array $payload)
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if ($config['webhook_secret'] === '' || $signature === '' || $timestamp === '') {
    fn_tropatt_webhook_respond(401, array('error' => 'Missing authentication headers'));
}

if (!SignatureValidator::withinTolerance($timestamp)) {
    fn_tropatt_webhook_respond(401, array('error' => 'Timestamp out of tolerance window'));
}

if (!SignatureValidator::verifyWebhook($timestamp, $rawBody, $signature, $config['webhook_secret'])) {
    fn_tropatt_webhook_respond(401, array('error' => 'Invalid cryptographic signature'));
}

$data = json_decode($rawBody, true);
if (!is_array($data)) {
    fn_tropatt_webhook_respond(400, array('error' => 'Invalid JSON payload'));
}

if ($event === 'ping') {
    fn_tropatt_webhook_respond(200, array('success' => true, 'code' => 'PONG'));
}

$orderId = (int)($data['external_order_id'] ?? 0);
if ($orderId <= 0) {
    fn_tropatt_webhook_respond(422, array('error' => 'Missing external_order_id'));
}

$status = !empty($data['external_status'])
    ? (string)$data['external_status']
    : StatusMapper::csCartStatusFor($config['status_mapping'], (string)($data['new_status'] ?? ''));

if ($status === null || $status === '') {
    fn_tropatt_webhook_respond(200, array('success' => true, 'notice' => 'Ignored: no mapping for status'));
}

$orderInfo = fn_get_order_info($orderId);
if (empty($orderInfo)) {
    fn_tropatt_webhook_respond(404, array('error' => 'Order not found: ' . $orderId));
}

// Anti-echo: the status change below must not be pushed back to the CRM.
Registry::set('runtime.tropatt_skip_status_sync', true);
fn_change_order_status($orderId, $status, 'TropaTT CRM');
Registry::set('runtime.tropatt_skip_status_sync', false);

ConnectorLogger::write($config, 'status applied from the CRM', array(
    'order_id' => $orderId,
    'status' => $status,
));

fn_tropatt_webhook_respond(200, array('success' => true, 'order_id' => $orderId, 'new_status' => $status));
