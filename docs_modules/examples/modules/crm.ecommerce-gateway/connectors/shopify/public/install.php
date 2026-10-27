<?php
/**
 * Shopify app install entry point.
 *
 * Open `https://your-host/shopify/install.php?shop=my-shop.myshopify.com` to start
 * the OAuth flow; Shopify redirects back to `public/callback.php`.
 */

require_once dirname(__DIR__) . '/lib/Config.php';
require_once dirname(__DIR__) . '/lib/SignatureValidator.php';
require_once dirname(__DIR__) . '/lib/CrmClient.php';
require_once dirname(__DIR__) . '/lib/ShopifySignature.php';
require_once dirname(__DIR__) . '/lib/TokenStore.php';
require_once dirname(__DIR__) . '/lib/OAuthClient.php';
require_once dirname(__DIR__) . '/lib/ConnectorLogger.php';

use Tropatt\Shopify\Config;
use Tropatt\Shopify\OAuthClient;
use Tropatt\Shopify\ShopifySignature;

Config::load();

header('Content-Type: text/plain; charset=utf-8');

$shop = ShopifySignature::normalizeShop($_GET['shop'] ?? Config::get('shopify_shop', ''));

if (!ShopifySignature::isValidShop($shop)) {
    http_response_code(400);
    echo 'A valid ?shop=my-shop.myshopify.com parameter is required' . PHP_EOL;

    return;
}

$state = ShopifySignature::state();
@file_put_contents(dirname(__DIR__) . '/var/oauth-state.txt', $state);

$client = new OAuthClient($shop);
echo 'Redirect to Shopify: ' . $client->redirectUrl($state) . PHP_EOL;
