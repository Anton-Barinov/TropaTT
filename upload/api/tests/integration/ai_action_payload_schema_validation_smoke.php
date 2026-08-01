<?php
declare(strict_types=1);

require __DIR__ . '/_bootstrap.php';

function runtimeSqlitePath834(): string
{
    $base = trim((string)getenv('CRM_STORAGE_BASE'));
    if ($base === '') {
        $base = dirname(__DIR__, 3) . '/../storage_api';
    }

    return rtrim($base, '/\\') . '/temp/crm.sqlite';
}

try {
    $root = loginRoot();
    $headers = authHeaders($root['token']);

    $migrationRun = request('POST', '/internal/migration/up', [], $headers);
    assertTrue(in_array($migrationRun['status'], [200, 201], true), 'Migration up must return 200/201');

    $projectCreate = request('POST', '/api/v1/projects', [
        'title' => 'AI action payload schema project ' . randomSuffix(),
    ], $headers);
    assertTrue($projectCreate['status'] === 201, 'Project create must return 201');
    $projectPublicId = (string)($projectCreate['payload']['data']['project']['public_id'] ?? '');
    assertTrue($projectPublicId !== '', 'Project public_id is required');

    $taskCreate = request('POST', '/api/v1/tasks', [
        'title' => 'AI action payload schema task ' . randomSuffix(),
        'project_public_id' => $projectPublicId,
    ], $headers);
    assertTrue($taskCreate['status'] === 201, 'Task create must return 201');
    $taskPublicId = (string)($taskCreate['payload']['data']['task']['public_id'] ?? '');
    assertTrue($taskPublicId !== '', 'Task public_id is required');

    $enableDecompose = request('PATCH', '/api/v1/ai/intent-settings/task_decomposition', [
        'is_enabled' => 1,
    ], $headers);
    assertTrue($enableDecompose['status'] === 200, 'task_decomposition intent must be enabled');

    $enableCommentDraft = request('PATCH', '/api/v1/ai/intent-settings/task_comment_draft', [
        'is_enabled' => 1,
    ], $headers);
    assertTrue($enableCommentDraft['status'] === 200, 'task_comment_draft intent must be enabled');

    $sqlitePath = runtimeSqlitePath834();
    assertTrue(is_file($sqlitePath), 'Runtime sqlite file must exist: ' . $sqlitePath);
    $pdo = new PDO('sqlite:' . $sqlitePath);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $insert = $pdo->prepare('INSERT INTO ai_suggestions (public_id, intent_code, entity_type, entity_public_id, summary, suggestion_json, status, created_by_user_id, confirmed_by_user_id, created_at, updated_at, expires_at) VALUES (:public_id, :intent_code, :entity_type, :entity_public_id, :summary, :suggestion_json, :status, :created_by_user_id, :confirmed_by_user_id, :created_at, :updated_at, :expires_at)');
    // Use runtime-local wall clock format for SQLite datetime fields.
    // gmdate() here can make expires_at look already expired when runtime
    // parses naive datetime strings in local timezone.
    $now = date('Y-m-d H:i:s');
    $expiresAt = date('Y-m-d H:i:s', time() + 3600);

    $decomposeSuggestionPublicId = 'aisug_' . randomSuffix();
    $decomposePayload = json_encode([
        'summary' => 'Invalid decomposition payload',
        'suggested_tasks' => [
            ['title' => 12345],
            ['title' => 'Valid title but invalid description type', 'description' => ['not', 'a', 'string']],
            ['title' => '   '],
        ],
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    assertTrue(is_string($decomposePayload), 'Decomposition payload json encode must succeed');

    $insert->execute([
        'public_id' => $decomposeSuggestionPublicId,
        'intent_code' => 'task_decomposition',
        'entity_type' => 'task',
        'entity_public_id' => $taskPublicId,
        'summary' => 'Invalid decomposition payload',
        'suggestion_json' => $decomposePayload,
        'status' => 'draft',
        'created_by_user_id' => null,
        'confirmed_by_user_id' => null,
        'created_at' => $now,
        'updated_at' => $now,
        'expires_at' => $expiresAt,
    ]);

    $commentSuggestionPublicId = 'aisug_' . randomSuffix();
    $commentPayload = json_encode([
        'summary' => '',
        'comment_draft' => ['invalid-array-instead-of-string'],
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    assertTrue(is_string($commentPayload), 'Comment payload json encode must succeed');

    $insert->execute([
        'public_id' => $commentSuggestionPublicId,
        'intent_code' => 'task_comment_draft',
        'entity_type' => 'task',
        'entity_public_id' => $taskPublicId,
        'summary' => '',
        'suggestion_json' => $commentPayload,
        'status' => 'draft',
        'created_by_user_id' => null,
        'confirmed_by_user_id' => null,
        'created_at' => $now,
        'updated_at' => $now,
        'expires_at' => $expiresAt,
    ]);

    $decomposePreview = request('POST', '/api/v1/ai/suggestions/' . $decomposeSuggestionPublicId . '/preview-apply', [], $headers);
    assertTrue($decomposePreview['status'] === 200, 'Decomposition preview must return 200');
    $decomposeChanges = (array)($decomposePreview['payload']['data']['preview']['changes'] ?? []);
    assertTrue($decomposeChanges === [], 'Invalid create_subtask payloads must be rejected by action payload schema validation');
    $decomposeEndpoints = (array)($decomposePreview['payload']['data']['preview']['supported_apply_endpoints'] ?? []);
    assertTrue($decomposeEndpoints === [], 'No apply endpoints must be exposed when all action payloads are invalid');

    $commentPreview = request('POST', '/api/v1/ai/suggestions/' . $commentSuggestionPublicId . '/preview-apply', [], $headers);
    assertTrue($commentPreview['status'] === 200, 'Comment-draft preview must return 200');
    $commentChanges = (array)($commentPreview['payload']['data']['preview']['changes'] ?? []);
    assertTrue($commentChanges === [], 'Invalid comment draft payload must be rejected by action payload schema validation');
    $commentEndpoints = (array)($commentPreview['payload']['data']['preview']['supported_apply_endpoints'] ?? []);
    assertTrue($commentEndpoints === [], 'No apply endpoints must be exposed for invalid comment payload');

    fwrite(STDOUT, "[OK] ai_action_payload_schema_validation_smoke\n");
} catch (Throwable $e) {
    fwrite(STDERR, '[FAIL] ai_action_payload_schema_validation_smoke: ' . $e->getMessage() . "\n");
    exit(1);
}
