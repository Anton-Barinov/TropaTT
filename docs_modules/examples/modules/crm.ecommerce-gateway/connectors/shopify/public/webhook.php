<?php
/**
 * Shopify webhook receiver.
 *
 * Verifies `X-Shopify-Hmac-Sha256`, maps `orders/*` payloads to the canonical
 * TropaTT contract and forwards them to the CRM. GDPR topics are answered
 * immediately (they must never fail). The answer is sent before the CRM
 * round-trip and a failed forward is spooled for the retry cron.
 */

require_once dirname(__DIR__) . '/lib/Config.php';
require_once dirname(__DIR__) . '/lib/SignatureValidator.php';
require_once dirname(__DIR__) . '/lib/CrmClient.php';
require_once dirname(__DIR__) . '/lib/FileQueue.php';
require_once dirname(__DIR__) . '/lib/ConnectorLogger.php';
require_once dirname(__DIR__) . '/lib/ShopifySignature.php';
require_once dirname(__DIR__) . '/lib/TokenStore.php';
require_once dirname(__DIR__) . '/lib/OrderMapper.php';
require_once dirname(__DIR__) . '/lib/StatusMapper.php';
require_once dirname(__DIR__) . '/lib/EchoGuard.php';
require_once dirname(__DIR__) . '/lib/GdprHandler.php';

use Tropatt\Shopify\Config;
use Tropatt\Shopify\ConnectorLogger;
use Tropatt\Shopify\EchoGuard;
use Tropatt\Shopify\FileQueue;
use Tropatt\Shopify\GdprHandler;
use Tropatt\Shopify\OrderMapper;
use Tropatt\Shopify\ShopifySignature;
use Tropatt\Shopify\StatusMapper;
use Tropatt\Shopify\TokenStore;

Config::load();

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

$rawBody = (string)file_get_contents('php://input');
$topic = isset($_SERVER['HTTP_X_SHOPIFY_TOPIC']) ? (string)$_SERVER['HTTP_X_SHOPIFY_TOPIC'] : '';
$shopDomain = isset($_SERVER['HTTP_X_SHOPIFY_SHOP_DOMAIN']) ? (string)$_SERVER['HTTP_X_SHOPIFY_SHOP_DOMAIN'] : '';

/**
 * @return void
 */
function tropatt_shopify_respond($status, array $payload)
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    if (function_exists('fastcgi_finish_request')) {
        fastcgi_finish_request();
    } else {
        if (ob_get_level() > 0) {
            @ob_end_flush();
        }
        @flush();
    }
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    tropatt_shopify_respond(405, array('status' => 'error', 'error' => 'POST only'));

    return;
}

if (!ShopifySignature::verifyWebhook(Config::apiSecret(), $rawBody, ShopifySignature::webhookHeader($_SERVER))) {
    tropatt_shopify_respond(401, array('status' => 'error', 'error' => 'Invalid webhook signature'));

    return;
}

$payload = json_decode($rawBody, true);
if (!is_array($payload)) {
    tropatt_shopify_respond(400, array('status' => 'error', 'error' => 'Invalid JSON payload'));

    return;
}

// Mandatory GDPR topics and the uninstall hook.
if (in_array($topic, array('customers/data_request', 'customers/redact', 'shop/redact'), true)) {
    list($status, $body) = GdprHandler::handle($topic, $payload);
    tropatt_shopify_respond($status, $body);

    return;
}

if ($topic === 'app/uninstalled') {
    TokenStore::remove();
    tropatt_shopify_respond(200, array('status' => 'ok', 'token_removed' => true));

    return;
}

tropatt_shopify_respond(200, array('status' => 'ok'));

if (!in_array($topic, array('orders/create', 'orders/updated', 'orders/cancelled'), true)) {
    return;
}

$payload['shop_domain'] = $shopDomain;

// Anti-echo: an order this connector has just fulfilled or cancelled comes back
// as `orders/updated`. Forwarding it again would bounce the stage into the CRM.
$orderGid = StatusMapper::orderGid($payload['id'] ?? '');
if ($orderGid !== null && EchoGuard::isOrderSuppressed($orderGid)) {
    ConnectorLogger::write('skipped the echo of a connector-initiated change', array('order_id' => $orderGid));

    return;
}

$statusId = (string)($payload['financial_status'] ?? '');

$canonical = OrderMapper::toCanonical($payload, $statusId === '' ? Config::defaultStage() : $statusId);

if (!Config::isConfigured()) {
    FileQueue::enqueue($canonical);

    return;
}

$result = (new \Tropatt\Shopify\CrmClient())->pushOrder($canonical);
if (!$result['success']) {
    FileQueue::enqueue($canonical);
}
