<?php
declare(strict_types=1);

require __DIR__ . '/_bootstrap.php';

use Api\System\Library\Config;
use Api\System\Library\Logger\JsonLogger;
use Api\System\Library\Service\AiSemanticIndexService;

function semanticAccessFindFlag(array $items, string $code): array
{
    foreach ($items as $item) {
        if (is_array($item) && (string)($item['code'] ?? '') === $code) {
            return $item;
        }
    }
    throw new RuntimeException('Feature flag not found: ' . $code);
}

try {
    $root = loginRoot();
    $rootHeaders = authHeaders($root['token']);

    $flags = request('GET', '/api/v1/feature-flags', [], $rootHeaders);
    assertTrue($flags['status'] === 200, 'Feature flags list must return 200');
    $flagItems = (array)($flags['payload']['data']['items'] ?? []);
    $snapshots = [];
    foreach (['ai.enabled', 'ai.search'] as $flagCode) {
        $flag = semanticAccessFindFlag($flagItems, $flagCode);
        $snapshots[$flagCode] = $flag;
        $enabled = request('PATCH', '/api/v1/feature-flags/' . (string)$flag['public_id'], ['is_enabled' => 1], $rootHeaders);
        assertTrue($enabled['status'] === 200, 'Enable ' . $flagCode . ' must return 200');
    }

    $ownerRole = request('POST', '/api/v1/roles', [
        'code' => 'semantic_owner_' . randomSuffix(),
        'title' => 'Semantic Owner',
    ], $rootHeaders);
    assertTrue($ownerRole['status'] === 201, 'Owner role create must return 201');
    $ownerRolePublicId = (string)($ownerRole['payload']['data']['role']['public_id'] ?? '');
    $setOwnerPermissions = request('PUT', '/api/v1/roles/' . $ownerRolePublicId . '/permissions', [
        'permission_codes' => ['ai.use', 'task.manage'],
    ], $rootHeaders);
    assertTrue($setOwnerPermissions['status'] === 200, 'Owner permissions set must return 200');

    $viewerRole = request('POST', '/api/v1/roles', [
        'code' => 'semantic_viewer_' . randomSuffix(),
        'title' => 'Semantic Viewer',
    ], $rootHeaders);
    assertTrue($viewerRole['status'] === 201, 'Viewer role create must return 201');
    $viewerRolePublicId = (string)($viewerRole['payload']['data']['role']['public_id'] ?? '');
    $setViewerPermissions = request('PUT', '/api/v1/roles/' . $viewerRolePublicId . '/permissions', [
        'permission_codes' => ['ai.use'],
    ], $rootHeaders);
    assertTrue($setViewerPermissions['status'] === 200, 'Viewer permissions set must return 200');

    $ownerLogin = 'semantic.owner.' . randomSuffix();
    $ownerPassword = 'SemanticOwner#2026!';
    $ownerToken = 'semantic-owner-token-' . randomSuffix();
    $ownerCreate = request('POST', '/api/v1/users', [
        'login' => $ownerLogin,
        'password' => $ownerPassword,
        'token' => $ownerToken,
        'email' => $ownerLogin . '@crm.local',
        'full_name' => 'Semantic Owner',
        'role_public_ids' => [$ownerRolePublicId],
    ], $rootHeaders);
    assertTrue($ownerCreate['status'] === 201, 'Owner user create must return 201');

    $viewerLogin = 'semantic.viewer.' . randomSuffix();
    $viewerPassword = 'SemanticViewer#2026!';
    $viewerToken = 'semantic-viewer-token-' . randomSuffix();
    $viewerCreate = request('POST', '/api/v1/users', [
        'login' => $viewerLogin,
        'password' => $viewerPassword,
        'token' => $viewerToken,
        'email' => $viewerLogin . '@crm.local',
        'full_name' => 'Semantic Viewer',
        'role_public_ids' => [$viewerRolePublicId],
    ], $rootHeaders);
    assertTrue($viewerCreate['status'] === 201, 'Viewer user create must return 201');

    $ownerAuth = request('POST', '/api/v1/auth/login', [
        'login' => $ownerLogin,
        'password' => $ownerPassword,
        'token' => $ownerToken,
    ]);
    assertTrue($ownerAuth['status'] === 200, 'Owner login must return 200');
    $ownerHeaders = authHeaders((string)($ownerAuth['payload']['data']['access_token'] ?? ''));

    $viewerAuth = request('POST', '/api/v1/auth/login', [
        'login' => $viewerLogin,
        'password' => $viewerPassword,
        'token' => $viewerToken,
    ]);
    assertTrue($viewerAuth['status'] === 200, 'Viewer login must return 200');
    $viewerHeaders = authHeaders((string)($viewerAuth['payload']['data']['access_token'] ?? ''));

    $suffix = randomSuffix();
    $task = request('POST', '/api/v1/tasks', [
        'title' => 'Semantic private task ' . $suffix,
        'description' => 'Private semantic object access ' . $suffix,
    ], $ownerHeaders);
    assertTrue($task['status'] === 201, 'Owner task create must return 201');
    $taskPublicId = (string)($task['payload']['data']['task']['public_id'] ?? '');
    assertTrue($taskPublicId !== '', 'Task public_id is required');

    $storageBase = trim((string)getenv('CRM_STORAGE_BASE'));
    if ($storageBase === '') {
        $storageBase = dirname(__DIR__, 3) . '/storage';
    }
    $config = new Config();
    $config->merge('default', ['storage' => ['base' => $storageBase]]);
    $semanticIndex = new AiSemanticIndexService($config, new JsonLogger([]));
    $needle = 'semantic private needle ' . $suffix;
    $indexed = $semanticIndex->indexEntityDocument('task', $taskPublicId, $needle);
    assertTrue((bool)($indexed['ok'] ?? false) === true, 'Index private task must succeed');

    $ownerSearch = request('POST', '/api/v1/ai/search/semantic', ['query' => $needle], $ownerHeaders);
    assertTrue($ownerSearch['status'] === 200, 'Owner semantic search must return 200');
    $ownerItems = (array)($ownerSearch['payload']['data']['items'] ?? []);
    assertTrue(count($ownerItems) >= 1, 'Owner must see indexed accessible task');

    $viewerSearch = request('POST', '/api/v1/ai/search/semantic', ['query' => $needle], $viewerHeaders);
    assertTrue($viewerSearch['status'] === 200, 'Viewer semantic search must return 200');
    $viewerItems = (array)($viewerSearch['payload']['data']['items'] ?? []);
    assertTrue($viewerItems === [], 'Viewer without task access must not receive semantic result');

    $semanticIndex->removeEntityDocument('task', $taskPublicId);
    request('DELETE', '/api/v1/tasks/' . $taskPublicId, [], $ownerHeaders);
    foreach ($snapshots as $snapshot) {
        request('PATCH', '/api/v1/feature-flags/' . (string)$snapshot['public_id'], [
            'is_enabled' => (bool)($snapshot['is_enabled'] ?? false) ? 1 : 0,
        ], $rootHeaders);
    }

    echo "[OK] ai_semantic_search_object_access_smoke\n";
} catch (Throwable $e) {
    fwrite(STDERR, '[FAIL] ai_semantic_search_object_access_smoke: ' . $e->getMessage() . "\n");
    exit(1);
}
