<?php
/**
 * Stock and price sync: pushes a JSON stock file to the InSales API
 * (`PUT /admin/products/variants_group_update.json`).
 *
 *   php /path/to/connector/cron/sync_stock.php [path-or-url]
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
use Tropatt\InSales\CrmClient;
use Tropatt\InSales\InSalesClient;

Config::load();

$source = isset($argv[1]) ? (string)$argv[1] : Config::stockSource();
if ($source === '') {
    fwrite(STDERR, "usage: php sync_stock.php <path-or-url> (or set stock_source in config.php)\n");
    exit(1);
}

$raw = null;
if (preg_match('#^https?://#i', $source)) {
    $response = CrmClient::send('GET', $source, '');
    $raw = $response['success'] ? $response['body'] : null;
} elseif (is_file($source)) {
    $raw = (string)file_get_contents($source);
}

$data = $raw === null ? null : json_decode($raw, true);
if (!is_array($data)) {
    fwrite(STDERR, "could not read a JSON stock source: " . $source . "\n");
    exit(1);
}

$rows = isset($data['products']) ? $data['products'] : (isset($data['items']) ? $data['items'] : $data);
$variants = InSalesClient::mapStockRows((array)$rows);

if ($variants === array()) {
    fwrite(STDERR, "no variants with a sku found in the source\n");
    exit(1);
}

$result = (new InSalesClient())->pushStock($variants);

if (!$result['success']) {
    fwrite(STDERR, 'InSales API error: ' . (string)$result['error'] . "\n");
    exit(2);
}

echo 'updated variants: ' . (int)$result['sent'] . PHP_EOL;
