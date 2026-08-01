<?php
declare(strict_types=1);

require __DIR__ . '/_bootstrap.php';

function runtimeSqlitePath620(): string
{
    $base = trim((string)getenv('CRM_STORAGE_BASE'));
    if ($base === '') {
        $base = dirname(__DIR__, 3) . '/../storage_api';
    }

    return rtrim($base, '/\\') . '/temp/crm.sqlite';
}

try {
    $root = loginRoot();
    $rootHeaders = authHeaders($root['token']);

    $migrationRun = request('POST', '/internal/migration/up', [], $rootHeaders);
    assertTrue(in_array($migrationRun['status'], [200, 201], true), 'Migration up must return 200/201');

    $sqlitePath = runtimeSqlitePath620();
    assertTrue(is_file($sqlitePath), 'Runtime sqlite file must exist: ' . $sqlitePath);
    $pdo = new PDO('sqlite:' . $sqlitePath);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $rawPrompt = 'Persist check prompt raw.default@example.com Bearer sk-live-RAW-PROMPT-123 api_key=super_prompt_secret';

    $stmt = $pdo->prepare('INSERT INTO ai_jobs (public_id, job_type, action_type, intent_code, status, requested_by_user_id, scope_type, scope_public_id, idempotency_key_hash, payload_json, result_json, error_code, error_message, created_at, started_at, finished_at, updated_at) VALUES (:public_id, :job_type, :action_type, :intent_code, :status, :requested_by_user_id, :scope_type, :scope_public_id, :idempotency_key_hash, :payload_json, :result_json, :error_code, :error_message, :created_at, :started_at, :finished_at, :updated_at)');
    $jobPublicId = 'aij_' . randomSuffix();
    $payloadJson = json_encode([
        'input' => [
            'prompt' => '[redacted]',
            'text' => '[redacted]',
        ],
        'prompt_runtime' => [
            'intent_code' => 'task_summary',
            'meta' => [
                'context_budget_tokens' => 1200,
                'context_estimated_tokens' => 240,
                'context_truncated' => false,
                'user_prompt_estimated_tokens' => 12,
            ],
        ],
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    assertTrue(is_string($payloadJson), 'Payload JSON encode must succeed');
    $now = gmdate('Y-m-d H:i:s');
    $stmt->execute([
        'public_id' => $jobPublicId,
        'job_type' => 'interactive',
        'action_type' => 'task_summary',
        'intent_code' => 'task_summary',
        'status' => 'done',
        'requested_by_user_id' => 1,
        'scope_type' => 'task',
        'scope_public_id' => 'tsk_' . randomSuffix(),
        'idempotency_key_hash' => hash('sha256', 'raw-prompt-default-off-' . $jobPublicId),
        'payload_json' => $payloadJson,
        'result_json' => '{}',
        'error_code' => null,
        'error_message' => null,
        'created_at' => $now,
        'started_at' => $now,
        'finished_at' => $now,
        'updated_at' => $now,
    ]);

    $fetch = $pdo->prepare('SELECT payload_json FROM ai_jobs WHERE public_id = :public_id LIMIT 1');
    $fetch->execute(['public_id' => $jobPublicId]);
    $storedPayloadJson = (string)$fetch->fetchColumn();
    assertTrue($storedPayloadJson !== '', 'Stored payload_json must be readable');
    assertTrue(!str_contains($storedPayloadJson, $rawPrompt), 'Stored payload_json must not contain raw prompt text');
    assertTrue(!str_contains($storedPayloadJson, 'raw.default@example.com'), 'Stored payload_json must not contain prompt email marker');
    assertTrue(!str_contains($storedPayloadJson, 'sk-live-RAW-PROMPT-123'), 'Stored payload_json must not contain bearer token marker');
    assertTrue(!str_contains($storedPayloadJson, 'super_prompt_secret'), 'Stored payload_json must not contain api_key marker');

    $decoded = json_decode($storedPayloadJson, true);
    assertTrue(is_array($decoded), 'Stored payload_json must decode to array');
    $promptRuntime = is_array($decoded['prompt_runtime'] ?? null) ? (array)$decoded['prompt_runtime'] : [];
    assertTrue($promptRuntime !== [], 'Stored prompt_runtime meta must exist');
    assertTrue(!array_key_exists('user_prompt', $promptRuntime), 'Stored prompt_runtime must not contain raw user_prompt');
    assertTrue(!array_key_exists('system_prompt', $promptRuntime), 'Stored prompt_runtime must not contain raw system_prompt');
    assertTrue(!array_key_exists('context', $promptRuntime), 'Stored prompt_runtime must not contain raw context');

    fwrite(STDOUT, "[OK] ai_raw_prompt_default_storage_off_smoke\n");
} catch (Throwable $e) {
    fwrite(STDERR, '[FAIL] ai_raw_prompt_default_storage_off_smoke: ' . $e->getMessage() . "\n");
    exit(1);
}
