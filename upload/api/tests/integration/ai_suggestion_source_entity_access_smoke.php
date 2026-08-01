<?php
declare(strict_types=1);

require __DIR__ . '/_bootstrap.php';

/** @param array<int,mixed> $items @return array<string,mixed> */
function findIntentByCodeOrFail(array $items, string $intentCode): array
{
    foreach ($items as $item) {
        if (!is_array($item)) {
            continue;
        }
        if ((string)($item['intent_code'] ?? '') === $intentCode) {
            return $item;
        }
    }

    throw new RuntimeException('Intent not found: ' . $intentCode);
}

/** @param array<int,mixed> $items */
function findByCodeOrFail(array $items, string $code): array
{
    foreach ($items as $item) {
        if (!is_array($item)) {
            continue;
        }
        if ((string)($item['code'] ?? '') === $code) {
            return $item;
        }
    }

    throw new RuntimeException('Item not found by code: ' . $code);
}

$restore = [
    'root_headers' => [],
    'provider_public_id' => '',
    'intent_snapshot' => null,
];
$failedMessage = '';

try {
    $root = loginRoot();
    $rootHeaders = authHeaders($root['token']);
    $restore['root_headers'] = $rootHeaders;

    $migrationRun = request('POST', '/internal/migration/up', [], $rootHeaders);
    assertTrue(in_array($migrationRun['status'], [200, 201], true), 'Migration up must return 200/201');

    $flags = request('GET', '/api/v1/feature-flags', [], $rootHeaders);
    assertTrue($flags['status'] === 200, 'Feature flags list status must be 200');
    $flagItems = (array)($flags['payload']['data']['items'] ?? []);
    $aiEnabled = findByCodeOrFail($flagItems, 'ai.enabled');
    $aiTask = findByCodeOrFail($flagItems, 'ai.task');

    $enableAi = request('PATCH', '/api/v1/feature-flags/' . (string)($aiEnabled['public_id'] ?? ''), ['is_enabled' => 1], $rootHeaders);
    assertTrue($enableAi['status'] === 200, 'Enable ai.enabled must return 200');
    $enableAiTask = request('PATCH', '/api/v1/feature-flags/' . (string)($aiTask['public_id'] ?? ''), ['is_enabled' => 1], $rootHeaders);
    assertTrue($enableAiTask['status'] === 200, 'Enable ai.task must return 200');

    $ownerRole = request('POST', '/api/v1/roles', [
        'code' => 'ai_owner_' . randomSuffix(),
        'title' => 'AI Owner',
    ], $rootHeaders);
    assertTrue($ownerRole['status'] === 201, 'Owner role create must return 201');
    $ownerRolePublicId = (string)($ownerRole['payload']['data']['role']['public_id'] ?? '');

    $auditorRole = request('POST', '/api/v1/roles', [
        'code' => 'ai_auditor_' . randomSuffix(),
        'title' => 'AI Auditor',
    ], $rootHeaders);
    assertTrue($auditorRole['status'] === 201, 'Auditor role create must return 201');
    $auditorRolePublicId = (string)($auditorRole['payload']['data']['role']['public_id'] ?? '');

    $setOwnerPermissions = request('PUT', '/api/v1/roles/' . $ownerRolePublicId . '/permissions', [
        'permission_codes' => ['ai.use', 'task.manage'],
    ], $rootHeaders);
    assertTrue($setOwnerPermissions['status'] === 200, 'Owner permissions set must return 200');

    $setAuditorPermissions = request('PUT', '/api/v1/roles/' . $auditorRolePublicId . '/permissions', [
        'permission_codes' => ['ai.use', 'ai.view_audit'],
    ], $rootHeaders);
    assertTrue($setAuditorPermissions['status'] === 200, 'Auditor permissions set must return 200');

    $ownerLogin = 'ai.owner.' . randomSuffix();
    $ownerPassword = 'AiOwnerPass#2026!';
    $ownerToken = 'ai-owner-token-' . randomSuffix();
    $ownerCreate = request('POST', '/api/v1/users', [
        'login' => $ownerLogin,
        'password' => $ownerPassword,
        'token' => $ownerToken,
        'email' => $ownerLogin . '@crm.local',
        'full_name' => 'AI Owner',
        'role_public_ids' => [$ownerRolePublicId],
    ], $rootHeaders);
    assertTrue($ownerCreate['status'] === 201, 'Owner user create must return 201');

    $auditorLogin = 'ai.auditor.' . randomSuffix();
    $auditorPassword = 'AiAuditorPass#2026!';
    $auditorToken = 'ai-auditor-token-' . randomSuffix();
    $auditorCreate = request('POST', '/api/v1/users', [
        'login' => $auditorLogin,
        'password' => $auditorPassword,
        'token' => $auditorToken,
        'email' => $auditorLogin . '@crm.local',
        'full_name' => 'AI Auditor',
        'role_public_ids' => [$auditorRolePublicId],
    ], $rootHeaders);
    assertTrue($auditorCreate['status'] === 201, 'Auditor user create must return 201');

    $ownerAuth = request('POST', '/api/v1/auth/login', [
        'login' => $ownerLogin,
        'password' => $ownerPassword,
        'token' => $ownerToken,
    ]);
    assertTrue($ownerAuth['status'] === 200, 'Owner login must return 200');
    $ownerHeaders = authHeaders((string)($ownerAuth['payload']['data']['access_token'] ?? ''));

    $auditorAuth = request('POST', '/api/v1/auth/login', [
        'login' => $auditorLogin,
        'password' => $auditorPassword,
        'token' => $auditorToken,
    ]);
    assertTrue($auditorAuth['status'] === 200, 'Auditor login must return 200');
    $auditorHeaders = authHeaders((string)($auditorAuth['payload']['data']['access_token'] ?? ''));

    $providerCreate = request('POST', '/api/v1/ai/providers', [
        'provider_code' => 'mock',
        'title' => 'Suggestion Source Access Provider ' . randomSuffix(),
        'base_url' => 'https://example.com',
        'api_path' => '/v1/chat/completions',
        'default_model' => 'mock-source-access-v1',
        'provider_payload' => [
            'mock_models' => ['mock-source-access-v1'],
        ],
        'is_default' => 0,
        'is_active' => 1,
    ], $rootHeaders);
    assertTrue($providerCreate['status'] === 201, 'Provider create must return 201');
    $providerPublicId = (string)($providerCreate['payload']['data']['provider']['public_id'] ?? '');
    assertTrue($providerPublicId !== '', 'Provider public_id is required');
    $restore['provider_public_id'] = $providerPublicId;

    $providerSecret = request('PUT', '/api/v1/ai/providers/' . $providerPublicId . '/secret', [
        'secret' => 'source-access-secret-' . randomSuffix(),
    ], $rootHeaders);
    assertTrue($providerSecret['status'] === 200, 'Provider secret set must return 200');

    $intentSettings = request('GET', '/api/v1/ai/intent-settings', [], $rootHeaders);
    assertTrue($intentSettings['status'] === 200, 'Intent settings list status must be 200');
    $intentItems = (array)($intentSettings['payload']['data']['items'] ?? []);
    $taskSummaryIntent = findIntentByCodeOrFail($intentItems, 'task_summary');

    $intentSnapshot = [
        'provider_public_id' => trim((string)($taskSummaryIntent['provider_public_id'] ?? '')),
        'model' => (string)($taskSummaryIntent['model'] ?? ''),
        'feature_flag' => (string)($taskSummaryIntent['feature_flag'] ?? 'ai.task'),
        'required_permission' => (string)($taskSummaryIntent['required_permission'] ?? 'ai.use'),
        'is_enabled' => (bool)($taskSummaryIntent['is_enabled'] ?? true),
        'max_tokens' => (int)($taskSummaryIntent['max_tokens'] ?? 1200),
    ];
    $restore['intent_snapshot'] = $intentSnapshot;

    $patchIntent = request('PATCH', '/api/v1/ai/intent-settings/task_summary', [
        'provider_public_id' => $providerPublicId,
        'model' => 'mock-source-access-v1',
        'feature_flag' => $intentSnapshot['feature_flag'] !== '' ? $intentSnapshot['feature_flag'] : 'ai.task',
        'required_permission' => 'ai.use',
        'is_enabled' => 1,
        'max_tokens' => max(1, $intentSnapshot['max_tokens']),
    ], $rootHeaders);
    assertTrue($patchIntent['status'] === 200, 'Patch task_summary intent must return 200');

    $taskCreate = request('POST', '/api/v1/tasks', [
        'title' => 'Suggestion Source Access Task ' . randomSuffix(),
        'description' => 'Check suggestion access by source entity',
    ], $ownerHeaders);
    assertTrue($taskCreate['status'] === 201, 'Task create by owner must return 201');
    $taskPublicId = (string)($taskCreate['payload']['data']['task']['public_id'] ?? '');
    assertTrue($taskPublicId !== '', 'Task public_id is required');

    $summaryCreate = request('POST', '/api/v1/ai/tasks/' . $taskPublicId . '/summary', [
        'prompt' => 'Generate summary for source entity access test',
    ], $ownerHeaders);
    assertTrue(
        $summaryCreate['status'] === 201,
        'Task summary create must return 201, got status=' . (int)$summaryCreate['status']
        . ' code=' . (string)($summaryCreate['payload']['code'] ?? 'UNKNOWN')
    );
    $suggestionPublicId = (string)($summaryCreate['payload']['data']['suggestion']['public_id'] ?? '');
    assertTrue($suggestionPublicId !== '', 'Suggestion public_id is required');

    $ownerList = request('GET', '/api/v1/ai/suggestions', [
        'entity_type' => 'task',
        'entity_public_id' => $taskPublicId,
    ], $ownerHeaders);
    assertTrue($ownerList['status'] === 200, 'Owner suggestions list must return 200');
    $ownerItems = (array)($ownerList['payload']['data']['items'] ?? []);
    assertTrue(count($ownerItems) >= 1, 'Owner must see own suggestion');

    $ownerGet = request('GET', '/api/v1/ai/suggestions/' . $suggestionPublicId, [], $ownerHeaders);
    assertTrue($ownerGet['status'] === 200, 'Owner suggestion get must return 200');

    $auditorList = request('GET', '/api/v1/ai/suggestions', [
        'entity_type' => 'task',
        'entity_public_id' => $taskPublicId,
    ], $auditorHeaders);
    assertTrue($auditorList['status'] === 200, 'Auditor suggestions list must return 200');
    $auditorItems = (array)($auditorList['payload']['data']['items'] ?? []);
    assertTrue($auditorItems === [], 'Auditor without source task access must not see suggestions');

    $auditorGet = request('GET', '/api/v1/ai/suggestions/' . $suggestionPublicId, [], $auditorHeaders);
    assertTrue($auditorGet['status'] === 404, 'Auditor without source task access must get 404 on suggestion get');

    $rootGet = request('GET', '/api/v1/ai/suggestions/' . $suggestionPublicId, [], $rootHeaders);
    assertTrue($rootGet['status'] === 200, 'Root must still access suggestion');

    fwrite(STDOUT, "[OK] ai_suggestion_source_entity_access_smoke\n");
} catch (Throwable $e) {
    $failedMessage = $e->getMessage();
} finally {
    $rootHeaders = is_array($restore['root_headers']) ? (array)$restore['root_headers'] : [];
    if ($rootHeaders !== []) {
        $intentSnapshot = is_array($restore['intent_snapshot']) ? (array)$restore['intent_snapshot'] : [];
        if ($intentSnapshot !== []) {
            request('PATCH', '/api/v1/ai/intent-settings/task_summary', [
                'provider_public_id' => (string)($intentSnapshot['provider_public_id'] ?? ''),
                'model' => (string)($intentSnapshot['model'] ?? ''),
                'feature_flag' => (string)($intentSnapshot['feature_flag'] ?? 'ai.task'),
                'required_permission' => (string)($intentSnapshot['required_permission'] ?? 'ai.use'),
                'is_enabled' => (bool)($intentSnapshot['is_enabled'] ?? true) ? 1 : 0,
                'max_tokens' => max(1, (int)($intentSnapshot['max_tokens'] ?? 1200)),
            ], $rootHeaders);
        }

        $providerPublicId = trim((string)($restore['provider_public_id'] ?? ''));
        if ($providerPublicId !== '') {
            request('DELETE', '/api/v1/ai/providers/' . $providerPublicId, [], $rootHeaders);
        }
    }
}

if ($failedMessage !== '') {
    fwrite(STDERR, "[FAIL] ai_suggestion_source_entity_access_smoke: " . $failedMessage . "\n");
    exit(1);
}
