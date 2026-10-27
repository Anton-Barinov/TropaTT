<?php
/**
 * Tilda Cart Webhook receiver.
 *
 * Configure in Tilda: Сайт → Настройки → Формы → Cart → Webhook URL =
 * https://your-host/tilda/webhook.php and set the same secret token in
 * `config.php` (`tilda_secret`).
 *
 * The answer is sent before the CRM round-trip, so Tilda always gets its
 * `{"status":"ok"}` in time; if the forward fails, the order is spooled to
 * `var/queue/` and retried by `cron/retry_queue.php`.
 */

require_once dirname(__DIR__) . '/lib/Config.php';
require_once dirname(__DIR__) . '/lib/SignatureValidator.php';
require_once dirname(__DIR__) . '/lib/IpAllowlist.php';
require_once dirname(__DIR__) . '/lib/TildaMapper.php';
require_once dirname(__DIR__) . '/lib/CrmClient.php';
require_once dirname(__DIR__) . '/lib/FileQueue.php';
require_once dirname(__DIR__) . '/lib/ConnectorLogger.php';

use Tropatt\Tilda\Config;
use Tropatt\Tilda\ConnectorLogger;
use Tropatt\Tilda\CrmClient;
use Tropatt\Tilda\FileQueue;
use Tropatt\Tilda\IpAllowlist;
use Tropatt\Tilda\SignatureValidator;
use Tropatt\Tilda\TildaMapper;

Config::load();

// Bound the body before buffering or JSON decoding. Content-Length is only an
// early rejection hint: chunked requests are still capped while streaming.
define('TROPATT_TILDA_MAX_BODY_BYTES', 1048576);

function tropatt_tilda_read_bounded_body($maxBytes)
{
    $contentLength = isset($_SERVER['CONTENT_LENGTH']) ? (int)$_SERVER['CONTENT_LENGTH'] : null;
    if ($contentLength !== null && $contentLength > $maxBytes) {
        return null;
    }

    $stream = fopen('php://input', 'rb');
    if ($stream === false) {
        return false;
    }

    $raw = stream_get_contents($stream, $maxBytes + 1);
    fclose($stream);
    if ($raw === false || strlen($raw) > $maxBytes) {
        return null;
    }
    if ($contentLength !== null && $contentLength !== strlen($raw)) {
        return false;
    }

    return $raw;
}

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

$clientIp = IpAllowlist::clientIp($_SERVER);

/**
 * @return void
 */
function tropatt_tilda_respond($status, array $payload)
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
    tropatt_tilda_respond(405, array('status' => 'error', 'error' => 'POST only'));

    return;
}

if (!IpAllowlist::matches(Config::allowedIps(), $clientIp)) {
    ConnectorLogger::write('webhook rejected: ip not allowed', array('ip' => $clientIp));
    tropatt_tilda_respond(403, array('status' => 'error', 'error' => 'IP is not allowed'));

    return;
}

$rawBody = tropatt_tilda_read_bounded_body(TROPATT_TILDA_MAX_BODY_BYTES);
if ($rawBody === null) {
    tropatt_tilda_respond(413, array('status' => 'error', 'error' => 'Request body too large'));
    return;
}
if ($rawBody === false) {
    tropatt_tilda_respond(400, array('status' => 'error', 'error' => 'Invalid request body'));
    return;
}

$cart = json_decode($rawBody, true);
if (!is_array($cart)) {
    tropatt_tilda_respond(400, array('status' => 'error', 'error' => 'Invalid JSON payload'));

    return;
}

$secret = Config::tildaSecret();
if ($secret !== '') {
    $given = (string)($cart['secret'] ?? ($_SERVER['HTTP_X_TILDA_SECRET'] ?? ''));
    if ($given === '' || !SignatureValidator::secureEquals($secret, $given)) {
        ConnectorLogger::write('webhook rejected: bad secret token');
        tropatt_tilda_respond(401, array('status' => 'error', 'error' => 'Invalid secret token'));

        return;
    }
}

// Answer Tilda first: the cart must never wait for the CRM.
tropatt_tilda_respond(200, array('status' => 'ok'));

$canonical = TildaMapper::toCanonical($cart, (string)Config::get('default_stage', 'new'));

if (!Config::isConfigured()) {
    FileQueue::enqueue($canonical);
    ConnectorLogger::write('gateway is not configured, order spooled', array('external_id' => $canonical['external_id']));

    return;
}

$result = (new CrmClient())->pushOrder($canonical);

if ($result['success']) {
    ConnectorLogger::write('order delivered', array('external_id' => $canonical['external_id'], 'code' => $result['code']));

    return;
}

FileQueue::enqueue($canonical);
ConnectorLogger::write('order delivery failed, spooled', array(
    'external_id' => $canonical['external_id'],
    'http_code' => $result['http_code'],
    'error' => $result['error'],
));
