<?php
/**
 * Token refresh cron.
 *
 * Expiring offline access tokens live for one hour (the response's `expires_in`
 * is authoritative) and a background connector has no merchant session to
 * re-authenticate, so the refresh token is the only way to stay connected.
 *
 * crontab: every 20 minutes run `php /path/to/connector/cron/refresh_token.php`
 *
 * Exit codes: 0 — nothing to do or refreshed; 1 — no token stored (install the
 * app); 2 — refresh failed (a 401 means the merchant must reinstall).
 */

require_once dirname(__DIR__) . '/lib/Config.php';
require_once dirname(__DIR__) . '/lib/SignatureValidator.php';
require_once dirname(__DIR__) . '/lib/CrmClient.php';
require_once dirname(__DIR__) . '/lib/ShopifySignature.php';
require_once dirname(__DIR__) . '/lib/TokenStore.php';
require_once dirname(__DIR__) . '/lib/OAuthClient.php';
require_once dirname(__DIR__) . '/lib/ConnectorLogger.php';

use Tropatt\Shopify\Config;
use Tropatt\Shopify\ConnectorLogger;
use Tropatt\Shopify\OAuthClient;
use Tropatt\Shopify\TokenStore;

Config::load();

$token = TokenStore::load();
if ($token === array()) {
    fwrite(STDERR, "no token stored: install the app (public/install.php?shop=...)\n");
    exit(1);
}

if (!TokenStore::hasRefreshToken($token)) {
    // A non-expiring token (a custom app, or an install made before the
    // connector started asking for expiring ones) has nothing to refresh.
    echo "token is non-expiring: nothing to refresh\n";
    exit(0);
}

$expiresAt = TokenStore::expiresAt($token);
if (!TokenStore::needsRefresh($token, time(), OAuthClient::REFRESH_SKEW_SECONDS)) {
    echo 'access token still valid for ' . max(0, (int)$expiresAt - time()) . "s\n";
    exit(0);
}

$client = new OAuthClient($token['shop'] ?? null);
$result = $client->refresh((string)$token['refresh_token']);

if (!$result['success']) {
    ConnectorLogger::write('shopify token refresh failed', array('error' => $result['error']));

    if (!empty($result['reauth_required'])) {
        fwrite(STDERR, "refresh token rejected: the merchant must reinstall the app\n");
        exit(2);
    }

    fwrite(STDERR, 'refresh failed: ' . $result['error'] . "\n");
    exit(2);
}

$fresh = (int)($result['token']['expires_in'] ?? 0);
echo "token refreshed, valid for {$fresh}s\n";
