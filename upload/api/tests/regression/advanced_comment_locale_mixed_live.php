<?php
declare(strict_types=1);

require_once __DIR__ . '/../_live_http.php';

/** @param mixed $value */
function assertNoCyrillicComment(mixed $value, string $context): void
{
    if (is_string($value)) {
        liveAssert(!preg_match('/\p{Cyrillic}/u', $value), $context . ': value contains Cyrillic');
        return;
    }

    if (is_array($value)) {
        foreach ($value as $k => $v) {
            assertNoCyrillicComment($v, $context . '.' . (string)$k);
        }
    }
}

try {
    $root = liveLoginRoot();
    $rootHeaders = ['Authorization' => 'Bearer ' . $root['token']];
    $suffix = strtolower(gmdate('YmdHis') . '_' . bin2hex(random_bytes(3)));

    $roleCreate = liveRequest('POST', 'api/v1/roles', [
        'code' => 'cmt_locale_' . $suffix,
        'title' => 'Comment Locale ' . $suffix,
    ], $rootHeaders);
    liveAssert($roleCreate['status'] === 201, 'Role create must return 201');
    $rolePublicId = (string)($roleCreate['payload']['data']['role']['public_id'] ?? '');
    liveAssert($rolePublicId !== '', 'Role public_id is required');

    $setPerms = liveRequest('PUT', 'api/v1/roles/' . $rolePublicId . '/permissions', [
        'permission_codes' => ['project.manage', 'task.manage'],
    ], $rootHeaders);
    liveAssert($setPerms['status'] === 200, 'Role permissions set must return 200');

    $login = 'cmt_locale_' . $suffix;
    $token = 'comment-locale-token-' . $suffix;
    $userCreate = liveRequest('POST', 'api/v1/users', [
        'login' => $login,
        'password' => 'CommentLocale123!',
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
        'password' => 'CommentLocale123!',
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
        'title' => 'Comment Locale Project ' . $suffix,
    ], $headers);
    liveAssert($projectCreate['status'] === 201, 'Project create must return 201');
    $projectPublicId = (string)($projectCreate['payload']['data']['project']['public_id'] ?? '');
    liveAssert($projectPublicId !== '', 'Project public_id is required');

    $taskCreate = liveRequest('POST', 'api/v1/tasks', [
        'project_public_id' => $projectPublicId,
        'title' => 'Comment Locale Task ' . $suffix,
    ], $headers);
    liveAssert($taskCreate['status'] === 201, 'Task create must return 201');
    $taskPublicId = (string)($taskCreate['payload']['data']['task']['public_id'] ?? '');
    liveAssert($taskPublicId !== '', 'Task public_id is required');

    $commentCreate = liveRequest('POST', 'api/v1/tasks/' . $taskPublicId . '/comments', [
        'body' => 'Comment locale ' . $suffix,
    ], $headers);
    liveAssert($commentCreate['status'] === 201, 'Comment create must return 201');

    $commentList = liveRequest('GET', 'api/v1/tasks/' . $taskPublicId . '/comments', [], $headers);
    liveAssert($commentList['status'] === 200, 'Comment list must return 200');
    $commentPublicId = (string)($commentList['payload']['data']['items'][0]['public_id'] ?? '');
    liveAssert($commentPublicId !== '', 'Comment public_id is required');

    $validation = liveRequest('PATCH', 'api/v1/comments/' . $commentPublicId, [
        'visibility' => 'invalid',
    ], $headers);
    liveAssert($validation['status'] === 422, 'Comment validation must return 422');
    liveAssert((string)($validation['payload']['message'] ?? '') === 'Validation error', 'Comment validation message mismatch');
    assertNoCyrillicComment($validation['payload']['errors'] ?? [], 'comment.validation.errors');

    $update = liveRequest('PATCH', 'api/v1/comments/' . $commentPublicId, [
        'body' => 'Updated comment ' . $suffix,
        'visibility' => 'internal',
    ], $headers);
    liveAssert($update['status'] === 200, 'Comment update must return 200');
    liveAssert((string)($update['payload']['message'] ?? '') === 'Comment updated', 'Comment updated message mismatch');

    $delete = liveRequest('DELETE', 'api/v1/comments/' . $commentPublicId, [], $headers);
    liveAssert($delete['status'] === 200, 'Comment delete must return 200');
    liveAssert((string)($delete['payload']['message'] ?? '') === 'Comment deleted', 'Comment deleted message mismatch');

    $notFound = liveRequest('DELETE', 'api/v1/comments/' . $commentPublicId, [], $headers);
    liveAssert($notFound['status'] === 404, 'Comment not found must return 404');
    liveAssert((string)($notFound['payload']['message'] ?? '') === 'Comment not found', 'Comment not found message mismatch');
    assertNoCyrillicComment($notFound['payload']['errors'] ?? [], 'comment.not_found.errors');

    liveRequest('DELETE', 'api/v1/tasks/' . $taskPublicId, [], $headers);
    liveRequest('DELETE', 'api/v1/projects/' . $projectPublicId, [], $headers);
    liveRequest('DELETE', 'api/v1/users/' . $userPublicId, [], $rootHeaders);
    liveRequest('DELETE', 'api/v1/roles/' . $rolePublicId, [], $rootHeaders);

    echo "[OK] advanced_comment_locale_mixed_live\n";
} catch (Throwable $e) {
    fwrite(STDERR, '[FAIL] advanced_comment_locale_mixed_live: ' . $e->getMessage() . "\n");
    exit(1);
}
