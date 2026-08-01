<?php
declare(strict_types=1);

require __DIR__ . '/_bootstrap.php';

try {
    $root = loginRoot();
    $rootHeaders = authHeaders($root['token']);

    $migrationRun = request('POST', '/internal/migration/up', [], $rootHeaders);
    assertTrue(in_array($migrationRun['status'], [200, 201], true), 'Migration up must return 200/201');

    $small = request('POST', '/api/v1/ai/actions/task_summary', [
        'scope_type' => 'task',
        'scope_public_id' => 'tsk_payload_small_' . randomSuffix(),
        'input_text' => 'small payload',
    ], $rootHeaders);
    assertTrue((string)($small['payload']['code'] ?? '') !== 'AI_REQUEST_PAYLOAD_TOO_LARGE', 'Small AI payload must not hit size guard');

    $large = request('POST', '/api/v1/ai/actions/task_summary', [
        'scope_type' => 'task',
        'scope_public_id' => 'tsk_payload_large_' . randomSuffix(),
        'input_text' => str_repeat('A', 5000),
    ], $rootHeaders);
    assertTrue($large['status'] === 413, 'Oversized AI payload must return 413');
    assertTrue((string)($large['payload']['code'] ?? '') === 'AI_REQUEST_PAYLOAD_TOO_LARGE', 'Oversized AI payload code mismatch');
    assertTrue((bool)($large['payload']['success'] ?? true) === false, 'Oversized AI payload response must be unsuccessful');
    assertTrue(isset($large['payload']['meta']['request_id']), 'Oversized AI payload response must keep standard envelope meta.request_id');
    assertTrue(isset($large['payload']['meta']['correlation_id']), 'Oversized AI payload response must keep standard envelope meta.correlation_id');

    fwrite(STDOUT, "[OK] ai_request_payload_size_limit_smoke\n");
} catch (Throwable $e) {
    fwrite(STDERR, "[FAIL] ai_request_payload_size_limit_smoke: " . $e->getMessage() . "\n");
    exit(1);
}

