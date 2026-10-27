<?php
/**
 * InSales application install endpoint (OAuth2 redirect target).
 *
 * 1. Open `https://your-host/insales/install.php?shop=my-shop.myinsales.ru` to start the flow.
 * 2. InSales redirects back with `?code=...`; the connector exchanges it for an access token,
 *    stores the token and subscribes the shop to the `orders/*` webhooks.
 */

require_once dirname(__DIR__) . '/lib/Config.php';
require_once dirname(__DIR__) . '/lib/SignatureValidator.php';
require_once dirname(__DIR__) . '/lib/CrmClient.php';
require_once dirname(__DIR__) . '/lib/TokenStore.php';
require_once dirname(__DIR__) . '/lib/RateLimiter.php';
require_once dirname(__DIR__) . '/lib/OAuthClient.php';
require_once dirname(__DIR__) . '/lib/InSalesClient.php';
require_once dirname(__DIR__) . '/lib/ConnectorLogger.php';

use Tropatt\InSales\Config;
use Tropatt\InSales\ConnectorLogger;
use Tropatt\InSales\InSalesClient;
use Tropatt\InSales\OAuthClient;

Config::load();

header('Content-Type: text/plain; charset=utf-8');

$code = isset($_GET['code']) ? (string)$_GET['code'] : '';
$shop = isset($_GET['shop']) ? (string)$_GET['shop'] : Config::shop();

// The shop domain may come from the app store parameter (`?shop=...`); it is used
// for this request only and is expected to be configured in config.php as well.

$client = new OAuthClient($shop);

if ($code === '') {
    echo 'Redirect to InSales: ' . $client->authorizeUrlForRedirect() . PHP_EOL;

    return;
}

$result = $client->exchangeCode($code);
if (!$result['success']) {
    ConnectorLogger::write('oauth exchange failed', array('error' => $result['error']));
    http_response_code(400);
    echo 'OAuth error: ' . $result['error'] . PHP_EOL;

    return;
}

// Subscribe the shop to the order webhooks (orders/create, orders/update, orders/destroy).
$webhookUrl = rtrim(Config::get('webhook_url', ''), '/');
if ($webhookUrl === '') {
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $webhookUrl = $scheme . '://' . (string)$_SERVER['HTTP_HOST'] . '/insales/webhook.php';
}

$api = new InSalesClient($shop);

// The documented topics are orders/create and orders/update; orders/destroy is
// not part of the published webhook model (SDKs list only the two order topics),
// so it is attempted as a best-effort extra and never fails the installation.
$requiredTopics = array('orders/create', 'orders/update');
$optionalTopics = array('orders/destroy');
$subscribed = array();
$failedTopics = array();

foreach (array_merge($requiredTopics, $optionalTopics) as $topic) {
    $result = $api->request('POST', '/admin/webhooks.json', array(
        'webhook' => array('address' => $webhookUrl, 'topic' => $topic, 'format_type' => 'json'),
    ));

    if ($result['success']) {
        $subscribed[] = $topic;
        continue;
    }

    $failedTopics[] = $topic . ' (' . (int)$result['http_code'] . ')';
    ConnectorLogger::write('webhook subscription failed', array('topic' => $topic, 'http_code' => $result['http_code']));
}

if ($failedTopics !== array()) {
    fwrite(STDERR, 'Warning: some webhook topics could not be subscribed: ' . implode(', ', $failedTopics) . PHP_EOL);
}

$missingRequired = array_diff($requiredTopics, $subscribed);
if ($missingRequired !== array()) {
    http_response_code(502);
    echo 'InSales rejected the required webhook subscriptions: ' . implode(', ', $missingRequired) . PHP_EOL;
    echo 'Check the webhook URL (' . $webhookUrl . ') and the application credentials.' . PHP_EOL;

    return;
}

echo 'Installed. Access token stored, webhooks subscribed to ' . $webhookUrl
    . ' (topics: ' . implode(', ', $subscribed) . ')' . PHP_EOL;
