<?php
declare(strict_types=1);

require __DIR__ . '/_bootstrap.php';

/** @param array<int,mixed> $items @return array<string,mixed> */
function findFlagByCodeOrFail534(array $items, string $code): array
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
function findIntentByCodeOrFail534(array $items, string $intentCode): array
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

function runtimeSqlitePath534(): string
{
    $base = trim((string)getenv('CRM_STORAGE_BASE'));
    if ($base === '') {
        $base = dirname(__DIR__, 3) . '/../storage_api';
    }

    return rtrim($base, '/\\') . '/temp/crm.sqlite';
}

$restore = [
    'root_headers' => [],
    'flag_snapshots' => [],
    'intent_snapshot' => null,
    'provider_public_id' => '',
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

    $flagSnapshots = [];
    foreach (['ai.enabled', 'ai.task'] as $flagCode) {
        $flag = findFlagByCodeOrFail534($flagItems, $flagCode);
        $flagPublicId = (string)($flag['public_id'] ?? '');
        assertTrue($flagPublicId !== '', 'Feature flag public_id is required for ' . $flagCode);

        $flagSnapshots[$flagCode] = [
            'public_id' => $flagPublicId,
            'is_enabled' => (bool)($flag['is_enabled'] ?? false),
        ];

        $enable = request('PATCH', '/api/v1/feature-flags/' . $flagPublicId, ['is_enabled' => 1], $rootHeaders);
        assertTrue($enable['status'] === 200, 'Enable feature flag must return 200 for ' . $flagCode);
    }
    $restore['flag_snapshots'] = $flagSnapshots;

    $providerCreate = request('POST', '/api/v1/ai/providers', [
        'provider_code' => 'mock',
        'title' => 'AI Raw Prompt Storage Guard Provider ' . randomSuffix(),
        'base_url' => 'https://example.com',
        'api_path' => '/v1/chat/completions',
        'default_model' => 'mock-raw-prompt-guard',
        'provider_payload' => [
            'mock_models' => ['mock-raw-prompt-guard'],
        ],
        'is_default' => 0,
        'is_active' => 1,
    ], $rootHeaders);
    assertTrue($providerCreate['status'] === 201, 'Provider create status must be 201');
    $providerPublicId = (string)($providerCreate['payload']['data']['provider']['public_id'] ?? '');
    assertTrue($providerPublicId !== '', 'Provider public_id is required');
    $restore['provider_public_id'] = $providerPublicId;

    $providerSecret = request('PUT', '/api/v1/ai/providers/' . $providerPublicId . '/secret', [
        'secret' => 'raw-prompt-storage-secret-' . randomSuffix(),
    ], $rootHeaders);
    assertTrue($providerSecret['status'] === 200, 'Provider secret set status must be 200');

    $intentSettings = request('GET', '/api/v1/ai/intent-settings', [], $rootHeaders);
    assertTrue($intentSettings['status'] === 200, 'Intent settings list status must be 200');
    $intentItems = (array)($intentSettings['payload']['data']['items'] ?? []);
    $taskSummaryIntent = findIntentByCodeOrFail534($intentItems, 'task_summary');

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
        'model' => 'mock-raw-prompt-guard',
        'feature_flag' => $intentSnapshot['feature_flag'] !== '' ? $intentSnapshot['feature_flag'] : 'ai.task',
        'required_permission' => $intentSnapshot['required_permission'] !== '' ? $intentSnapshot['required_permission'] : 'ai.use',
        'is_enabled' => 1,
        'max_tokens' => max(1, $intentSnapshot['max_tokens']),
    ], $rootHeaders);
    assertTrue($patchIntent['status'] === 200, 'task_summary intent patch status must be 200');

    $taskCreate = request('POST', '/api/v1/tasks', [
        'title' => 'AI Raw Prompt Storage Guard Task ' . randomSuffix(),
        'description' => 'Task body for prompt storage guard smoke.',
    ], $rootHeaders);
    assertTrue($taskCreate['status'] === 201, 'Task create status must be 201');
    $taskPublicId = (string)($taskCreate['payload']['data']['task']['public_id'] ?? '');
    assertTrue($taskPublicId !== '', 'Task public_id is required');

    $rawPrompt = 'Please summarize. Bearer sk-live-VERY-SECRET-TOKEN-123456 email raw.prompt.guard@example.com api_key=super_secret_prompt_key_987';
    $taskSummary = request('POST', '/api/v1/ai/tasks/' . $taskPublicId . '/summary', [
        'prompt' => $rawPrompt,
    ], $rootHeaders);
    assertTrue($taskSummary['status'] === 201, 'Task summary suggestion create status must be 201');

    $jobPublicId = (string)($taskSummary['payload']['data']['job_public_id'] ?? '');
    assertTrue($jobPublicId !== '', 'Job public_id is required');

    $sqlitePath = runtimeSqlitePath534();
    assertTrue(is_file($sqlitePath), 'Runtime sqlite file must exist: ' . $sqlitePath);
    $pdo = new PDO('sqlite:' . $sqlitePath);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $stmt = $pdo->prepare('SELECT payload_json FROM ai_jobs WHERE public_id = :public_id LIMIT 1');
    $stmt->execute(['public_id' => $jobPublicId]);
    $payloadJson = (string)$stmt->fetchColumn();
    assertTrue($payloadJson !== '', 'Stored ai_jobs.payload_json must be non-empty for created job');

    assertTrue(!str_contains($payloadJson, $rawPrompt), 'Raw prompt must not be stored in ai_jobs.payload_json');
    assertTrue(!str_contains($payloadJson, 'raw.prompt.guard@example.com'), 'Prompt email marker must not be stored in ai_jobs.payload_json');
    assertTrue(!str_contains($payloadJson, 'super_secret_prompt_key_987'), 'Prompt api_key marker must not be stored in ai_jobs.payload_json');
    assertTrue(!str_contains($payloadJson, 'sk-live-VERY-SECRET-TOKEN-123456'), 'Prompt bearer token marker must not be stored in ai_jobs.payload_json');

    $payload = json_decode($payloadJson, true);
    assertTrue(is_array($payload), 'Stored payload_json must decode to array');
    $storedInput = is_array($payload['input'] ?? null) ? (array)$payload['input'] : [];
    assertTrue((string)($storedInput['prompt'] ?? '') === '[redacted]', 'Stored input.prompt must be redacted');

    $promptRuntime = is_array($payload['prompt_runtime'] ?? null) ? (array)$payload['prompt_runtime'] : [];
    assertTrue($promptRuntime !== [], 'Stored payload must keep sanitized prompt_runtime meta');
    assertTrue(!array_key_exists('user_prompt', $promptRuntime), 'Stored prompt_runtime must not contain raw user_prompt');
    assertTrue(!array_key_exists('system_prompt', $promptRuntime), 'Stored prompt_runtime must not contain raw system_prompt');
    assertTrue(!array_key_exists('context', $promptRuntime), 'Stored prompt_runtime must not contain raw context');

    $stmt = null;
    $pdo = null;

    fwrite(STDOUT, "[OK] ai_no_raw_prompt_storage_smoke\n");
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
    fwrite(STDERR, "[FAIL] ai_no_raw_prompt_storage_smoke: " . $failedMessage . "\n");
    exit(1);
}
