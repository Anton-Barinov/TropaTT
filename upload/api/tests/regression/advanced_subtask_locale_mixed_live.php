<?php
declare(strict_types=1);

require_once __DIR__ . '/../_live_http.php';

/** @param mixed $value */
function assertNoCyrillicSubtask(mixed $value, string $context): void
{
    if (is_string($value)) {
        liveAssert(!preg_match('/\p{Cyrillic}/u', $value), $context . ': value contains Cyrillic');
        return;
    }

    if (is_array($value)) {
        foreach ($value as $k => $v) {
            assertNoCyrillicSubtask($v, $context . '.' . (string)$k);
        }
    }
}

try {
    $root = liveLoginRoot();
    $rootHeaders = ['Authorization' => 'Bearer ' . $root['token']];
    $suffix = strtolower(gmdate('YmdHis') . '_' . bin2hex(random_bytes(3)));

    $roleCreate = liveRequest('POST', 'api/v1/roles', [
        'code' => 'subtask_locale_' . $suffix,
        'title' => 'Subtask Locale ' . $suffix,
    ], $rootHeaders);
    liveAssert($roleCreate['status'] === 201, 'Role create must return 201');
    $rolePublicId = (string)($roleCreate['payload']['data']['role']['public_id'] ?? '');
    liveAssert($rolePublicId !== '', 'Role public_id is required');

    $setPerms = liveRequest('PUT', 'api/v1/roles/' . $rolePublicId . '/permissions', [
        'permission_codes' => ['project.manage', 'task.manage'],
    ], $rootHeaders);
    liveAssert($setPerms['status'] === 200, 'Role permissions set must return 200');

    $login = 'subtask_locale_' . $suffix;
    $token = 'subtask-locale-token-' . $suffix;
    $userCreate = liveRequest('POST', 'api/v1/users', [
        'login' => $login,
        'password' => 'SubtaskLocale123!',
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
        'password' => 'SubtaskLocale123!',
        'token' => $token,
    ]);
    liveAssert($userLogin['status'] === 200, 'User login must return 200');
    $userToken = (string)($userLogin['payload']['data']['access_token'] ?? '');
    liveAssert($userToken !== '', 'User token is required');

    $headers = [
        'Authorization' => 'Bearer ' . $userToken,
        'X-Locale' => 'ru-ru',
    ];

    $projectCreate = liveRequest('POST', 'api/v1/projects', [
        'title' => 'Subtask Locale Project ' . $suffix,
    ], $headers);
    liveAssert($projectCreate['status'] === 201, 'Project create must return 201');
    $projectPublicId = (string)($projectCreate['payload']['data']['project']['public_id'] ?? '');
    liveAssert($projectPublicId !== '', 'Project public_id is required');

    $taskCreate = liveRequest('POST', 'api/v1/tasks', [
        'project_public_id' => $projectPublicId,
        'title' => 'Subtask Locale Task ' . $suffix,
    ], $headers);
    liveAssert($taskCreate['status'] === 201, 'Task create must return 201');
    $taskPublicId = (string)($taskCreate['payload']['data']['task']['public_id'] ?? '');
    liveAssert($taskPublicId !== '', 'Task public_id is required');

    $list = liveRequest('GET', 'api/v1/tasks/' . $taskPublicId . '/subtasks', [], $headers);
    liveAssert($list['status'] === 200, 'Subtask list must return 200');
    liveAssert((string)($list['payload']['message'] ?? '') === 'Subtask list', 'Subtask list message mismatch');

    $validation = liveRequest('POST', 'api/v1/tasks/' . $taskPublicId . '/subtasks', [
        'title' => 'X',
        'status' => 'wrong',
    ], $headers);
    liveAssert($validation['status'] === 422, 'Subtask validation must return 422');
    liveAssert((string)($validation['payload']['message'] ?? '') === 'Validation error', 'Subtask validation message mismatch');
    assertNoCyrillicSubtask($validation['payload']['errors'] ?? [], 'subtask.validation.errors');

    $create = liveRequest('POST', 'api/v1/tasks/' . $taskPublicId . '/subtasks', [
        'title' => 'Subtask ' . $suffix,
        'status' => 'new',
    ], $headers);
    liveAssert($create['status'] === 201, 'Subtask create must return 201');
    liveAssert((string)($create['payload']['message'] ?? '') === 'Subtask created', 'Subtask create message mismatch');
    $subtaskPublicId = (string)($create['payload']['data']['subtask']['public_id'] ?? '');
    liveAssert($subtaskPublicId !== '', 'Subtask public_id is required');

    $get = liveRequest('GET', 'api/v1/subtasks/' . $subtaskPublicId, [], $headers);
    liveAssert($get['status'] === 200, 'Subtask get must return 200');
    liveAssert((string)($get['payload']['message'] ?? '') === 'Subtask details', 'Subtask detail message mismatch');

    $update = liveRequest('PATCH', 'api/v1/subtasks/' . $subtaskPublicId, [
        'status' => 'done',
    ], $headers);
    liveAssert($update['status'] === 200, 'Subtask update must return 200');
    liveAssert((string)($update['payload']['message'] ?? '') === 'Subtask updated', 'Subtask update message mismatch');

    $notFound = liveRequest('GET', 'api/v1/subtasks/sub_missing_' . $suffix, [], $headers);
    liveAssert($notFound['status'] === 404, 'Subtask not found must return 404');
    liveAssert((string)($notFound['payload']['message'] ?? '') === 'Subtask not found', 'Subtask not found message mismatch');

    liveRequest('DELETE', 'api/v1/subtasks/' . $subtaskPublicId, [], $headers);
    liveRequest('DELETE', 'api/v1/tasks/' . $taskPublicId, [], $headers);
    liveRequest('DELETE', 'api/v1/projects/' . $projectPublicId, [], $headers);
    liveRequest('DELETE', 'api/v1/users/' . $userPublicId, [], $rootHeaders);
    liveRequest('DELETE', 'api/v1/roles/' . $rolePublicId, [], $rootHeaders);

    echo "[OK] advanced_subtask_locale_mixed_live\n";
} catch (Throwable $e) {
    fwrite(STDERR, '[FAIL] advanced_subtask_locale_mixed_live: ' . $e->getMessage() . "\n");
    exit(1);
}
