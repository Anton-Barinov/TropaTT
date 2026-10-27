<?php
/**
 * Retry cron: delivers the orders spooled by the webhook.
 *
 *   crontab: every 5 minutes run `php /path/to/connector/cron/retry_queue.php`
 */

require_once dirname(__DIR__) . '/lib/Config.php';
require_once dirname(__DIR__) . '/lib/SignatureValidator.php';
require_once dirname(__DIR__) . '/lib/CrmClient.php';
require_once dirname(__DIR__) . '/lib/FileQueue.php';
require_once dirname(__DIR__) . '/lib/ConnectorLogger.php';

use Tropatt\Tilda\Config;
use Tropatt\Tilda\ConnectorLogger;
use Tropatt\Tilda\CrmClient;
use Tropatt\Tilda\FileQueue;

Config::load();

if (!Config::isConfigured()) {
    fwrite(STDERR, "connector is not configured\n");
    exit(1);
}

$client = new CrmClient();
$delivered = 0;
$failed = 0;

foreach (FileQueue::listFiles() as $path) {
    $canonical = FileQueue::read($path);
    if ($canonical === null) {
        FileQueue::remove($path);
        continue;
    }

    $result = $client->pushOrder($canonical);
    if ($result['success']) {
        FileQueue::remove($path);
        $delivered++;
        continue;
    }

    $failed++;
    ConnectorLogger::write('retry failed', array('file' => basename($path), 'http_code' => $result['http_code']));
}

echo 'delivered: ' . $delivered . ', still queued: ' . FileQueue::count() . ', failed this run: ' . $failed . PHP_EOL;
