<?php
/**
 * Stock sync: resolves SKUs to inventory item ids and pushes quantities through
 * the Admin GraphQL API (`inventorySetQuantities`).
 *
 *   php /path/to/connector/cron/sync_stock.php [stock.json]
 */

require_once dirname(__DIR__) . '/lib/Config.php';
require_once dirname(__DIR__) . '/lib/SignatureValidator.php';
require_once dirname(__DIR__) . '/lib/CrmClient.php';
require_once dirname(__DIR__) . '/lib/ShopifySignature.php';
require_once dirname(__DIR__) . '/lib/TokenStore.php';
require_once dirname(__DIR__) . '/lib/GraphQL/Queries.php';
require_once dirname(__DIR__) . '/lib/ShopifyClient.php';
require_once dirname(__DIR__) . '/lib/StockSync.php';
require_once dirname(__DIR__) . '/lib/ConnectorLogger.php';

use Tropatt\Shopify\Config;
use Tropatt\Shopify\CrmClient;
use Tropatt\Shopify\ShopifyClient;
use Tropatt\Shopify\StockSync;

Config::load();

$source = isset($argv[1]) ? (string)$argv[1] : Config::stockSource();
if ($source === '') {
    fwrite(STDERR, "usage: php sync_stock.php <stock.json> (or set stock_source in config.php)\n");
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
$map = array();
foreach ((array)$rows as $row) {
    if (is_array($row) && !empty($row['sku']) && !empty($row['inventory_item_id'])) {
        $map[(string)$row['sku']] = (string)$row['inventory_item_id'];
    }
}

$quantities = StockSync::mapRows((array)$rows, $map);
if ($quantities === array()) {
    fwrite(STDERR, "no rows with a sku and inventory_item_id found\n");
    exit(1);
}

$client = new ShopifyClient();
$sent = 0;
foreach (StockSync::batches($quantities) as $batch) {
    $result = $client->setInventoryQuantities($batch, null, 'stock-' . gmdate('YmdHis'));
    if (!$result['success']) {
        fwrite(STDERR, 'Shopify API error: ' . $result['error'] . "\n");
        exit(2);
    }

    $wait = ShopifyClient::waitSeconds((array)$result['cost'], 100.0);
    if ($wait > 0) {
        usleep((int)($wait * 1000000));
    }

    $sent += count($batch);
}

echo 'updated inventory items: ' . $sent . PHP_EOL;
