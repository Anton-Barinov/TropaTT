<?php
declare(strict_types=1);

require_once __DIR__ . '/../_live_http.php';

/** @param mixed $value */
function assertNoCyrillicTask(mixed $value, string $context): void
{
    if (is_string($value)) {
        liveAssert(!preg_match('/\p{Cyrillic}/u', $value), $context . ': value contains Cyrillic');
        return;
    }

    if (is_array($value)) {
        foreach ($value as $k => $v) {
            assertNoCyrillicTask($v, $context . '.' . (string)$k);
        }
    }
}

try {
    $root = liveLoginRoot();
    $rootHeaders = ['Authorization' => 'Bearer ' . $root['token']];
    $suffix = strtolower(gmdate('YmdHis') . '_' . bin2hex(random_bytes(3)));

    $roleCreate = liveRequest('POST', 'api/v1/roles', [
        'code' => 'task_locale_' . $suffix,
        'title' => 'Task Locale ' . $suffix,
    ], $rootHeaders);
    liveAssert($roleCreate['status'] === 201, 'Role create must return 201');
    $rolePublicId = (string)($roleCreate['payload']['data']['role']['public_id'] ?? '');
    liveAssert($rolePublicId !== '', 'Role public_id is required');

    $setPerms = liveRequest('PUT', 'api/v1/roles/' . $rolePublicId . '/permissions', [
        'permission_codes' => ['task.manage'],
    ], $rootHeaders);
    liveAssert($setPerms['status'] === 200, 'Role permissions set must return 200');

    $login = 'task_locale_' . $suffix;
    $token = 'task-locale-token-' . $suffix;
    $userCreate = liveRequest('POST', 'api/v1/users', [
        'login' => $login,
        'password' => 'TaskLocale123!',
        'token' => $token,
        'email' => $login . '@crm.local',
        'locale' => 'en-gb',
        'role_public_ids' => [$rolePublicId],
    ], $rootHeaders);
    liveAssert($userCreate['status'] === 201, 'User create must return 201');
    $userPublicId = (string)($userCreate['payload']['data']['user']['public_id'] ?? '');
    liveAssert($userPublicId !== '', 'User public_id is required');

    $userLogin = liveRequest('POST', 'api/v1/auth/login', [
        'login' => $login,
        'password' => 'TaskLocale123!',
        'token' => $token,
    ]);
    liveAssert($userLogin['status'] === 200, 'User login must return 200');
    $userToken = (string)($userLogin['payload']['data']['access_token'] ?? '');
    liveAssert($userToken !== '', 'User token is required');

    $headers = [
        'Authorization' => 'Bearer ' . $userToken,
        'X-Locale' => 'ru-ru',
    ];

    $board = liveRequest('GET', 'api/v1/tasks/board', [], $headers);
    liveAssert($board['status'] === 200, 'Task board must return 200');
    liveAssert((string)($board['payload']['message'] ?? '') === 'Task board', 'Task board message mismatch');

    $create = liveRequest('POST', 'api/v1/tasks', [
        'title' => 'Task locale ' . $suffix,
        'status' => 'new',
        'priority' => 'normal',
    ], $headers);
    liveAssert($create['status'] === 201, 'Task create must return 201');
    liveAssert((string)($create['payload']['message'] ?? '') === 'Task created successfully', 'Task create message mismatch');
    $taskPublicId = (string)($create['payload']['data']['task']['public_id'] ?? '');
    liveAssert($taskPublicId !== '', 'Task public_id is required');

    $list = liveRequest('GET', 'api/v1/tasks', ['limit' => 5], $headers);
    liveAssert($list['status'] === 200, 'Task list must return 200');
    liveAssert((string)($list['payload']['message'] ?? '') === 'Tasks list', 'Task list message mismatch');

    $get = liveRequest('GET', 'api/v1/tasks/' . $taskPublicId, [], $headers);
    liveAssert($get['status'] === 200, 'Task get must return 200');
    liveAssert((string)($get['payload']['message'] ?? '') === 'Task details', 'Task get message mismatch');

    $update = liveRequest('PATCH', 'api/v1/tasks/' . $taskPublicId, [
        'priority' => 'high',
    ], $headers);
    liveAssert($update['status'] === 200, 'Task update must return 200');
    liveAssert((string)($update['payload']['message'] ?? '') === 'Task updated', 'Task update message mismatch');

    $moveValidation = liveRequest('POST', 'api/v1/tasks/' . $taskPublicId . '/move', [], $headers);
    liveAssert($moveValidation['status'] === 422, 'Task move validation must return 422');
    liveAssert((string)($moveValidation['payload']['message'] ?? '') === 'Validation error', 'Task move validation message mismatch');

    $move = liveRequest('POST', 'api/v1/tasks/' . $taskPublicId . '/move', [
        'to_status' => 'in_progress',
    ], $headers);
    liveAssert($move['status'] === 200, 'Task move must return 200');
    liveAssert((string)($move['payload']['message'] ?? '') === 'Task card moved', 'Task move message mismatch');

    $bulkValidation = liveRequest('POST', 'api/v1/tasks/bulk', [
        'task_public_ids' => [$taskPublicId],
        'changes' => [],
    ], $headers);
    liveAssert($bulkValidation['status'] === 422, 'Task bulk validation must return 422');
    liveAssert((string)($bulkValidation['payload']['message'] ?? '') === 'Validation error', 'Task bulk validation message mismatch');

    $bulk = liveRequest('POST', 'api/v1/tasks/bulk', [
        'task_public_ids' => [$taskPublicId],
        'changes' => ['priority' => 'urgent'],
    ], $headers);
    liveAssert($bulk['status'] === 200, 'Task bulk update must return 200');
    liveAssert((string)($bulk['payload']['message'] ?? '') === 'Task bulk update completed', 'Task bulk message mismatch');

    $comments = liveRequest('GET', 'api/v1/tasks/' . $taskPublicId . '/comments', ['limit' => 5], $headers);
    liveAssert($comments['status'] === 200, 'Task comments list must return 200');
    liveAssert((string)($comments['payload']['message'] ?? '') === 'Task comments', 'Task comments list message mismatch');

    $commentCreate = liveRequest('POST', 'api/v1/tasks/' . $taskPublicId . '/comments', [
        'body' => 'Task comment ' . $suffix,
    ], $headers);
    liveAssert($commentCreate['status'] === 201, 'Task comment create must return 201');
    liveAssert((string)($commentCreate['payload']['message'] ?? '') === 'Comment added', 'Task comment create message mismatch');

    $delete = liveRequest('DELETE', 'api/v1/tasks/' . $taskPublicId, [], $headers);
    liveAssert($delete['status'] === 200, 'Task delete must return 200');
    liveAssert((string)($delete['payload']['message'] ?? '') === 'Task deleted', 'Task delete message mismatch');

    $notFound = liveRequest('GET', 'api/v1/tasks/' . $taskPublicId, [], $headers);
    liveAssert($notFound['status'] === 404, 'Task not found must return 404');
    liveAssert((string)($notFound['payload']['message'] ?? '') === 'Task not found', 'Task not found message mismatch');

    assertNoCyrillicTask($moveValidation['payload']['errors'] ?? [], 'task.move.validation.errors');
    assertNoCyrillicTask($bulkValidation['payload']['errors'] ?? [], 'task.bulk.validation.errors');
    assertNoCyrillicTask($notFound['payload']['errors'] ?? [], 'task.not_found.errors');

    liveRequest('DELETE', 'api/v1/users/' . $userPublicId, [], $rootHeaders);
    liveRequest('DELETE', 'api/v1/roles/' . $rolePublicId, [], $rootHeaders);

    echo "[OK] advanced_task_locale_mixed_live\n";
} catch (Throwable $e) {
    fwrite(STDERR, '[FAIL] advanced_task_locale_mixed_live: ' . $e->getMessage() . "\n");
    exit(1);
}
