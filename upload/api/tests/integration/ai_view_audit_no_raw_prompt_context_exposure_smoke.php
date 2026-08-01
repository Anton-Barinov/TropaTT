<?php
declare(strict_types=1);

require __DIR__ . '/_bootstrap.php';

try {
    $root = loginRoot();
    $rootHeaders = authHeaders($root['token']);

    $migrationRun = request('POST', '/internal/migration/up', [], $rootHeaders);
    assertTrue(in_array($migrationRun['status'], [200, 201], true), 'Migration up must return 200/201');

    $flagItems = (array)((request('GET', '/api/v1/feature-flags', [], $rootHeaders)['payload']['data']['items'] ?? []));
    $flagByCode = [];
    foreach ($flagItems as $item) {
        if (!is_array($item)) {
            continue;
        }
        $flagByCode[(string)($item['code'] ?? '')] = (string)($item['public_id'] ?? '');
    }
    foreach (['ai.enabled', 'ai.task'] as $flagCode) {
        $flagPublicId = (string)($flagByCode[$flagCode] ?? '');
        assertTrue($flagPublicId !== '', 'Feature flag public_id missing: ' . $flagCode);
        $enable = request('PATCH', '/api/v1/feature-flags/' . $flagPublicId, ['is_enabled' => 1], $rootHeaders);
        assertTrue($enable['status'] === 200, 'Feature flag enable must be 200 for ' . $flagCode);
    }

    $providerCreate = request('POST', '/api/v1/ai/providers', [
        'provider_code' => 'mock',
        'title' => 'Audit Redaction Provider ' . randomSuffix(),
        'base_url' => 'https://example.com',
        'api_path' => '/v1/chat/completions',
        'default_model' => 'mock-audit-redaction',
        'provider_payload' => [
            'mock_models' => ['mock-audit-redaction'],
        ],
        'is_default' => 0,
        'is_active' => 1,
    ], $rootHeaders);
    assertTrue($providerCreate['status'] === 201, 'Provider create status must be 201');
    $providerPublicId = (string)($providerCreate['payload']['data']['provider']['public_id'] ?? '');
    assertTrue($providerPublicId !== '', 'Provider public_id is required');

    $setSecret = request('PUT', '/api/v1/ai/providers/' . $providerPublicId . '/secret', [
        'secret' => 'audit-redaction-secret-' . randomSuffix(),
    ], $rootHeaders);
    assertTrue($setSecret['status'] === 200, 'Provider secret set status must be 200');

    $intentSettings = request('GET', '/api/v1/ai/intent-settings', [], $rootHeaders);
    assertTrue($intentSettings['status'] === 200, 'Intent settings list status must be 200');
    $intentItems = (array)($intentSettings['payload']['data']['items'] ?? []);
    $taskSummaryIntent = null;
    foreach ($intentItems as $item) {
        if ((string)($item['intent_code'] ?? '') === 'task_summary') {
            $taskSummaryIntent = is_array($item) ? $item : null;
            break;
        }
    }
    assertTrue(is_array($taskSummaryIntent), 'task_summary intent setting must exist');

    $intentPatch = request('PATCH', '/api/v1/ai/intent-settings/task_summary', [
        'provider_public_id' => $providerPublicId,
        'model' => 'mock-audit-redaction',
        'feature_flag' => (string)($taskSummaryIntent['feature_flag'] ?? 'ai.task'),
        'required_permission' => (string)($taskSummaryIntent['required_permission'] ?? 'ai.use'),
        'is_enabled' => 1,
        'max_tokens' => max(1, (int)($taskSummaryIntent['max_tokens'] ?? 1200)),
    ], $rootHeaders);
    assertTrue($intentPatch['status'] === 200, 'Intent patch status must be 200');

    $roleCreate = request('POST', '/api/v1/roles', [
        'code' => 'ai_view_audit_only_' . randomSuffix(),
        'title' => 'AI View Audit Only',
    ], $rootHeaders);
    assertTrue($roleCreate['status'] === 201, 'Audit role create status must be 201');
    $rolePublicId = (string)($roleCreate['payload']['data']['role']['public_id'] ?? '');
    assertTrue($rolePublicId !== '', 'Audit role public_id is required');

    $setRolePerms = request('PUT', '/api/v1/roles/' . $rolePublicId . '/permissions', [
        'permission_codes' => ['ai.view_audit'],
    ], $rootHeaders);
    assertTrue($setRolePerms['status'] === 200, 'Audit role permissions set must be 200');

    $userLogin = 'ai.view.audit.' . randomSuffix();
    $userPassword = 'AiViewAudit#2026!';
    $userToken = 'ai-view-audit-token-' . randomSuffix();
    $userCreate = request('POST', '/api/v1/users', [
        'login' => $userLogin,
        'password' => $userPassword,
        'token' => $userToken,
        'email' => $userLogin . '@crm.local',
        'full_name' => 'AI View Audit User',
        'role_public_ids' => [$rolePublicId],
    ], $rootHeaders);
    assertTrue($userCreate['status'] === 201, 'Audit user create status must be 201');

    $auth = request('POST', '/api/v1/auth/login', [
        'login' => $userLogin,
        'password' => $userPassword,
        'token' => $userToken,
    ]);
    assertTrue($auth['status'] === 200, 'Audit user login status must be 200');
    $auditHeaders = authHeaders((string)($auth['payload']['data']['access_token'] ?? ''));

    $taskCreate = request('POST', '/api/v1/tasks', [
        'title' => 'Audit redaction task ' . randomSuffix(),
        'description' => 'Task description with hidden context john.audit@example.com +7 (999) 123-45-67 and secret audit_token_123',
    ], $rootHeaders);
    assertTrue($taskCreate['status'] === 201, 'Task create status must be 201');
    $taskPublicId = (string)($taskCreate['payload']['data']['task']['public_id'] ?? '');
    assertTrue($taskPublicId !== '', 'Task public_id is required');

    $rawPrompt = 'Сделай сводку. Контекст: raw.prompt.audit@example.com токен sk-live-AUDIT-VERY-SECRET-12345';
    $taskSummary = request('POST', '/api/v1/ai/tasks/' . $taskPublicId . '/summary', [
        'prompt' => $rawPrompt,
    ], $rootHeaders);
    assertTrue($taskSummary['status'] === 201, 'Task summary create status must be 201');

    $usageList = request('GET', '/api/v1/ai/usage', [
        'action_type' => 'task_summary',
        'limit' => 50,
    ], $auditHeaders);
    assertTrue($usageList['status'] === 200, 'ai.view_audit user must access usage list');
    $usageItems = (array)($usageList['payload']['data']['items'] ?? []);
    assertTrue($usageItems !== [], 'Usage list must contain items');

    $matchingUsage = null;
    foreach ($usageItems as $item) {
        if (!is_array($item)) {
            continue;
        }
        $meta = is_array($item['request_meta'] ?? null) ? (array)$item['request_meta'] : [];
        if ((string)($meta['scope_public_id'] ?? '') === $taskPublicId && (string)($item['action_type'] ?? '') === 'task_summary') {
            $matchingUsage = $item;
            break;
        }
    }
    assertTrue(is_array($matchingUsage), 'Matching usage entry for task_summary must exist');
    $requestMeta = is_array($matchingUsage['request_meta'] ?? null) ? (array)$matchingUsage['request_meta'] : [];
    $requestMetaJson = json_encode($requestMeta, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    assertTrue(is_string($requestMetaJson), 'request_meta must encode to JSON');
    assertTrue(!str_contains($requestMetaJson, $rawPrompt), 'request_meta must not expose raw prompt');
    assertTrue(!str_contains($requestMetaJson, 'raw.prompt.audit@example.com'), 'request_meta must not expose prompt email');
    assertTrue(!str_contains($requestMetaJson, 'sk-live-AUDIT-VERY-SECRET-12345'), 'request_meta must not expose secret token');
    $promptRuntime = is_array($requestMeta['prompt_runtime'] ?? null) ? (array)$requestMeta['prompt_runtime'] : [];
    assertTrue($promptRuntime !== [], 'request_meta must keep sanitized prompt_runtime meta');
    assertTrue(!array_key_exists('context', $promptRuntime), 'request_meta prompt_runtime must not expose raw context');
    assertTrue(!array_key_exists('user_prompt', $promptRuntime), 'request_meta prompt_runtime must not expose raw user_prompt');
    assertTrue(!array_key_exists('system_prompt', $promptRuntime), 'request_meta prompt_runtime must not expose raw system_prompt');

    $auditList = request('GET', '/api/v1/ai/audit', ['limit' => 100], $auditHeaders);
    assertTrue($auditList['status'] === 200, 'ai.view_audit user must access audit list');
    $auditItems = (array)($auditList['payload']['data']['items'] ?? []);
    assertTrue($auditItems !== [], 'Audit list must contain items');

    $matchingAudit = null;
    foreach ($auditItems as $item) {
        if (!is_array($item)) {
            continue;
        }
        $details = is_array($item['details'] ?? null) ? (array)$item['details'] : [];
        $detailsJson = json_encode($details, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        assertTrue(is_string($detailsJson), 'audit details must encode to JSON');
        assertTrue(!str_contains($detailsJson, $rawPrompt), 'audit details must not expose raw prompt');
        assertTrue(!str_contains($detailsJson, 'raw.prompt.audit@example.com'), 'audit details must not expose prompt email');
        assertTrue(!str_contains($detailsJson, 'sk-live-AUDIT-VERY-SECRET-12345'), 'audit details must not expose secret token');
        if (
            (string)($item['action'] ?? '') === 'ai_suggestion_created'
            && (string)($item['entity_public_id'] ?? '') === (string)($taskSummary['payload']['data']['suggestion']['public_id'] ?? '')
        ) {
            $matchingAudit = $item;
        }
    }
    assertTrue(is_array($matchingAudit), 'Audit list must contain matching ai_suggestion_created event');
    $matchingDetails = is_array($matchingAudit['details'] ?? null) ? (array)$matchingAudit['details'] : [];
    assertTrue((string)($matchingDetails['intent_code'] ?? '') === 'task_summary', 'Audit details must include intent_code');
    assertTrue((string)($matchingDetails['provider_public_id'] ?? '') === $providerPublicId, 'Audit details must include provider_public_id');
    assertTrue((string)($matchingDetails['provider_code'] ?? '') === 'mock', 'Audit details must include provider_code');
    assertTrue((string)($matchingDetails['provider_type'] ?? '') === 'mock', 'Audit details must include provider_type');

    fwrite(STDOUT, "[OK] ai_view_audit_no_raw_prompt_context_exposure_smoke\n");
} catch (Throwable $e) {
    fwrite(STDERR, '[FAIL] ai_view_audit_no_raw_prompt_context_exposure_smoke: ' . $e->getMessage() . "\n");
    exit(1);
}
