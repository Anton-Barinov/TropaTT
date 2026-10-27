<?php
/**
 * Stock and price sync: pushes a JSON stock file to the Tilda Store API.
 *
 *   php /path/to/connector/cron/sync_stock.php [path-or-url]
 *
 * The source is a JSON document with either a list of rows
 * `[{"sku": "ART-1", "quantity": 5, "price_minor": 199000}]` or an object with a
 * `products`/`items` list. `stock_source` in `config.php` sets the default path.
 */

require_once dirname(__DIR__) . '/lib/Config.php';
require_once dirname(__DIR__) . '/lib/CrmClient.php';
require_once dirname(__DIR__) . '/lib/StoreApiClient.php';
require_once dirname(__DIR__) . '/lib/ConnectorLogger.php';

use Tropatt\Tilda\Config;
use Tropatt\Tilda\CrmClient;
use Tropatt\Tilda\StoreApiClient;

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

if ($raw === null || $raw === '') {
    fwrite(STDERR, "could not read the stock source: " . $source . "\n");
    exit(1);
}

$data = json_decode($raw, true);
if (!is_array($data)) {
    fwrite(STDERR, "the stock source is not valid JSON\n");
    exit(1);
}

$rows = isset($data['products']) ? $data['products'] : (isset($data['items']) ? $data['items'] : $data);

$products = StoreApiClient::mapStockRows((array)$rows);
if ($products === array()) {
    fwrite(STDERR, "no products with a sku found in the source\n");
    exit(1);
}

$result = (new StoreApiClient())->pushProducts($products);

if (!$result['success']) {
    fwrite(STDERR, 'Tilda Store API error: ' . (string)$result['error'] . "\n");
    exit(2);
}

echo 'pushed products: ' . (int)$result['sent'] . PHP_EOL;
