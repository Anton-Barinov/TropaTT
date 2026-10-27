<?php
/**
 * Inbound CRM status receiver: the target of the store's `webhook_url`.
 *
 * The CRM signs the packet as
 *   base64(HMAC-SHA256(store_secret, timestamp . '.' . raw_body))
 * and sends it in `X-TropaTT-Signature` together with `X-TropaTT-Timestamp`
 * and `X-TropaTT-Event` (see EcommerceWebhookJob::deliver()).
 *
 * Body (order.status_changed):
 *   {
 *     "event": "order.status_changed",
 *     "store_key": "...",
 *     "external_order_id": "555000111",
 *     "crm_task_public_id": "...",
 *     "old_status": "in_progress",
 *     "new_status": "done",
 *     "external_status": "done",
 *     "sync_initiator": "crm",
 *     "timestamp": 1234567890
 *   }
 *
 * `external_status` comes from the core mapper and may hold the CRM stage code
 * itself, so the connector falls back to its own `status_mapping` when the value
 * is not one of the Shopify actions (see StatusApplier::resolveAction()).
 */

require_once dirname(__DIR__) . '/lib/Config.php';
require_once dirname(__DIR__) . '/lib/SignatureValidator.php';
require_once dirname(__DIR__) . '/lib/CrmClient.php';
require_once dirname(__DIR__) . '/lib/ConnectorLogger.php';
require_once dirname(__DIR__) . '/lib/ShopifySignature.php';
require_once dirname(__DIR__) . '/lib/TokenStore.php';
require_once dirname(__DIR__) . '/lib/GraphQL/Queries.php';
require_once dirname(__DIR__) . '/lib/ShopifyClient.php';
require_once dirname(__DIR__) . '/lib/StatusMapper.php';
require_once dirname(__DIR__) . '/lib/StatusApplier.php';
require_once dirname(__DIR__) . '/lib/EchoGuard.php';

use Tropatt\Shopify\Config;
use Tropatt\Shopify\ConnectorLogger;
use Tropatt\Shopify\EchoGuard;
use Tropatt\Shopify\ShopifyClient;
use Tropatt\Shopify\SignatureValidator;
use Tropatt\Shopify\StatusApplier;
use Tropatt\Shopify\StatusMapper;

Config::load();

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

/**
 * @return void
 */
function tropatt_shopify_status_respond($status, array $payload)
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    if (function_exists('fastcgi_finish_request')) {
        fastcgi_finish_request();
    }
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    tropatt_shopify_status_respond(405, array('error' => 'POST only'));

    return;
}

$rawBody = (string)file_get_contents('php://input');
$timestamp = isset($_SERVER['HTTP_X_TROPATT_TIMESTAMP']) ? (string)$_SERVER['HTTP_X_TROPATT_TIMESTAMP'] : '';
$signature = isset($_SERVER['HTTP_X_TROPATT_SIGNATURE']) ? (string)$_SERVER['HTTP_X_TROPATT_SIGNATURE'] : '';
$event = isset($_SERVER['HTTP_X_TROPATT_EVENT']) ? (string)$_SERVER['HTTP_X_TROPATT_EVENT'] : '';

$secret = Config::storeSecret();

if ($secret === '' || $signature === '' || $timestamp === '') {
    tropatt_shopify_status_respond(401, array('error' => 'Missing authentication headers'));

    return;
}

if (!SignatureValidator::withinTolerance($timestamp)) {
    tropatt_shopify_status_respond(401, array('error' => 'Timestamp out of tolerance window'));

    return;
}

if (!SignatureValidator::verifyWebhook($timestamp, $rawBody, $signature, $secret)) {
    tropatt_shopify_status_respond(401, array('error' => 'Invalid cryptographic signature'));

    return;
}

if (EchoGuard::isPacketReplayed($signature, $timestamp)) {
    tropatt_shopify_status_respond(409, array('error' => 'Replay detected: this packet was already processed'));

    return;
}

$data = json_decode($rawBody, true);
if (!is_array($data)) {
    tropatt_shopify_status_respond(400, array('error' => 'Invalid JSON payload'));

    return;
}

EchoGuard::markPacket($signature, $timestamp);

if ($event === 'ping') {
    tropatt_shopify_status_respond(200, array('success' => true, 'code' => 'PONG'));

    return;
}

$orderGid = StatusMapper::orderGid($data['external_order_id'] ?? '');
if ($orderGid === null) {
    tropatt_shopify_status_respond(422, array('error' => 'Missing external_order_id'));

    return;
}

$mapping = StatusMapper::decode((string)Config::get('status_mapping', StatusMapper::defaultMapping()));
$action = StatusApplier::resolveAction($data['external_status'] ?? '', $data['new_status'] ?? '', $mapping);

if ($action === null || $action === StatusMapper::ACTION_NOOP) {
    tropatt_shopify_status_respond(200, array(
        'success' => true,
        'notice' => 'Ignored: no Shopify action for the stage',
        'new_status' => (string)($data['new_status'] ?? ''),
    ));

    return;
}

// The action below makes Shopify fire `orders/updated`; mark the order so the
// connector does not push the result straight back to the CRM.
EchoGuard::markOrder($orderGid);

$result = StatusApplier::apply($action, $orderGid, new ShopifyClient(), array(
    'notify_customer' => (bool)Config::get('notify_customer', false),
    'cancel_refund' => (bool)Config::get('cancel_refund', false),
    'cancel_restock' => (bool)Config::get('cancel_restock', true),
));

ConnectorLogger::write('crm status applied', array(
    'action' => $action,
    'order_id' => $orderGid,
    'applied' => $result['applied'],
    'error' => $result['error'],
));

if (!$result['applied']) {
    // Nothing to fulfil is not a transport failure: answering 5xx would make the
    // CRM retry a change that can never succeed.
    $isNoop = strpos((string)$result['error'], 'Nothing left to fulfil') === 0;

    tropatt_shopify_status_respond($isNoop ? 200 : 500, array(
        'success' => $isNoop,
        'action' => $action,
        'order_id' => $orderGid,
        'error' => $result['error'],
        'detail' => $result['detail'],
    ));

    return;
}

tropatt_shopify_status_respond(200, array(
    'success' => true,
    'action' => $action,
    'order_id' => $orderGid,
    'detail' => $result['detail'],
));
