<?php
declare(strict_types=1);

require __DIR__ . '/_bootstrap.php';

function runtimeSqlitePath945(): string
{
    $base = trim((string)getenv('CRM_STORAGE_BASE'));
    if ($base === '') {
        $base = dirname(__DIR__, 3) . '/../storage_api';
    }

    return rtrim($base, '/\\') . '/temp/crm.sqlite';
}

/** @var PDO|null $pdo */
$pdo = null;
$originalTaskPublicId = '';
$invalidTaskPublicId = '';

try {
    $root = loginRoot();
    $headers = authHeaders($root['token']);

    $migrationRun = request('POST', '/internal/migration/up', [], $headers);
    assertTrue(in_array($migrationRun['status'], [200, 201], true), 'Migration up must return 200/201');

    $providerCreate = request('POST', '/api/v1/ai/providers', [
        'provider_code' => 'mock',
        'title' => 'Public ID validation provider ' . randomSuffix(),
        'base_url' => 'https://example.com',
        'api_path' => '/v1/chat/completions',
        'default_model' => 'mock-public-id-validation',
        'provider_payload' => [
            'mock_models' => ['mock-public-id-validation'],
        ],
        'is_default' => 0,
        'is_active' => 1,
    ], $headers);
    assertTrue($providerCreate['status'] === 201, 'Provider create must return 201');
    $providerPublicId = (string)($providerCreate['payload']['data']['provider']['public_id'] ?? '');
    assertTrue($providerPublicId !== '', 'Provider public_id is required');

    $providerSecret = request('PUT', '/api/v1/ai/providers/' . $providerPublicId . '/secret', [
        'secret' => 'public-id-validation-secret-' . randomSuffix(),
    ], $headers);
    assertTrue($providerSecret['status'] === 200, 'Provider secret set must return 200');

    $sqlitePath = runtimeSqlitePath945();
    assertTrue(is_file($sqlitePath), 'Runtime sqlite file must exist: ' . $sqlitePath);
    $pdo = new PDO('sqlite:' . $sqlitePath);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $taskCreate = request('POST', '/api/v1/tasks', [
        'title' => 'AI public_id validation task ' . randomSuffix(),
        'description' => 'Task for public_id validation before suggestion save',
    ], $headers);
    assertTrue($taskCreate['status'] === 201, 'Task create must return 201');
    $originalTaskPublicId = (string)($taskCreate['payload']['data']['task']['public_id'] ?? '');
    assertTrue($originalTaskPublicId !== '', 'Task public_id is required');

    $invalidTaskPublicId = 'invalidtaskpublicid' . bin2hex(random_bytes(6));
    $setInvalid = $pdo->prepare('UPDATE tasks SET public_id = :new_public_id WHERE public_id = :old_public_id');
    $setInvalid->execute([
        'new_public_id' => $invalidTaskPublicId,
        'old_public_id' => $originalTaskPublicId,
    ]);
    assertTrue($setInvalid->rowCount() === 1, 'Task public_id update to invalid value must affect exactly one row');

    $summary = request('POST', '/api/v1/ai/tasks/' . $invalidTaskPublicId . '/summary', [], $headers);
    $summaryStatus = (int)$summary['status'];
    $summaryCode = (string)($summary['payload']['code'] ?? '');
    assertTrue(in_array($summaryStatus, [404, 422], true), 'Task summary with invalid task public_id must be rejected before suggestion save');
    assertTrue(in_array($summaryCode, ['TASK_NOT_FOUND', 'AI_SCOPE_PUBLIC_ID_INVALID'], true), 'Invalid task public_id rejection code mismatch');

    $suggestionCountStmt = $pdo->prepare('SELECT COUNT(*) FROM ai_suggestions WHERE entity_type = :entity_type AND entity_public_id = :entity_public_id');
    $suggestionCountStmt->execute([
        'entity_type' => 'task',
        'entity_public_id' => $invalidTaskPublicId,
    ]);
    $invalidTaskSuggestions = (int)$suggestionCountStmt->fetchColumn();
    assertTrue($invalidTaskSuggestions === 0, 'Suggestion must not be saved with invalid task public_id');

    request('DELETE', '/api/v1/ai/providers/' . $providerPublicId, [], $headers);

    fwrite(STDOUT, "[OK] ai_public_id_validation_before_suggestion_save_smoke\n");
} catch (Throwable $e) {
    fwrite(STDERR, '[FAIL] ai_public_id_validation_before_suggestion_save_smoke: ' . $e->getMessage() . "\n");
    exit(1);
} finally {
    if ($pdo instanceof PDO && $originalTaskPublicId !== '' && $invalidTaskPublicId !== '') {
        try {
            $restore = $pdo->prepare('UPDATE tasks SET public_id = :old_public_id WHERE public_id = :new_public_id');
            $restore->execute([
                'old_public_id' => $originalTaskPublicId,
                'new_public_id' => $invalidTaskPublicId,
            ]);
        } catch (Throwable $e) {
            error_log('[AiPublicIdValidationSmoke] ' . $e->getMessage());
            // Best-effort restore in test cleanup.
        }
    }
}
