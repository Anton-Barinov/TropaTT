<?php
declare(strict_types=1);

require __DIR__ . '/_bootstrap.php';

try {
    $root = loginRoot();
    $rootHeaders = authHeaders($root['token']);

    $migration = request('POST', '/internal/migration/up', [], $rootHeaders);
    assertTrue(in_array($migration['status'], [200, 201], true), 'Migration up must return 200/201');

    $actionTypesResponse = request('GET', '/api/v1/ai/action-types', [], $rootHeaders);
    assertTrue($actionTypesResponse['status'] === 200, 'Action types list status must be 200');
    $actionTypes = (array)($actionTypesResponse['payload']['data']['items'] ?? []);
    assertTrue(count($actionTypes) > 0, 'Action types allowlist must not be empty');

    $forbiddenExact = [
        'run_sql',
        'run_shell',
        'change_provider_settings',
        'disable_security_control',
    ];
    $forbiddenPrefixes = [
        'delete_',
    ];

    $violations = [];
    foreach ($actionTypes as $actionTypeRaw) {
        $actionType = trim((string)$actionTypeRaw);
        if ($actionType === '') {
            continue;
        }
        if (in_array($actionType, $forbiddenExact, true)) {
            $violations[] = $actionType;
            continue;
        }
        foreach ($forbiddenPrefixes as $prefix) {
            if (str_starts_with($actionType, $prefix)) {
                $violations[] = $actionType;
                break;
            }
        }
    }

    assertTrue($violations === [], 'Destructive action types must not be present in start allowlist. Violations: ' . implode(', ', $violations));

    $configAllowlist = (require dirname(__DIR__, 2) . '/config/ai.php')['actions']['allowlist'] ?? [];
    assertTrue(is_array($configAllowlist), 'Config allowlist must be an array');
    foreach ($configAllowlist as $actionTypeRaw) {
        $actionType = trim((string)$actionTypeRaw);
        if ($actionType === '') {
            continue;
        }
        assertTrue(!in_array($actionType, $forbiddenExact, true), 'Forbidden exact action must not be present in config allowlist: ' . $actionType);
        foreach ($forbiddenPrefixes as $prefix) {
            assertTrue(!str_starts_with($actionType, $prefix), 'Forbidden destructive prefix must not be present in config allowlist: ' . $actionType);
        }
    }

    fwrite(STDOUT, "[OK] ai_action_type_destructive_allowlist_guard_smoke\n");
} catch (Throwable $e) {
    fwrite(STDERR, "[FAIL] ai_action_type_destructive_allowlist_guard_smoke: " . $e->getMessage() . "\n");
    exit(1);
}
