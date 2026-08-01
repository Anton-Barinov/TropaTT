<?php
declare(strict_types=1);

require __DIR__ . '/_bootstrap.php';

/** @var array<string,string> $headers */
$headers = [];
$previousActivePublicId = '';
$providerPublicId = '';
$failed = false;
$failMessage = '';

try {
    $root = loginRoot();
    $headers = authHeaders($root['token']);

    $migrationRun = request('POST', '/internal/migration/up', [], $headers);
    assertTrue(in_array($migrationRun['status'], [200, 201], true), 'Migration up must return 200/201');

    $schemasBefore = request('GET', '/api/v1/ai/json-schemas', ['intent_code' => 'task_summary'], $headers);
    assertTrue($schemasBefore['status'] === 200, 'Schema list status must be 200');
    $schemaItems = is_array($schemasBefore['payload']['data']['items'] ?? null) ? (array)$schemasBefore['payload']['data']['items'] : [];
    foreach ($schemaItems as $item) {
        if (is_array($item) && (bool)($item['is_active'] ?? false)) {
            $previousActivePublicId = (string)($item['public_id'] ?? '');
            break;
        }
    }

    $strictSchemaCreate = request('POST', '/api/v1/ai/json-schemas', [
        'intent_code' => 'task_summary',
        'schema_version' => 'v948_' . randomSuffix(),
        'is_active' => 1,
        'schema_json' => [
            'type' => 'object',
            'required' => ['summary'],
            'properties' => [
                'summary' => ['type' => 'string'],
            ],
        ],
    ], $headers);
    assertTrue($strictSchemaCreate['status'] === 201, 'Strict schema create must return 201');
    $strictSchemaPublicId = (string)($strictSchemaCreate['payload']['data']['schema']['public_id'] ?? '');
    assertTrue($strictSchemaPublicId !== '', 'Strict schema public_id is required');

    $providerCreate = request('POST', '/api/v1/ai/providers', [
        'provider_code' => 'mock',
        'title' => 'Extra fields schema guard provider ' . randomSuffix(),
        'base_url' => 'https://example.com',
        'api_path' => '/v1/chat/completions',
        'default_model' => 'mock-extra-fields-guard',
        'provider_payload' => [
            'mock_models' => ['mock-extra-fields-guard'],
        ],
        'is_default' => 0,
        'is_active' => 1,
    ], $headers);
    assertTrue($providerCreate['status'] === 201, 'Provider create must return 201');
    $providerPublicId = (string)($providerCreate['payload']['data']['provider']['public_id'] ?? '');
    assertTrue($providerPublicId !== '', 'Provider public_id is required');

    $providerSecret = request('PUT', '/api/v1/ai/providers/' . $providerPublicId . '/secret', [
        'secret' => 'extra-fields-guard-secret-' . randomSuffix(),
    ], $headers);
    assertTrue($providerSecret['status'] === 200, 'Provider secret set must return 200');

    $flagsList = request('GET', '/api/v1/feature-flags', ['limit' => 200], $headers);
    assertTrue($flagsList['status'] === 200, 'Feature flags list status must be 200');
    $flagItems = is_array($flagsList['payload']['data']['items'] ?? null) ? (array)$flagsList['payload']['data']['items'] : [];
    $flagsByCode = [];
    foreach ($flagItems as $flag) {
        if (!is_array($flag)) {
            continue;
        }
        $code = trim((string)($flag['code'] ?? ''));
        $publicId = trim((string)($flag['public_id'] ?? ''));
        if ($code !== '' && $publicId !== '') {
            $flagsByCode[$code] = $publicId;
        }
    }
    foreach (['ai.enabled', 'ai.task'] as $requiredFlagCode) {
        $flagPublicId = (string)($flagsByCode[$requiredFlagCode] ?? '');
        assertTrue($flagPublicId !== '', 'Required feature flag public_id is missing for code: ' . $requiredFlagCode);
        $enableFlag = request('PATCH', '/api/v1/feature-flags/' . $flagPublicId, ['is_enabled' => 1], $headers);
        assertTrue($enableFlag['status'] === 200, 'Enable feature flag must return 200 for code: ' . $requiredFlagCode);
    }

    $taskCreate = request('POST', '/api/v1/tasks', [
        'title' => 'AI schema extra fields guard task ' . randomSuffix(),
        'description' => 'Task for extra fields schema sanitization smoke',
    ], $headers);
    assertTrue($taskCreate['status'] === 201, 'Task create must return 201');
    $taskPublicId = (string)($taskCreate['payload']['data']['task']['public_id'] ?? '');
    assertTrue($taskPublicId !== '', 'Task public_id is required');

    $summary = request('POST', '/api/v1/ai/tasks/' . $taskPublicId . '/summary', [], $headers);
    assertTrue(in_array($summary['status'], [200, 201], true), 'Task summary must return 200/201 with strict schema');

    $suggestion = is_array($summary['payload']['data']['suggestion'] ?? null) ? (array)$summary['payload']['data']['suggestion'] : [];
    $payload = is_array($suggestion['payload'] ?? null) ? (array)$suggestion['payload'] : [];
    assertTrue(array_key_exists('summary', $payload), 'Summary key must remain in sanitized payload');
    assertTrue(!array_key_exists('improved_description', $payload), 'Extra payload key improved_description must be dropped by schema sanitization');
    assertTrue(!array_key_exists('recommendations', $payload), 'Extra payload key recommendations must be dropped by schema sanitization');
    assertTrue(!array_key_exists('risks', $payload), 'Extra payload key risks must be dropped by schema sanitization');
    assertTrue(!array_key_exists('key_points', $payload), 'Extra payload key key_points must be dropped by schema sanitization');

    fwrite(STDOUT, "[OK] ai_extra_fields_schema_sanitization_smoke\n");
} catch (Throwable $e) {
    $failed = true;
    $failMessage = $e->getMessage();
} finally {
    if ($providerPublicId !== '' && $headers !== []) {
        request('DELETE', '/api/v1/ai/providers/' . $providerPublicId, [], $headers);
    }
    if ($previousActivePublicId !== '' && $headers !== []) {
        request('PATCH', '/api/v1/ai/json-schemas/' . $previousActivePublicId, [
            'is_active' => 1,
        ], $headers);
    }
    if ($failed) {
        fwrite(STDERR, '[FAIL] ai_extra_fields_schema_sanitization_smoke: ' . $failMessage . "\n");
        exit(1);
    }
}
