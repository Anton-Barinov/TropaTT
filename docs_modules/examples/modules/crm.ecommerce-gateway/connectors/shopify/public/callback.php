<?php
/**
 * OAuth2 callback: validates the request, exchanges the code for an expiring
 * offline access token (`expiring=1`, so a refresh token comes back too) and
 * subscribes the shop to the order and GDPR webhooks.
 */

require_once dirname(__DIR__) . '/lib/Config.php';
require_once dirname(__DIR__) . '/lib/SignatureValidator.php';
require_once dirname(__DIR__) . '/lib/CrmClient.php';
require_once dirname(__DIR__) . '/lib/ShopifySignature.php';
require_once dirname(__DIR__) . '/lib/TokenStore.php';
require_once dirname(__DIR__) . '/lib/OAuthClient.php';
require_once dirname(__DIR__) . '/lib/GraphQL/Queries.php';
require_once dirname(__DIR__) . '/lib/ShopifyClient.php';
require_once dirname(__DIR__) . '/lib/ConnectorLogger.php';

use Tropatt\Shopify\Config;
use Tropatt\Shopify\GraphQL\Queries;
use Tropatt\Shopify\OAuthClient;
use Tropatt\Shopify\ShopifyClient;
use Tropatt\Shopify\ShopifySignature;

Config::load();

header('Content-Type: text/plain; charset=utf-8');

$shop = ShopifySignature::normalizeShop($_GET['shop'] ?? '');
$code = (string)($_GET['code'] ?? '');
$state = (string)($_GET['state'] ?? '');
$expectedState = (string)@file_get_contents(dirname(__DIR__) . '/var/oauth-state.txt');

if (!ShopifySignature::isValidShop($shop) || $code === '') {
    http_response_code(400);
    echo 'Missing shop or code' . PHP_EOL;

    return;
}

if (!ShopifySignature::verifyQuery(Config::apiSecret(), $_GET)) {
    http_response_code(401);
    echo 'The request HMAC is invalid' . PHP_EOL;

    return;
}

if (!ShopifySignature::verifyState($expectedState, $state)) {
    http_response_code(401);
    echo 'The state parameter does not match' . PHP_EOL;

    return;
}

$result = (new OAuthClient($shop))->exchangeCode($code);
if (!$result['success']) {
    http_response_code(400);
    echo 'Token exchange failed: ' . $result['error'] . PHP_EOL;

    return;
}

$webhookUrl = rtrim((string)Config::get('webhook_url', ''), '/');
if ($webhookUrl === '') {
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $webhookUrl = $scheme . '://' . (string)$_SERVER['HTTP_HOST'] . '/shopify/webhook.php';
}

$client = new ShopifyClient($shop, (string)$result['token']['access_token']);
foreach (Queries::topics() as $topic) {
    // `WebhookSubscriptionInput` takes `uri`: `callbackUrl` was removed when the
    // field was renamed, and passing it fails the whole subscription.
    $client->query(Queries::webhookSubscriptionCreate(), array(
        'topic' => $topic,
        'webhookSubscription' => array('uri' => $webhookUrl),
    ));
}

$expiring = isset($result['token']['expires_in']) ? (int)$result['token']['expires_in'] : 0;
echo 'Installed. Token stored (' . ($expiring > 0 ? 'expiring in ' . $expiring . 's, refresh token saved' : 'non-expiring')
    . '), webhooks subscribed to ' . $webhookUrl . PHP_EOL;
