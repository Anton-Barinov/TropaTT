<?php
declare(strict_types=1);

require __DIR__ . '/_bootstrap.php';

/** @param array<string,mixed> $item */
function assertPublicIdOnly(array $item, string $context): void
{
    assertTrue(!array_key_exists('id', $item), $context . ' must not expose integer id');
    assertTrue(trim((string)($item['public_id'] ?? '')) !== '', $context . ' must expose public_id');
}

/** @param list<array<string,mixed>> $items */
function assertItemsPublicIdOnly(array $items, string $context): void
{
    foreach ($items as $idx => $item) {
        if (!is_array($item)) {
            continue;
        }
        assertTrue(!array_key_exists('id', $item), $context . '[' . $idx . '] must not expose integer id');
        if (array_key_exists('public_id', $item)) {
            assertTrue(trim((string)$item['public_id']) !== '', $context . '[' . $idx . '] public_id must be non-empty');
        }
    }
}

try {
    $root = loginRoot();
    $headers = authHeaders($root['token']);

    $migrationRun = request('POST', '/internal/migration/up', [], $headers);
    assertTrue(in_array($migrationRun['status'], [200, 201], true), 'Migration up must return 200/201');

    $providerCreate = request('POST', '/api/v1/ai/providers', [
        'provider_code' => 'mock',
        'title' => 'PublicId Guard Provider ' . randomSuffix(),
        'base_url' => 'https://example.com',
        'api_path' => '/v1/chat/completions',
        'default_model' => 'mock-publicid-model',
        'is_default' => 0,
        'is_active' => 1,
    ], $headers);
    assertTrue($providerCreate['status'] === 201, 'Provider create status must be 201');
    $provider = (array)($providerCreate['payload']['data']['provider'] ?? []);
    assertPublicIdOnly($provider, 'provider create response');
    $providerPublicId = (string)($provider['public_id'] ?? '');

    $providersList = request('GET', '/api/v1/ai/providers', [], $headers);
    assertTrue($providersList['status'] === 200, 'Providers list status must be 200');
    $providerItems = (array)($providersList['payload']['data']['items'] ?? []);
    assertItemsPublicIdOnly($providerItems, 'providers.items');

    $providerGet = request('GET', '/api/v1/ai/providers/' . $providerPublicId, [], $headers);
    assertTrue($providerGet['status'] === 200, 'Provider get status must be 200');
    $providerDetail = (array)($providerGet['payload']['data']['provider'] ?? []);
    assertPublicIdOnly($providerDetail, 'provider detail');

    $intents = request('GET', '/api/v1/ai/intent-settings', [], $headers);
    assertTrue($intents['status'] === 200, 'Intent settings list status must be 200');
    assertItemsPublicIdOnly((array)($intents['payload']['data']['items'] ?? []), 'intent-settings.items');

    $prompts = request('GET', '/api/v1/ai/prompt-templates?intent_code=task_summary', [], $headers);
    assertTrue($prompts['status'] === 200, 'Prompt templates list status must be 200');
    assertItemsPublicIdOnly((array)($prompts['payload']['data']['items'] ?? []), 'prompt-templates.items');

    $schemas = request('GET', '/api/v1/ai/json-schemas?intent_code=task_summary', [], $headers);
    assertTrue($schemas['status'] === 200, 'JSON schemas list status must be 200');
    assertItemsPublicIdOnly((array)($schemas['payload']['data']['items'] ?? []), 'json-schemas.items');

    $taskCreate = request('POST', '/api/v1/tasks', [
        'title' => 'AI Public ID Contract Task ' . randomSuffix(),
        'description' => 'public_id contract smoke',
    ], $headers);
    assertTrue($taskCreate['status'] === 201, 'Task create status must be 201');
    $taskPublicId = (string)($taskCreate['payload']['data']['task']['public_id'] ?? '');
    assertTrue($taskPublicId !== '', 'Task public_id is required');

    $summary = request('POST', '/api/v1/ai/tasks/' . $taskPublicId . '/summary', [
        'prompt' => 'public id contract check',
    ], $headers);
    assertTrue($summary['status'] === 201, 'Task summary status must be 201');
    $suggestion = (array)($summary['payload']['data']['suggestion'] ?? []);
    assertPublicIdOnly($suggestion, 'task summary suggestion');
    assertTrue(!array_key_exists('id', (array)($summary['payload']['data'] ?? [])), 'Task summary response envelope must not expose id');
    assertTrue(trim((string)($summary['payload']['data']['job_public_id'] ?? '')) !== '', 'Task summary must expose job_public_id');

    $suggestions = request('GET', '/api/v1/ai/suggestions', ['limit' => 5], $headers);
    assertTrue($suggestions['status'] === 200, 'Suggestions list status must be 200');
    assertItemsPublicIdOnly((array)($suggestions['payload']['data']['items'] ?? []), 'suggestions.items');

    $jobs = request('GET', '/api/v1/ai/jobs', ['limit' => 5], $headers);
    assertTrue($jobs['status'] === 200, 'Jobs list status must be 200');
    assertItemsPublicIdOnly((array)($jobs['payload']['data']['items'] ?? []), 'jobs.items');

    request('DELETE', '/api/v1/ai/providers/' . $providerPublicId, [], $headers);

    fwrite(STDOUT, "[OK] ai_public_id_contract_smoke\n");
} catch (Throwable $e) {
    fwrite(STDERR, '[FAIL] ai_public_id_contract_smoke: ' . $e->getMessage() . "\n");
    exit(1);
}
