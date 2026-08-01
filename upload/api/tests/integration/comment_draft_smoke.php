<?php
declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';

try {
    $auth = loginRoot();
    $headers = authHeaders($auth['token']);

    $migrationUp = request('POST', '/internal/migration/up', [], $headers);
    assertTrue($migrationUp['status'] === 200, 'Migration up status must be 200');

    $project = request('POST', '/api/v1/projects', [
        'title' => 'Draft smoke project ' . randomSuffix(),
        'description' => 'draft smoke',
    ], $headers);
    assertTrue($project['status'] === 201, 'Project create status must be 201');
    $projectPublicId = (string)($project['payload']['data']['project']['public_id'] ?? '');
    assertTrue($projectPublicId !== '', 'Project public_id required');

    $task = request('POST', '/api/v1/tasks', [
        'title' => 'Draft smoke task',
        'project_public_id' => $projectPublicId,
    ], $headers);
    assertTrue($task['status'] === 201, 'Task create status must be 201');
    $taskPublicId = (string)($task['payload']['data']['task']['public_id'] ?? '');
    assertTrue($taskPublicId !== '', 'Task public_id required');

    $save = request('POST', '/api/v1/tasks/' . $taskPublicId . '/comment-draft', [
        'body' => 'Draft content v1',
    ], $headers);
    assertTrue($save['status'] === 200, 'Comment draft save status must be 200');
    assertTrue(($save['payload']['code'] ?? '') === 'COMMENT_DRAFT_SAVED', 'Comment draft save code mismatch');

    $get = request('GET', '/api/v1/tasks/' . $taskPublicId . '/comment-draft', [], $headers);
    assertTrue($get['status'] === 200, 'Comment draft get status must be 200');
    assertTrue(($get['payload']['code'] ?? '') === 'COMMENT_DRAFT_DETAIL', 'Comment draft get code mismatch');
    assertTrue(($get['payload']['data']['draft']['body'] ?? '') === 'Draft content v1', 'Comment draft body mismatch');

    $aliasSave = request('POST', '/api/v1/comment/draft/save/' . $taskPublicId, [
        'body' => 'Draft content alias',
    ], $headers);
    assertTrue($aliasSave['status'] === 200, 'Comment draft alias save status must be 200');

    $aliasGet = request('GET', '/api/v1/comment/draft/get/' . $taskPublicId, [], $headers);
    assertTrue($aliasGet['status'] === 200, 'Comment draft alias get status must be 200');
    assertTrue(($aliasGet['payload']['data']['draft']['body'] ?? '') === 'Draft content alias', 'Comment draft alias body mismatch');

    $delete = request('DELETE', '/api/v1/tasks/' . $taskPublicId . '/comment-draft', [], $headers);
    assertTrue($delete['status'] === 200, 'Comment draft delete status must be 200');
    assertTrue(($delete['payload']['code'] ?? '') === 'COMMENT_DRAFT_DELETED', 'Comment draft delete code mismatch');

    $afterDelete = request('GET', '/api/v1/tasks/' . $taskPublicId . '/comment-draft', [], $headers);
    assertTrue($afterDelete['status'] === 200, 'Comment draft get after delete status must be 200');
    assertTrue(array_key_exists('draft', (array)($afterDelete['payload']['data'] ?? [])), 'Comment draft data key must exist after delete');
    $draftAfterDelete = $afterDelete['payload']['data']['draft'] ?? null;
    assertTrue($draftAfterDelete === null, 'Comment draft must be null after delete');

    $unauthorized = request('GET', '/api/v1/tasks/' . $taskPublicId . '/comment-draft');
    assertTrue($unauthorized['status'] === 401, 'Comment draft unauthorized status must be 401');

    echo "[OK] Comment draft save/restore smoke passed\n";
    exit(0);
} catch (Throwable $e) {
    fwrite(STDERR, '[FAIL] ' . $e->getMessage() . "\n");
    exit(1);
}
