<?php

if (PHP_SAPI !== "cli") { http_response_code(404); exit; }
declare(strict_types=1);

use Api\Model\Client\ClientRepository;
use Api\System\Library\Config;
use Api\System\Library\Database\ConnectionManager;
use Api\System\Library\Support\Autoloader;

$basePath = dirname(__DIR__);

require_once $basePath . '/system/library/support/Autoloader.php';
$autoloader = new Autoloader($basePath);
$autoloader->register();

$config = new Config();
$config->load($basePath . '/config/default.php', 'default');
$config->load($basePath . '/config/database.php', 'database');
$config->load($basePath . '/config/install.php', 'install');
$config->load($basePath . '/config/database.local.php', 'database');

$pdo = (new ConnectionManager($config))->connect();

if (!tableExists($pdo, 'clients')) {
    fwrite(STDERR, "Client data quality report cannot run: required table clients is missing. Apply database migrations first.\n");
    exit(2);
}

$repo = new ClientRepository($pdo);

$duplicates = $repo->duplicatesReport();
$quality = $repo->dataQualitySummary();

$report = [
    'generated_at' => gmdate('c'),
    'summary' => [
        'clients_total' => (int)($quality['clients_total'] ?? 0),
        'duplicate_groups' => (int)($duplicates['summary']['duplicate_groups'] ?? 0),
    ],
    'duplicates' => $duplicates['duplicates'] ?? [],
    'quality' => $quality,
];

$storage = (array)$config->get('default.storage', []);
$storageBase = trim((string)($storage['base'] ?? ''));
if ($storageBase === '') {
    $storageBase = dirname($basePath) . '/storage_api';
}
$outputDir = rtrim($storageBase, '/') . '/generated/reports';
if (!is_dir($outputDir)) {
    @mkdir($outputDir, 0775, true);
}

$json = json_encode($report, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
if (!is_string($json)) {
    fwrite(STDERR, "Failed to encode report to JSON\n");
    exit(1);
}

$timestamp = gmdate('Ymd_His');
$versionedPath = $outputDir . '/client_data_quality_' . $timestamp . '.json';
$latestPath = $outputDir . '/client_data_quality_latest.json';
file_put_contents($versionedPath, $json);
file_put_contents($latestPath, $json);

echo "Client data quality report generated\n";
echo "latest=" . $latestPath . "\n";
echo "versioned=" . $versionedPath . "\n";

function tableExists(PDO $pdo, string $table): bool
{
    try {
        return $pdo->query('SELECT 1 FROM ' . $table . ' WHERE 1=0') !== false;
    } catch (Throwable) {
        return false;
    }
}
