<?php
declare(strict_types=1);

require __DIR__ . '/_bootstrap.php';

try {
    $catalogPath = __DIR__ . '/../../system/library/service/AiJobService.php';
    $configPath = __DIR__ . '/../../config/ai.php';

    assertTrue(is_file($catalogPath), 'AiJobService file must exist');
    assertTrue(is_file($configPath), 'config/ai.php must exist');

    $catalogSource = (string)file_get_contents($catalogPath);
    assertTrue($catalogSource !== '', 'AiJobService source must be readable');

    $aiConfig = require $configPath;
    $allowlist = is_array($aiConfig['actions']['allowlist'] ?? null) ? (array)$aiConfig['actions']['allowlist'] : [];
    $allowMap = [];
    foreach ($allowlist as $code) {
        if (is_string($code) && $code !== '') {
            $allowMap[$code] = true;
        }
    }

    preg_match_all("/'action_type'\\s*=>\\s*'([^']+)'/u", $catalogSource, $matches);
    $actionTypes = array_values(array_unique(array_filter((array)($matches[1] ?? []), static fn($v): bool => is_string($v) && $v !== '')));
    assertTrue($actionTypes !== [], 'Cron action_type catalog must not be empty');

    foreach ($actionTypes as $actionType) {
        $normalized = strtolower(trim($actionType));
        foreach (['delete', 'drop', 'truncate', 'shell', 'sql', 'permission_change', 'role_change', 'api_client_mutation', 'webhook_mutation'] as $forbidden) {
            assertTrue(
                !str_contains($normalized, $forbidden),
                'Cron catalog must not include destructive action_type fragment: ' . $actionType
            );
        }

        if ($normalized === 'suggestion_cleanup') {
            continue;
        }

        assertTrue(
            isset($allowMap[$actionType]),
            'Cron action_type must be present in AI allowlist (or be suggestion_cleanup): ' . $actionType
        );
    }

    fwrite(STDOUT, "[OK] ai_cron_non_destructive_actions_guard_smoke\n");
} catch (Throwable $e) {
    fwrite(STDERR, '[FAIL] ai_cron_non_destructive_actions_guard_smoke: ' . $e->getMessage() . "\n");
    exit(1);
}
