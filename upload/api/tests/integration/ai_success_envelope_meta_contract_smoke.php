<?php
declare(strict_types=1);

require __DIR__ . '/_bootstrap.php';

/** @param array{status:int,payload:array<string,mixed>} $response */
function assertSuccessEnvelopeMeta(array $response, string $context): void
{
    assertTrue($response['status'] >= 200 && $response['status'] < 300, $context . ' status must be 2xx');
    $payload = $response['payload'];
    assertTrue(array_key_exists('success', $payload) && (bool)$payload['success'] === true, $context . ' success must be true');
    assertTrue(trim((string)($payload['code'] ?? '')) !== '', $context . ' code must be non-empty');
    assertTrue(is_array($payload['meta'] ?? null), $context . ' meta must be array');
    $meta = (array)$payload['meta'];
    assertTrue(trim((string)($meta['request_id'] ?? '')) !== '', $context . ' meta.request_id must be present');
    assertTrue(trim((string)($meta['correlation_id'] ?? '')) !== '', $context . ' meta.correlation_id must be present');
}

try {
    $root = loginRoot();
    $headers = authHeaders($root['token']);

    $migrationRun = request('POST', '/internal/migration/up', [], $headers);
    assertTrue(in_array($migrationRun['status'], [200, 201], true), 'Migration up must return 200/201');

    $actionTypes = request('GET', '/api/v1/ai/action-types', [], $headers);
    assertSuccessEnvelopeMeta($actionTypes, 'AI action-types envelope');

    $suggestions = request('GET', '/api/v1/ai/suggestions?limit=1', [], $headers);
    assertSuccessEnvelopeMeta($suggestions, 'AI suggestions list envelope');

    fwrite(STDOUT, "[OK] ai_success_envelope_meta_contract_smoke\n");
} catch (Throwable $e) {
    fwrite(STDERR, '[FAIL] ai_success_envelope_meta_contract_smoke: ' . $e->getMessage() . "\n");
    exit(1);
}

