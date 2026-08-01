<?php
declare(strict_types=1);

require_once __DIR__ . '/../../system/library/support/Autoloader.php';

$autoloader = new Api\System\Library\Support\Autoloader(dirname(__DIR__, 2));
$autoloader->register();

use Api\System\Library\Config;
use Api\System\Library\Logger\JsonLogger;
use Api\System\Library\Service\AiSemanticIndexService;

function unitAssert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

try {
    $tmpBase = sys_get_temp_dir() . '/crm_ai_semantic_' . bin2hex(random_bytes(4));
    @mkdir($tmpBase, 0775, true);

    $config = new Config();
    $config->merge('default', [
        'storage' => [
            'base' => $tmpBase,
        ],
    ]);

    $logger = new JsonLogger([]);
    $service = new AiSemanticIndexService($config, $logger);

    $indexed = $service->indexDocument('doc_unit_1', 'semantic index text', ['scope' => 'unit']);
    unitAssert((bool)($indexed['ok'] ?? false) === true, 'Index document must succeed');

    $expected = $tmpBase . '/ai/cache/semantic-index.json';
    unitAssert(is_file($expected), 'Semantic index file must be stored under default.storage.base/ai/cache');

    $raw = (string)file_get_contents($expected);
    $decoded = json_decode($raw, true);
    unitAssert(is_array($decoded), 'Semantic index file must contain valid JSON');
    unitAssert(isset($decoded['doc_unit_1']), 'Indexed document must be present in semantic index file');

    echo "[OK] ai_semantic_index_storage_base_unit\n";
} catch (Throwable $e) {
    fwrite(STDERR, '[FAIL] ai_semantic_index_storage_base_unit: ' . $e->getMessage() . "\n");
    exit(1);
}
