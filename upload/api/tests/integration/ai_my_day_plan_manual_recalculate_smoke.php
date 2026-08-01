<?php
declare(strict_types=1);

require __DIR__ . '/_bootstrap.php';

try {
    $root = loginRoot();
    $headers = authHeaders($root['token']);

    $migrationRun = request('POST', '/internal/migration/up', [], $headers);
    assertTrue(in_array($migrationRun['status'], [200, 201], true), 'Migration up must return 200/201');

    $flagsList = request('GET', '/api/v1/feature-flags', ['limit' => 200], $headers);
    assertTrue($flagsList['status'] === 200, 'Feature flags list status must be 200');
    $flags = is_array($flagsList['payload']['data']['items'] ?? null) ? (array)$flagsList['payload']['data']['items'] : [];
    $flagsByCode = [];
    foreach ($flags as $flag) {
        if (!is_array($flag)) {
            continue;
        }
        $code = trim((string)($flag['code'] ?? ''));
        $publicId = trim((string)($flag['public_id'] ?? ''));
        if ($code !== '' && $publicId !== '') {
            $flagsByCode[$code] = $publicId;
        }
    }
    foreach (['ai.enabled', 'ai.calendar', 'ai.cron.daily_work_plan'] as $requiredFlag) {
        $flagPublicId = (string)($flagsByCode[$requiredFlag] ?? '');
        assertTrue($flagPublicId !== '', 'Missing feature flag for code: ' . $requiredFlag);
        $enable = request('PATCH', '/api/v1/feature-flags/' . $flagPublicId, ['is_enabled' => 1], $headers);
        assertTrue($enable['status'] === 200, 'Enable feature flag must return 200 for code: ' . $requiredFlag);
    }

    $providerCreate = request('POST', '/api/v1/ai/providers', [
        'provider_code' => 'mock',
        'title' => 'My-day manual recalc provider ' . randomSuffix(),
        'base_url' => 'https://example.com',
        'api_path' => '/v1/chat/completions',
        'default_model' => 'mock-my-day-manual-recalc',
        'provider_payload' => [
            'mock_models' => ['mock-my-day-manual-recalc'],
        ],
        'is_default' => 0,
        'is_active' => 1,
    ], $headers);
    assertTrue($providerCreate['status'] === 201, 'Provider create must return 201');
    $providerPublicId = (string)($providerCreate['payload']['data']['provider']['public_id'] ?? '');
    assertTrue($providerPublicId !== '', 'Provider public_id is required');

    $providerSecret = request('PUT', '/api/v1/ai/providers/' . $providerPublicId . '/secret', [
        'secret' => 'my-day-manual-recalc-secret-' . randomSuffix(),
    ], $headers);
    assertTrue($providerSecret['status'] === 200, 'Provider secret set must return 200');

    $firstPlan = request('POST', '/api/v1/ai/my-day/plan', [], $headers);
    assertTrue(in_array($firstPlan['status'], [200, 201], true), 'First my-day plan create must return 200/201');
    $firstSuggestion = is_array($firstPlan['payload']['data']['suggestion'] ?? null)
        ? (array)$firstPlan['payload']['data']['suggestion']
        : [];
    $firstSuggestionPublicId = trim((string)($firstSuggestion['public_id'] ?? ''));
    assertTrue($firstSuggestionPublicId !== '', 'First plan must return suggestion public_id');

    $secondPlan = request('POST', '/api/v1/ai/my-day/plan', [
        'regenerate' => 1,
    ], $headers);
    assertTrue(in_array($secondPlan['status'], [200, 201], true), 'Second my-day plan create must return 200/201');
    $secondSuggestion = is_array($secondPlan['payload']['data']['suggestion'] ?? null)
        ? (array)$secondPlan['payload']['data']['suggestion']
        : [];
    $secondSuggestionPublicId = trim((string)($secondSuggestion['public_id'] ?? ''));
    assertTrue($secondSuggestionPublicId !== '', 'Second plan must return suggestion public_id');
    assertTrue(
        $secondSuggestionPublicId !== $firstSuggestionPublicId,
        'Manual re-calculate must create a new suggestion public_id'
    );

    $firstMode = strtolower(trim((string)($firstSuggestion['payload']['meta']['execution_mode'] ?? '')));
    $secondMode = strtolower(trim((string)($secondSuggestion['payload']['meta']['execution_mode'] ?? '')));
    assertTrue($firstMode === 'manual', 'First my-day plan execution_mode must be manual');
    assertTrue($secondMode === 'manual', 'Second my-day plan execution_mode must be manual');

    request('DELETE', '/api/v1/ai/providers/' . $providerPublicId, [], $headers);

    fwrite(STDOUT, "[OK] ai_my_day_plan_manual_recalculate_smoke\n");
} catch (Throwable $e) {
    fwrite(STDERR, '[FAIL] ai_my_day_plan_manual_recalculate_smoke: ' . $e->getMessage() . "\n");
    exit(1);
}
