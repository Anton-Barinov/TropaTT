<?php
declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';

try {
    $auth = loginRoot();
    $headers = authHeaders($auth['token']);

    $suffix = randomSuffix();

    $projectKey = 'idem-project-' . $suffix;
    $projectBody = ['title' => 'Idem Project ' . $suffix];
    $projectCreate1 = request('POST', '/api/v1/projects', $projectBody, array_merge($headers, ['X-Idempotency-Key' => $projectKey]));
    assertTrue($projectCreate1['status'] === 201, 'Project create #1 status must be 201');
    $projectCreate2 = request('POST', '/api/v1/projects', $projectBody, array_merge($headers, ['X-Idempotency-Key' => $projectKey]));
    assertTrue($projectCreate2['status'] === 201, 'Project create #2 status must be 201');
    $projectPublicId1 = (string)($projectCreate1['payload']['data']['project']['public_id'] ?? '');
    $projectPublicId2 = (string)($projectCreate2['payload']['data']['project']['public_id'] ?? '');
    assertTrue($projectPublicId1 !== '' && $projectPublicId1 === $projectPublicId2, 'Project idempotency must return same public_id');
    assertTrue((bool)($projectCreate2['payload']['meta']['idempotency_replayed'] ?? false) === true, 'Project replay meta must be true');

    $taskKey = 'idem-task-' . $suffix;
    $taskBody = ['title' => 'Idem Task ' . $suffix, 'project_public_id' => $projectPublicId1];
    $taskCreate1 = request('POST', '/api/v1/tasks', $taskBody, array_merge($headers, ['X-Idempotency-Key' => $taskKey]));
    assertTrue($taskCreate1['status'] === 201, 'Task create #1 status must be 201');
    $taskCreate2 = request('POST', '/api/v1/tasks', $taskBody, array_merge($headers, ['X-Idempotency-Key' => $taskKey]));
    assertTrue($taskCreate2['status'] === 201, 'Task create #2 status must be 201');
    $taskPublicId1 = (string)($taskCreate1['payload']['data']['task']['public_id'] ?? '');
    $taskPublicId2 = (string)($taskCreate2['payload']['data']['task']['public_id'] ?? '');
    assertTrue($taskPublicId1 !== '' && $taskPublicId1 === $taskPublicId2, 'Task idempotency must return same public_id');
    assertTrue((bool)($taskCreate2['payload']['meta']['idempotency_replayed'] ?? false) === true, 'Task replay meta must be true');

    $taskGet = request('GET', '/api/v1/tasks/' . $taskPublicId1, [], $headers);
    assertTrue($taskGet['status'] === 200, 'Task get status must be 200');
    $currentRowVersion = (int)($taskGet['payload']['data']['task']['row_version'] ?? 0);
    assertTrue($currentRowVersion > 0, 'Task row_version must be > 0');

    $taskUpdateOk = request('PATCH', '/api/v1/tasks/' . $taskPublicId1, [
        'description' => 'First update ' . $suffix,
        'row_version' => $currentRowVersion,
    ], $headers);
    assertTrue($taskUpdateOk['status'] === 200, 'Task update with current row_version must be 200');

    $taskUpdateConflict = request('PATCH', '/api/v1/tasks/' . $taskPublicId1, [
        'description' => 'Conflict update ' . $suffix,
        'row_version' => $currentRowVersion,
    ], $headers);
    assertTrue($taskUpdateConflict['status'] === 409, 'Task update conflict status must be 409');
    assertTrue(($taskUpdateConflict['payload']['code'] ?? '') === 'ROW_VERSION_CONFLICT', 'Task update conflict code mismatch');

    echo "[OK] Idempotency/RowVersion smoke passed\n";
    exit(0);
} catch (Throwable $e) {
    fwrite(STDERR, '[FAIL] ' . $e->getMessage() . "\n");
    exit(1);
}
