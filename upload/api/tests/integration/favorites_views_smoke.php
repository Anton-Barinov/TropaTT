<?php
declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';

try {
    $auth = loginRoot();
    $headers = authHeaders($auth['token']);

    $tasks = request('GET', '/api/v1/tasks?limit=1', [], $headers);
    assertTrue($tasks['status'] === 200, 'Tasks list status must be 200');
    $taskPublicId = (string)($tasks['payload']['data']['items'][0]['public_id'] ?? '');
    assertTrue($taskPublicId !== '', 'Task public_id required');

    $favoriteCreate = request('POST', '/api/v1/favorites', [
        'entity_type' => 'task',
        'entity_public_id' => $taskPublicId,
    ], $headers);
    assertTrue($favoriteCreate['status'] === 201, 'Favorite create status must be 201');
    $favoritePublicId = (string)($favoriteCreate['payload']['data']['favorite']['public_id'] ?? '');
    assertTrue($favoritePublicId !== '', 'Favorite public_id required');

    $favoriteList = request('GET', '/api/v1/favorites?entity_type=task&entity_public_id=' . $taskPublicId, [], $headers);
    assertTrue($favoriteList['status'] === 200, 'Favorite list status must be 200');
    assertTrue(($favoriteList['payload']['code'] ?? '') === 'FAVORITE_LIST', 'Favorite list code mismatch');

    $favoriteDelete = request('DELETE', '/api/v1/favorites/' . $favoritePublicId, [], $headers);
    assertTrue($favoriteDelete['status'] === 200, 'Favorite delete status must be 200');

    $viewCreate = request('POST', '/api/v1/views', [
        'entity_type' => 'task',
        'title' => 'Saved view ' . randomSuffix(),
        'filters' => [
            'status' => 'in_progress',
            'assignee' => 'me',
        ],
    ], $headers);
    assertTrue($viewCreate['status'] === 201, 'View create status must be 201');
    $viewPublicId = (string)($viewCreate['payload']['data']['view']['public_id'] ?? '');
    assertTrue($viewPublicId !== '', 'View public_id required');

    $viewList = request('GET', '/api/v1/views?entity_type=task', [], $headers);
    assertTrue($viewList['status'] === 200, 'View list status must be 200');
    assertTrue(($viewList['payload']['code'] ?? '') === 'VIEW_LIST', 'View list code mismatch');

    $viewUpdate = request('PATCH', '/api/v1/views/' . $viewPublicId, [
        'title' => 'Saved view updated ' . randomSuffix(),
        'filters' => [
            'status' => 'done',
        ],
    ], $headers);
    assertTrue($viewUpdate['status'] === 200, 'View update status must be 200');
    assertTrue(($viewUpdate['payload']['code'] ?? '') === 'VIEW_UPDATED', 'View update code mismatch');

    $viewDelete = request('DELETE', '/api/v1/views/' . $viewPublicId, [], $headers);
    assertTrue($viewDelete['status'] === 200, 'View delete status must be 200');

    $aliasFavoriteList = request('GET', '/api/v1/favorite/list', [], $headers);
    assertTrue($aliasFavoriteList['status'] === 200, 'Alias favorite list status must be 200');

    $aliasViewList = request('GET', '/api/v1/view/list', [], $headers);
    assertTrue($aliasViewList['status'] === 200, 'Alias view list status must be 200');

    $unauthorized = request('GET', '/api/v1/views');
    assertTrue($unauthorized['status'] === 401, 'Views unauthorized status must be 401');

    echo "[OK] Favorites + views smoke passed\n";
    exit(0);
} catch (Throwable $e) {
    fwrite(STDERR, '[FAIL] ' . $e->getMessage() . "\n");
    exit(1);
}
