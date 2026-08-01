<?php
declare(strict_types=1);

require __DIR__ . '/_bootstrap.php';

/** @param array<int,mixed> $items @return array<string,mixed> */
function findFlagByCodeOrFail474(array $items, string $code): array
{
    foreach ($items as $item) {
        if (!is_array($item)) {
            continue;
        }
        if ((string)($item['code'] ?? '') === $code) {
            return $item;
        }
    }

    throw new RuntimeException('Feature flag not found: ' . $code);
}

/** @param array<int,mixed> $items @return array<string,mixed> */
function findIntentByCodeOrFail474(array $items, string $intentCode): array
{
    foreach ($items as $item) {
        if (!is_array($item)) {
            continue;
        }
        if ((string)($item['intent_code'] ?? '') === $intentCode) {
            return $item;
        }
    }

    throw new RuntimeException('Intent setting not found: ' . $intentCode);
}

$restore = [
    'root_headers' => [],
    'flag_snapshots' => [],
    'intent_snapshot' => null,
    'provider_public_id' => '',
    'actor_headers' => [],
    'actor_public_id' => '',
    'actor_date' => '',
];
$failedMessage = '';

try {
    $root = loginRoot();
    $rootHeaders = authHeaders($root['token']);
    $restore['root_headers'] = $rootHeaders;

    $migrationRun = request('POST', '/internal/migration/up', [], $rootHeaders);
    assertTrue(in_array($migrationRun['status'], [200, 201], true), 'Migration up must return 200/201');

    $flagsResponse = request('GET', '/api/v1/feature-flags', [], $rootHeaders);
    assertTrue($flagsResponse['status'] === 200, 'Feature flags list status must be 200');
    $flagItems = (array)($flagsResponse['payload']['data']['items'] ?? []);

    $flagCodes = ['ai.enabled', 'ai.cron.daily_work_plan'];
    $flagSnapshots = [];
    foreach ($flagCodes as $flagCode) {
        $flag = findFlagByCodeOrFail474($flagItems, $flagCode);
        $flagPublicId = (string)($flag['public_id'] ?? '');
        assertTrue($flagPublicId !== '', 'Feature flag public_id is required for ' . $flagCode);
        $flagSnapshots[$flagCode] = [
            'public_id' => $flagPublicId,
            'is_enabled' => (bool)($flag['is_enabled'] ?? false),
        ];

        $enable = request('PATCH', '/api/v1/feature-flags/' . $flagPublicId, ['is_enabled' => 1], $rootHeaders);
        assertTrue($enable['status'] === 200, 'Enable feature flag must be 200 for ' . $flagCode);
    }
    $restore['flag_snapshots'] = $flagSnapshots;

    $roleCreate = request('POST', '/api/v1/roles', [
        'code' => 'ai_background_dedupe_' . randomSuffix(),
        'title' => 'AI Background Dedupe Role',
    ], $rootHeaders);
    assertTrue($roleCreate['status'] === 201, 'Role create status must be 201');
    $rolePublicId = (string)($roleCreate['payload']['data']['role']['public_id'] ?? '');
    assertTrue($rolePublicId !== '', 'Role public_id is required');

    $rolePerms = request('PUT', '/api/v1/roles/' . $rolePublicId . '/permissions', [
        'permission_codes' => ['ai.use', 'ai.manage_cron_jobs'],
    ], $rootHeaders);
    assertTrue($rolePerms['status'] === 200, 'Role permissions set status must be 200');

    $userLogin = 'ai.bg.dedupe.' . randomSuffix();
    $userPassword = 'AiBgDedupePass#2026!';
    $userToken = 'ai-bg-dedupe-token-' . randomSuffix();
    $userCreate = request('POST', '/api/v1/users', [
        'login' => $userLogin,
        'password' => $userPassword,
        'token' => $userToken,
        'email' => $userLogin . '@crm.local',
        'full_name' => 'AI Background Dedupe User',
        'role_public_ids' => [$rolePublicId],
    ], $rootHeaders);
    assertTrue($userCreate['status'] === 201, 'Background dedupe user create status must be 201');
    $actorPublicId = (string)($userCreate['payload']['data']['user']['public_id'] ?? '');
    assertTrue($actorPublicId !== '', 'Background dedupe user public_id is required');
    $restore['actor_public_id'] = $actorPublicId;

    $userAuth = request('POST', '/api/v1/auth/login', [
        'login' => $userLogin,
        'password' => $userPassword,
        'token' => $userToken,
    ]);
    assertTrue($userAuth['status'] === 200, 'Background dedupe user login status must be 200');
    $actorHeaders = authHeaders((string)($userAuth['payload']['data']['access_token'] ?? ''));
    $restore['actor_headers'] = $actorHeaders;

    $providerCreate = request('POST', '/api/v1/ai/providers', [
        'provider_code' => 'mock',
        'title' => 'AI Background Dedupe Provider ' . randomSuffix(),
        'base_url' => 'https://example.com',
        'api_path' => '/v1/chat/completions',
        'default_model' => 'mock-background-dedupe',
        'provider_payload' => [
            'mock_models' => ['mock-background-dedupe'],
        ],
        'is_default' => 0,
        'is_active' => 1,
    ], $rootHeaders);
    assertTrue($providerCreate['status'] === 201, 'Provider create status must be 201');
    $providerPublicId = (string)($providerCreate['payload']['data']['provider']['public_id'] ?? '');
    assertTrue($providerPublicId !== '', 'Provider public_id is required');
    $restore['provider_public_id'] = $providerPublicId;

    $providerSecret = request('PUT', '/api/v1/ai/providers/' . $providerPublicId . '/secret', [
        'secret' => 'background-dedupe-secret-' . randomSuffix(),
    ], $rootHeaders);
    assertTrue($providerSecret['status'] === 200, 'Provider secret set status must be 200');

    $intentSettings = request('GET', '/api/v1/ai/intent-settings', [], $rootHeaders);
    assertTrue($intentSettings['status'] === 200, 'Intent settings list status must be 200');
    $intentItems = (array)($intentSettings['payload']['data']['items'] ?? []);
    $myDayIntent = findIntentByCodeOrFail474($intentItems, 'my_day_plan');

    $intentSnapshot = [
        'provider_public_id' => trim((string)($myDayIntent['provider_public_id'] ?? '')),
        'model' => (string)($myDayIntent['model'] ?? ''),
        'feature_flag' => (string)($myDayIntent['feature_flag'] ?? 'ai.cron.daily_work_plan'),
        'required_permission' => (string)($myDayIntent['required_permission'] ?? 'ai.use'),
        'is_enabled' => (bool)($myDayIntent['is_enabled'] ?? true),
        'max_tokens' => (int)($myDayIntent['max_tokens'] ?? 1200),
    ];
    $restore['intent_snapshot'] = $intentSnapshot;

    $patchIntent = request('PATCH', '/api/v1/ai/intent-settings/my_day_plan', [
        'provider_public_id' => $providerPublicId,
        'model' => 'mock-background-dedupe',
        'feature_flag' => $intentSnapshot['feature_flag'] !== '' ? $intentSnapshot['feature_flag'] : 'ai.cron.daily_work_plan',
        'required_permission' => 'ai.use',
        'is_enabled' => 1,
        'max_tokens' => max(1, $intentSnapshot['max_tokens']),
    ], $rootHeaders);
    assertTrue($patchIntent['status'] === 200, 'my_day_plan intent patch status must be 200');

    $targetDate = gmdate('Y-m-d', time() + 86400);
    $restore['actor_date'] = $targetDate;

    $requestPayload = [
        'date' => $targetDate,
        'meta' => [
            'source_marker' => 'daily_work_plan',
            'mode' => 'cron',
            'job_code' => 'ai:user-daily-work-plan',
        ],
    ];

    $planFirst = request('POST', '/api/v1/ai/my-day/plan', $requestPayload, $actorHeaders);
    assertTrue($planFirst['status'] === 201, 'First background my-day plan create must be 201');
    $firstSuggestion = (array)($planFirst['payload']['data']['suggestion'] ?? []);
    $firstSuggestionPublicId = (string)($firstSuggestion['public_id'] ?? '');
    assertTrue($firstSuggestionPublicId !== '', 'First background suggestion public_id is required');

    $planSecond = request('POST', '/api/v1/ai/my-day/plan', $requestPayload, $actorHeaders);
    assertTrue($planSecond['status'] === 201, 'Second background my-day plan create must be 201');
    $secondSuggestion = (array)($planSecond['payload']['data']['suggestion'] ?? []);
    $secondSuggestionPublicId = (string)($secondSuggestion['public_id'] ?? '');
    assertTrue($secondSuggestionPublicId !== '', 'Second background suggestion public_id is required');

    assertTrue($secondSuggestionPublicId === $firstSuggestionPublicId, 'Duplicate background suggestion must be deduplicated by input_hash (same suggestion public_id expected)');

    $list = request('GET', '/api/v1/ai/suggestions', [
        'intent_code' => 'my_day_plan',
        'entity_type' => 'user',
        'entity_public_id' => $actorPublicId,
        'limit' => 20,
    ], $actorHeaders);
    assertTrue($list['status'] === 200, 'Suggestions list for actor must return 200');
    $items = (array)($list['payload']['data']['items'] ?? []);

    $matched = [];
    foreach ($items as $item) {
        if (!is_array($item)) {
            continue;
        }
        if ((string)($item['intent_code'] ?? '') !== 'my_day_plan') {
            continue;
        }
        if ((string)($item['entity_type'] ?? '') !== 'user') {
            continue;
        }
        if ((string)($item['entity_public_id'] ?? '') !== $actorPublicId) {
            continue;
        }
        if ((string)($item['public_id'] ?? '') === $firstSuggestionPublicId) {
            $matched[] = $item;
        }
    }

    assertTrue(count($matched) === 1, 'Background dedupe must keep single suggestion record for identical input hash');

    fwrite(STDOUT, "[OK] ai_background_suggestion_input_hash_dedupe_smoke\n");
} catch (Throwable $e) {
    $failedMessage = $e->getMessage();
} finally {
    $rootHeaders = is_array($restore['root_headers']) ? (array)$restore['root_headers'] : [];
    if ($rootHeaders !== []) {
        $intentSnapshot = is_array($restore['intent_snapshot']) ? (array)$restore['intent_snapshot'] : [];
        if ($intentSnapshot !== []) {
            request('PATCH', '/api/v1/ai/intent-settings/my_day_plan', [
                'provider_public_id' => (string)($intentSnapshot['provider_public_id'] ?? ''),
                'model' => (string)($intentSnapshot['model'] ?? ''),
                'feature_flag' => (string)($intentSnapshot['feature_flag'] ?? 'ai.cron.daily_work_plan'),
                'required_permission' => (string)($intentSnapshot['required_permission'] ?? 'ai.use'),
                'is_enabled' => (bool)($intentSnapshot['is_enabled'] ?? true) ? 1 : 0,
                'max_tokens' => max(1, (int)($intentSnapshot['max_tokens'] ?? 1200)),
            ], $rootHeaders);
        }

        $flagSnapshots = is_array($restore['flag_snapshots']) ? (array)$restore['flag_snapshots'] : [];
        foreach ($flagSnapshots as $snapshot) {
            if (!is_array($snapshot)) {
                continue;
            }
            $flagPublicId = (string)($snapshot['public_id'] ?? '');
            if ($flagPublicId === '') {
                continue;
            }
            request('PATCH', '/api/v1/feature-flags/' . $flagPublicId, [
                'is_enabled' => (bool)($snapshot['is_enabled'] ?? false) ? 1 : 0,
            ], $rootHeaders);
        }

        $providerPublicId = trim((string)($restore['provider_public_id'] ?? ''));
        if ($providerPublicId !== '') {
            request('DELETE', '/api/v1/ai/providers/' . $providerPublicId, [], $rootHeaders);
        }
    }
}

if ($failedMessage !== '') {
    fwrite(STDERR, "[FAIL] ai_background_suggestion_input_hash_dedupe_smoke: " . $failedMessage . "\n");
    exit(1);
}
