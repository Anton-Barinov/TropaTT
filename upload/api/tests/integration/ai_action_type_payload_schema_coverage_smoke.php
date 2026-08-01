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

    $schemasResponse = request('GET', '/api/v1/ai/json-schemas', [], $rootHeaders);
    assertTrue($schemasResponse['status'] === 200, 'JSON schemas list status must be 200');
    $schemaItems = (array)($schemasResponse['payload']['data']['items'] ?? []);

    $activeSchemasByIntent = [];
    foreach ($schemaItems as $schema) {
        if (!is_array($schema) || !(bool)($schema['is_active'] ?? false)) {
            continue;
        }

        $intentCode = trim((string)($schema['intent_code'] ?? ''));
        if ($intentCode === '') {
            continue;
        }

        $schemaJson = $schema['schema_json'] ?? null;
        if (!is_array($schemaJson)) {
            continue;
        }

        $activeSchemasByIntent[$intentCode] = true;
    }

    $missingSchemas = [];
    foreach ($actionTypes as $actionType) {
        $intentCode = trim((string)$actionType);
        if ($intentCode === '') {
            continue;
        }

        if (!isset($activeSchemasByIntent[$intentCode])) {
            $missingSchemas[] = $intentCode;
        }
    }

    assertTrue($missingSchemas === [], 'Each action type must have an active payload schema. Missing: ' . implode(', ', $missingSchemas));

    fwrite(STDOUT, "[OK] ai_action_type_payload_schema_coverage_smoke\n");
} catch (Throwable $e) {
    fwrite(STDERR, "[FAIL] ai_action_type_payload_schema_coverage_smoke: " . $e->getMessage() . "\n");
    exit(1);
}
