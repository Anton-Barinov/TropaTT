<?php
declare(strict_types=1);

require __DIR__ . '/_bootstrap.php';

function runCursorSyncSmoke(): void
{
    $auth = loginRoot();
    $headers = authHeaders($auth['token']);

    $suffix = randomSuffix();
    $project = request('POST', '/api/v1/projects', [
        'title' => 'Cursor Smoke Project ' . $suffix,
        'description' => 'cursor-sync',
    ], $headers);
    assertTrue($project['status'] === 201, 'Project create status must be 201');
    $projectPublicId = (string)($project['payload']['data']['project']['public_id'] ?? '');
    assertTrue($projectPublicId !== '', 'Project public_id is required');

    $taskOne = request('POST', '/api/v1/tasks', [
        'project_public_id' => $projectPublicId,
        'title' => 'Cursor Task A ' . $suffix,
    ], $headers);
    assertTrue($taskOne['status'] === 201, 'Task A create status must be 201');

    $taskTwo = request('POST', '/api/v1/tasks', [
        'project_public_id' => $projectPublicId,
        'title' => 'Cursor Task B ' . $suffix,
    ], $headers);
    assertTrue($taskTwo['status'] === 201, 'Task B create status must be 201');

    $firstPage = request(
        'GET',
        '/api/v1/tasks?project_public_id=' . rawurlencode($projectPublicId) . '&pagination_mode=cursor&sort=updated_at&order=DESC&limit=1',
        [],
        $headers
    );
    assertTrue($firstPage['status'] === 200, 'Cursor tasks first page status must be 200');
    assertTrue(($firstPage['payload']['meta']['pagination_mode'] ?? '') === 'cursor', 'Pagination mode must be cursor');
    $cursor = (string)($firstPage['payload']['meta']['cursor']['next'] ?? '');
    assertTrue($cursor !== '', 'Cursor next token is required');

    $firstItem = (string)($firstPage['payload']['data']['items'][0]['public_id'] ?? '');
    assertTrue($firstItem !== '', 'First page item public_id is required');

    $secondPage = request(
        'GET',
        '/api/v1/tasks?project_public_id=' . rawurlencode($projectPublicId) . '&pagination_mode=cursor&sort=updated_at&order=DESC&limit=1&cursor=' . rawurlencode($cursor),
        [],
        $headers
    );
    assertTrue($secondPage['status'] === 200, 'Cursor tasks second page status must be 200');
    $secondItem = (string)($secondPage['payload']['data']['items'][0]['public_id'] ?? '');
    assertTrue($secondItem !== '', 'Second page item public_id is required');
    assertTrue($secondItem !== $firstItem, 'Second page must return another item');

    $updatedSince = gmdate('Y-m-d H:i:s', time() - 86400);
    $syncFiltered = request(
        'GET',
        '/api/v1/tasks?project_public_id=' . rawurlencode($projectPublicId) . '&updated_since=' . rawurlencode($updatedSince) . '&limit=5',
        [],
        $headers
    );
    assertTrue($syncFiltered['status'] === 200, 'updated_since tasks status must be 200');
    assertTrue(($syncFiltered['payload']['meta']['sync']['updated_since'] ?? '') === $updatedSince, 'sync meta.updated_since must match input');

    $invalid = request('GET', '/api/v1/tasks?updated_since=not-a-date', [], $headers);
    assertTrue($invalid['status'] === 422, 'Invalid updated_since must return 422');

    $projectsCursor = request('GET', '/api/v1/projects?pagination_mode=cursor&sort=updated_at&order=DESC&limit=1', [], $headers);
    assertTrue($projectsCursor['status'] === 200, 'Projects cursor list status must be 200');
    assertTrue(($projectsCursor['payload']['meta']['pagination_mode'] ?? '') === 'cursor', 'Projects pagination mode must be cursor');
}

runCursorSyncSmoke();
echo "[OK] cursor_sync_smoke\n";
