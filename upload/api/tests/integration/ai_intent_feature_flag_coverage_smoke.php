<?php
declare(strict_types=1);

require __DIR__ . '/_bootstrap.php';

try {
    $root = loginRoot();
    $rootHeaders = authHeaders($root['token']);

    $migrationRun = request('POST', '/internal/migration/up', [], $rootHeaders);
    assertTrue(in_array($migrationRun['status'], [200, 201], true), 'Migration up must return 200/201');

    $intentSettings = request('GET', '/api/v1/ai/intent-settings', [], $rootHeaders);
    assertTrue($intentSettings['status'] === 200, 'Intent settings list status must be 200');
    $intentItems = (array)($intentSettings['payload']['data']['items'] ?? []);
    assertTrue(count($intentItems) > 0, 'Intent settings must not be empty');

    $emptyFeatureFlagIntents = [];
    foreach ($intentItems as $intent) {
        if (!is_array($intent)) {
            continue;
        }

        $intentCode = (string)($intent['intent_code'] ?? '');
        if ($intentCode === '') {
            continue;
        }

        $featureFlag = trim((string)($intent['feature_flag'] ?? ''));
        if ($featureFlag === '') {
            $emptyFeatureFlagIntents[] = $intentCode;
        }
    }

    assertTrue($emptyFeatureFlagIntents === [], 'Every intent must have feature_flag or ai.enabled fallback. Missing: ' . implode(', ', $emptyFeatureFlagIntents));

    fwrite(STDOUT, "[OK] ai_intent_feature_flag_coverage_smoke\n");
} catch (Throwable $e) {
    fwrite(STDERR, "[FAIL] ai_intent_feature_flag_coverage_smoke: " . $e->getMessage() . "\n");
    exit(1);
}
