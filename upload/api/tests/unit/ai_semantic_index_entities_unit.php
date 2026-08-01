<?php
declare(strict_types=1);

require_once __DIR__ . '/../../system/library/support/Autoloader.php';

$autoloader = new Api\System\Library\Support\Autoloader(dirname(__DIR__, 2));
$autoloader->register();

use Api\System\Library\Config;
use Api\System\Library\Logger\JsonLogger;
use Api\System\Library\Service\AiSemanticIndexService;

function semanticEntitiesAssert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

try {
    $tmpBase = sys_get_temp_dir() . '/crm_ai_semantic_entities_' . bin2hex(random_bytes(4));
    @mkdir($tmpBase, 0775, true);

    $config = new Config();
    $config->merge('default', [
        'storage' => [
            'base' => $tmpBase,
        ],
    ]);

    $service = new AiSemanticIndexService($config, new JsonLogger([]));
    $types = $service->indexableEntityTypes();
    foreach (['tasks', 'projects', 'clients', 'companies', 'contacts', 'comments', 'files'] as $requiredType) {
        semanticEntitiesAssert(in_array($requiredType, $types, true), 'Missing indexable entity type: ' . $requiredType);
    }

    semanticEntitiesAssert($service->isIndexableEntityType('task'), 'Singular task alias must be indexable');
    semanticEntitiesAssert($service->isIndexableEntityType('files'), 'Files must be indexable');
    semanticEntitiesAssert(!$service->isIndexableEntityType('api_clients'), 'Security/admin entities must not be indexable by default');

    $indexed = $service->indexEntityDocument('task', 'task_public_1', 'semantic task text', [
        'secret' => 'must-not-persist',
        'visibility' => 'internal',
    ]);
    semanticEntitiesAssert((bool)($indexed['ok'] ?? false) === true, 'Indexing allowed task entity must succeed');

    $rejected = $service->indexEntityDocument('api_clients', 'client_1', 'secret text');
    semanticEntitiesAssert((bool)($rejected['ok'] ?? false) === false, 'Indexing non-allowlisted entity must fail');
    semanticEntitiesAssert((string)($rejected['code'] ?? '') === 'AI_SEMANTIC_ENTITY_NOT_INDEXABLE', 'Rejected entity code must be stable');

    $fileIndexed = $service->indexEntityDocument('file', 'file_public_1', 'Proposal.pdf metadata: signed estimate, page count 4', [
        'original_name' => 'Proposal.pdf',
        'mime_type' => 'application/pdf',
        'storage_path' => '/private/storage/file.pdf',
        'content_base64' => base64_encode('binary must not persist'),
        'raw_text' => 'raw extracted body must not persist in meta',
    ]);
    semanticEntitiesAssert((bool)($fileIndexed['ok'] ?? false) === true, 'Indexing file metadata text must succeed');

    $fileBinaryRejected = $service->indexEntityDocument('file', 'file_public_2', str_repeat('QUJD', 180));
    semanticEntitiesAssert((bool)($fileBinaryRejected['ok'] ?? false) === false, 'Base64-like file text must be rejected');
    semanticEntitiesAssert((string)($fileBinaryRejected['code'] ?? '') === 'AI_SEMANTIC_FILE_TEXT_NOT_ALLOWED', 'Unsafe file text rejection code must be stable');

    $raw = (string)file_get_contents($tmpBase . '/ai/cache/semantic-index.json');
    $decoded = json_decode($raw, true);
    semanticEntitiesAssert(is_array($decoded), 'Semantic entity index must contain valid JSON');
    semanticEntitiesAssert(isset($decoded['tasks:task_public_1']), 'Entity document key must include normalized entity type');
    $meta = is_array($decoded['tasks:task_public_1']['meta'] ?? null) ? $decoded['tasks:task_public_1']['meta'] : [];
    semanticEntitiesAssert(($meta['entity_type'] ?? '') === 'tasks', 'Meta must include normalized entity type');
    semanticEntitiesAssert(($meta['entity_public_id'] ?? '') === 'task_public_1', 'Meta must include public id');
    semanticEntitiesAssert(!array_key_exists('secret', $meta), 'Secret-like metadata must be dropped');
    $fileMeta = is_array($decoded['files:file_public_1']['meta'] ?? null) ? $decoded['files:file_public_1']['meta'] : [];
    semanticEntitiesAssert(($fileMeta['entity_type'] ?? '') === 'files', 'File meta must include normalized entity type');
    semanticEntitiesAssert(!array_key_exists('storage_path', $fileMeta), 'File storage_path metadata must be dropped');
    semanticEntitiesAssert(!array_key_exists('content_base64', $fileMeta), 'File content_base64 metadata must be dropped');
    semanticEntitiesAssert(!array_key_exists('raw_text', $fileMeta), 'File raw_text metadata must be dropped');
    $embedding = is_array($decoded['tasks:task_public_1']['embedding'] ?? null) ? $decoded['tasks:task_public_1']['embedding'] : [];
    semanticEntitiesAssert(($embedding['provider'] ?? '') === 'local_keyword_v1', 'Semantic index must persist local vector provider marker');
    semanticEntitiesAssert((int)($embedding['dimensions'] ?? 0) === 64, 'Semantic index vector dimensions must be stable');
    semanticEntitiesAssert(is_array($embedding['vector'] ?? null) && count($embedding['vector']) === 64, 'Semantic index must persist a bounded vector');

    $found = $service->search('task text', 5);
    $items = is_array($found['items'] ?? null) ? $found['items'] : [];
    semanticEntitiesAssert(count($items) >= 1, 'Vector-backed search must return indexed entity document');
    semanticEntitiesAssert(($items[0]['document_public_id'] ?? '') === 'tasks:task_public_1', 'Vector-backed search must expose normalized document key');

    $removed = $service->removeEntityDocument('task', 'task_public_1');
    semanticEntitiesAssert((bool)($removed['ok'] ?? false) === true, 'Removing indexed entity document must succeed');
    $fileRemoved = $service->removeEntityDocument('file', 'file_public_1');
    semanticEntitiesAssert((bool)($fileRemoved['ok'] ?? false) === true, 'Removing indexed file document must succeed');
    $afterRemove = $service->search('task text', 5);
    semanticEntitiesAssert(count((array)($afterRemove['items'] ?? [])) === 0, 'Removed entity document must not be searchable');

    echo "[OK] ai_semantic_index_entities_unit\n";
} catch (Throwable $e) {
    fwrite(STDERR, '[FAIL] ai_semantic_index_entities_unit: ' . $e->getMessage() . "\n");
    exit(1);
}
