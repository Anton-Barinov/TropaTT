<?php
/**
 * InSales webhook receiver (topics: orders/create, orders/update, orders/destroy).
 *
 * The shop signs the raw body with the application password
 * (`X-InSales-Webhook-Signature`); the order is then mapped to the canonical
 * TropaTT payload and forwarded to the CRM. The answer is sent before the
 * CRM round-trip and a failed forward is spooled for the retry cron.
 */

require_once dirname(__DIR__) . '/lib/Config.php';
require_once dirname(__DIR__) . '/lib/SignatureValidator.php';
require_once dirname(__DIR__) . '/lib/CrmClient.php';
require_once dirname(__DIR__) . '/lib/FileQueue.php';
require_once dirname(__DIR__) . '/lib/ConnectorLogger.php';
require_once dirname(__DIR__) . '/lib/WebhookVerifier.php';
require_once dirname(__DIR__) . '/lib/InSalesMapper.php';
require_once dirname(__DIR__) . '/lib/StatusMapper.php';

use Tropatt\InSales\Config;
use Tropatt\InSales\ConnectorLogger;
use Tropatt\InSales\CrmClient;
use Tropatt\InSales\FileQueue;
use Tropatt\InSales\InSalesMapper;
use Tropatt\InSales\StatusMapper;
use Tropatt\InSales\WebhookVerifier;

Config::load();

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

$rawBody = (string)file_get_contents('php://input');

/**
 * @return void
 */
function tropatt_insales_respond($status, array $payload)
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
    tropatt_insales_respond(405, array('status' => 'error', 'error' => 'POST only'));

    return;
}

$verificationMode = strtolower(trim((string)Config::get('webhook_verification', 'auto')));
if ($verificationMode === 'off') {
    ConnectorLogger::write('webhook verification is disabled (webhook_verification=off)');
}

if (!WebhookVerifier::verifyRequest(array(
    'webhook_verification' => $verificationMode,
    'webhook_secret' => (string)Config::get('webhook_secret', ''),
    'insales_app_password' => Config::appPassword(),
), $rawBody, $_SERVER, $_GET)) {
    ConnectorLogger::write('webhook rejected: invalid signature or token');
    tropatt_insales_respond(401, array('status' => 'error', 'error' => 'Invalid webhook signature'));

    return;
}

$payload = json_decode($rawBody, true);
if (!is_array($payload)) {
    tropatt_insales_respond(400, array('status' => 'error', 'error' => 'Invalid JSON payload'));

    return;
}

// InSales wraps the entity: {"order": {...}} for order topics.
$order = isset($payload['order']) && is_array($payload['order']) ? $payload['order'] : $payload;

tropatt_insales_respond(200, array('status' => 'ok'));

if (!Config::isConfigured()) {
    FileQueue::enqueue(InSalesMapper::toCanonical($order));

    return;
}

$mapping = StatusMapper::decode((string)Config::get('status_mapping', ''));
$statusId = (string)($order['fulfillment_status'] ?? '');
$stage = StatusMapper::crmStageFor($mapping, $statusId);
$canonical = InSalesMapper::toCanonical($order, $stage === null ? (string)Config::get('default_stage', 'new') : $stage);

$result = (new CrmClient())->pushOrder($canonical);
if ($result['success']) {
    return;
}

FileQueue::enqueue($canonical);
