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
        'title' => 'My-day opt-out provider ' . randomSuffix(),
        'base_url' => 'https://example.com',
        'api_path' => '/v1/chat/completions',
        'default_model' => 'mock-my-day-opt-out',
        'provider_payload' => [
            'mock_models' => ['mock-my-day-opt-out'],
        ],
        'is_default' => 0,
        'is_active' => 1,
    ], $headers);
    assertTrue($providerCreate['status'] === 201, 'Provider create must return 201');
    $providerPublicId = (string)($providerCreate['payload']['data']['provider']['public_id'] ?? '');
    assertTrue($providerPublicId !== '', 'Provider public_id is required');

    $providerSecret = request('PUT', '/api/v1/ai/providers/' . $providerPublicId . '/secret', [
        'secret' => 'my-day-opt-out-secret-' . randomSuffix(),
    ], $headers);
    assertTrue($providerSecret['status'] === 200, 'Provider secret set must return 200');

    $disableDailyPlan = request('PATCH', '/api/v1/ai/preferences', [
        'preferences' => [
            'daily_plan_enabled' => 0,
        ],
    ], $headers);
    assertTrue($disableDailyPlan['status'] === 200, 'Disable daily plan preference must return 200');

    $blockedPlan = request('POST', '/api/v1/ai/my-day/plan', [], $headers);
    assertTrue($blockedPlan['status'] === 409, 'My-day plan must be blocked with 409 when daily_plan_enabled=false');
    assertTrue(
        (string)($blockedPlan['payload']['code'] ?? '') === 'AI_PREFERENCES_DAILY_PLAN_DISABLED',
        'Blocked my-day plan code must be AI_PREFERENCES_DAILY_PLAN_DISABLED'
    );

    $enableDailyPlan = request('PATCH', '/api/v1/ai/preferences', [
        'preferences' => [
            'daily_plan_enabled' => 1,
        ],
    ], $headers);
    assertTrue($enableDailyPlan['status'] === 200, 'Enable daily plan preference must return 200');

    $allowedPlan = request('POST', '/api/v1/ai/my-day/plan', [], $headers);
    assertTrue(in_array($allowedPlan['status'], [200, 201], true), 'My-day plan must be available again when daily_plan_enabled=true');

    request('DELETE', '/api/v1/ai/providers/' . $providerPublicId, [], $headers);

    fwrite(STDOUT, "[OK] ai_my_day_plan_respects_opt_out_preferences_smoke\n");
} catch (Throwable $e) {
    fwrite(STDERR, '[FAIL] ai_my_day_plan_respects_opt_out_preferences_smoke: ' . $e->getMessage() . "\n");
    exit(1);
}
