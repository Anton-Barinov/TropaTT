<?php
declare(strict_types=1);

require __DIR__ . '/_bootstrap.php';

use Api\System\Library\Config;
use Api\System\Library\Logger\JsonLogger;
use Api\System\Library\Service\AiSemanticIndexService;

function semanticSearchFlagByCode(array $items, string $code): array
{
    foreach ($items as $item) {
        if (is_array($item) && (string)($item['code'] ?? '') === $code) {
            return $item;
        }
    }

    throw new RuntimeException('Missing feature flag: ' . $code);
}

try {
    $root = loginRoot();
    $headers = authHeaders($root['token']);

    $flagsResponse = request('GET', '/api/v1/feature-flags', [], $headers);
    assertTrue($flagsResponse['status'] === 200, 'Feature flags list must return 200');
    $flagItems = (array)($flagsResponse['payload']['data']['items'] ?? []);
    $flagSnapshots = [];
    foreach (['ai.enabled', 'ai.search'] as $flagCode) {
        $flag = semanticSearchFlagByCode($flagItems, $flagCode);
        $flagSnapshots[$flagCode] = $flag;
        $enabled = request('PATCH', '/api/v1/feature-flags/' . (string)$flag['public_id'], ['is_enabled' => 1], $headers);
        assertTrue($enabled['status'] === 200, 'Enable ' . $flagCode . ' must return 200');
    }

    $suffix = randomSuffix();
    $task = request('POST', '/api/v1/tasks', [
        'title' => 'Semantic endpoint task ' . $suffix,
        'description' => 'Unique semantic text ' . $suffix,
        'status' => 'new',
        'priority' => 'normal',
    ], $headers);
    assertTrue($task['status'] === 201 || $task['status'] === 200, 'Task create must succeed for semantic search setup');
    $taskPublicId = (string)($task['payload']['data']['task']['public_id'] ?? $task['payload']['data']['public_id'] ?? '');
    assertTrue($taskPublicId !== '', 'Created task public_id is required');

    $storageBase = trim((string)getenv('CRM_STORAGE_BASE'));
    if ($storageBase === '') {
        $storageBase = dirname(__DIR__, 3) . '/storage';
    }
    $config = new Config();
    $config->merge('default', [
        'storage' => [
            'base' => $storageBase,
        ],
    ]);
    $semanticIndex = new AiSemanticIndexService($config, new JsonLogger([]));
    $indexed = $semanticIndex->indexEntityDocument('task', $taskPublicId, 'semantic endpoint needle ' . $suffix, [
        'visibility' => 'test',
    ]);
    assertTrue((bool)($indexed['ok'] ?? false) === true, 'Index task document must succeed');

    $search = request('POST', '/api/v1/ai/search/semantic', [
        'query' => 'semantic endpoint needle ' . $suffix,
        'limit' => 5,
    ], $headers);
    assertTrue($search['status'] === 200, 'Semantic search endpoint must return 200');
    assertTrue((string)($search['payload']['code'] ?? '') === 'AI_SEMANTIC_SEARCH_RESULTS', 'Semantic search code must be stable');
    $items = (array)($search['payload']['data']['items'] ?? []);
    assertTrue(count($items) >= 1, 'Semantic search must return indexed accessible task');
    assertTrue((string)($items[0]['entity_type'] ?? '') === 'task', 'Semantic search result entity_type must be singular task');
    assertTrue((string)($items[0]['entity_public_id'] ?? '') === $taskPublicId, 'Semantic search result must point to created task public_id');
    assertTrue(!array_key_exists('id', (array)$items[0]), 'Semantic search result must not expose internal id');
    assertTrue(!array_key_exists('embedding', (array)$items[0]), 'Semantic search result must not expose embedding payload');
    assertTrue(!array_key_exists('vector', (array)$items[0]), 'Semantic search result must not expose vector payload');
    assertTrue(!array_key_exists('meta', (array)$items[0]), 'Semantic search result must not expose raw index metadata');

    $archive = request('PATCH', '/api/v1/tasks/' . $taskPublicId, [
        'archived' => 1,
    ], $headers);
    assertTrue($archive['status'] === 200, 'Archiving indexed task must return 200');
    $reindexed = $semanticIndex->indexEntityDocument('task', $taskPublicId, 'semantic endpoint archived needle ' . $suffix);
    assertTrue((bool)($reindexed['ok'] ?? false) === true, 'Reindex archived task fixture must succeed');

    $hiddenArchived = request('POST', '/api/v1/ai/search/semantic', [
        'query' => 'semantic endpoint archived needle ' . $suffix,
        'limit' => 5,
    ], $headers);
    assertTrue($hiddenArchived['status'] === 200, 'Semantic search archived default must return 200');
    assertTrue((array)($hiddenArchived['payload']['data']['items'] ?? []) === [], 'Archived semantic result must be hidden by default');

    $explicitArchived = request('POST', '/api/v1/ai/search/semantic', [
        'query' => 'semantic endpoint archived needle ' . $suffix,
        'include_archived' => 1,
        'limit' => 5,
    ], $headers);
    assertTrue($explicitArchived['status'] === 200, 'Semantic search include_archived must return 200');
    assertTrue(count((array)($explicitArchived['payload']['data']['items'] ?? [])) >= 1, 'Archived semantic result can be returned with explicit include_archived');

    $semanticIndex->removeEntityDocument('task', $taskPublicId);
    request('DELETE', '/api/v1/tasks/' . $taskPublicId, [], $headers);
    foreach ($flagSnapshots as $flagCode => $snapshot) {
        request('PATCH', '/api/v1/feature-flags/' . (string)$snapshot['public_id'], [
            'is_enabled' => (bool)($snapshot['is_enabled'] ?? false) ? 1 : 0,
        ], $headers);
    }

    echo "[OK] ai_semantic_search_endpoint_smoke\n";
} catch (Throwable $e) {
    fwrite(STDERR, '[FAIL] ai_semantic_search_endpoint_smoke: ' . $e->getMessage() . "\n");
    exit(1);
}
