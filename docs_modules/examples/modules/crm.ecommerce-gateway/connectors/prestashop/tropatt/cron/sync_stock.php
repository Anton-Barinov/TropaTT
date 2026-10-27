<?php
/**
 * CLI/cron: pushes a JSON stock file into PrestaShop.
 *
 *   php /path/to/modules/tropatt/cron/sync_stock.php /path/to/stock.json [id_shop]
 *
 * The JSON may be a list of rows `[{"sku": "REF-1", "quantity": 5}]` or an
 * object with a `products`/`items` list.
 */

require_once dirname(__FILE__) . '/../../../config/config.inc.php';
require_once dirname(__FILE__) . '/../classes/TropattSignature.php';
require_once dirname(__FILE__) . '/../classes/TropattFileQueue.php';
require_once dirname(__FILE__) . '/../classes/TropattLogger.php';
require_once dirname(__FILE__) . '/../classes/TropattConfig.php';
require_once dirname(__FILE__) . '/../classes/TropattStockSync.php';

if (!defined('PHP_SAPI') || PHP_SAPI !== 'cli') {
    exit(1);
}

$source = isset($argv[1]) ? (string)$argv[1] : '';
$idShop = isset($argv[2]) ? (int)$argv[2] : null;

if ($source === '' || !is_file($source)) {
    fwrite(STDERR, "usage: php sync_stock.php <stock.json> [id_shop]\n");
    exit(1);
}

$data = json_decode((string)file_get_contents($source), true);
if (!is_array($data)) {
    fwrite(STDERR, "the stock source is not valid JSON\n");
    exit(1);
}

// Multi-Store: switch the context so setQuantity() writes to the right shop.
if ($idShop && class_exists('Shop')) {
    Shop::setContext(Shop::CONTEXT_SHOP, $idShop);
}

$rows = isset($data['products']) ? $data['products'] : (isset($data['items']) ? $data['items'] : $data);
$lines = TropattStockSync::mapRows((array)$rows);

if ($lines === array()) {
    fwrite(STDERR, "no rows with a sku found\n");
    exit(1);
}

$results = TropattStockSync::applyBatch($lines, $idShop);
$updated = 0;
foreach ($results as $result) {
    if ($result['result'] === 'updated') {
        $updated++;
    }
}

echo 'updated: ' . $updated . ' of ' . count($results) . PHP_EOL;
