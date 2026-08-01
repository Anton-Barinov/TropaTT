<?php
declare(strict_types=1);

require_once __DIR__ . '/../_live_http.php';

/** @param mixed $value */
function assertNoCyrillicRecycle(mixed $value, string $context): void
{
    if (is_string($value)) {
        liveAssert(!preg_match('/\p{Cyrillic}/u', $value), $context . ': value contains Cyrillic');
        return;
    }

    if (is_array($value)) {
        foreach ($value as $k => $v) {
            assertNoCyrillicRecycle($v, $context . '.' . (string)$k);
        }
    }
}

try {
    $root = liveLoginRoot();
    $rootHeaders = ['Authorization' => 'Bearer ' . $root['token']];
    $suffix = strtolower(gmdate('YmdHis') . '_' . bin2hex(random_bytes(3)));

    $roleCreate = liveRequest('POST', 'api/v1/roles', [
        'code' => 'rcb_locale_' . $suffix,
        'title' => 'Recycle Locale ' . $suffix,
    ], $rootHeaders);
    liveAssert($roleCreate['status'] === 201, 'Role create must return 201');
    $rolePublicId = (string)($roleCreate['payload']['data']['role']['public_id'] ?? '');
    liveAssert($rolePublicId !== '', 'Role public_id is required');

    $setPerms = liveRequest('PUT', 'api/v1/roles/' . $rolePublicId . '/permissions', [
        'permission_codes' => ['project.manage', 'task.manage', 'file.manage', 'recycle_bin.manage'],
    ], $rootHeaders);
    liveAssert($setPerms['status'] === 200, 'Role permissions set must return 200');

    $login = 'rcb_locale_' . $suffix;
    $token = 'rcb-locale-token-' . $suffix;
    $userCreate = liveRequest('POST', 'api/v1/users', [
        'login' => $login,
        'password' => 'RecycleLocale123!',
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
        'password' => 'RecycleLocale123!',
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
        'title' => 'Recycle Locale Project ' . $suffix,
    ], $headers);
    liveAssert($projectCreate['status'] === 201, 'Project create must return 201');
    $projectPublicId = (string)($projectCreate['payload']['data']['project']['public_id'] ?? '');
    liveAssert($projectPublicId !== '', 'Project public_id is required');

    $taskCreate = liveRequest('POST', 'api/v1/tasks', [
        'project_public_id' => $projectPublicId,
        'title' => 'Recycle Locale Task ' . $suffix,
    ], $headers);
    liveAssert($taskCreate['status'] === 201, 'Task create must return 201');
    $taskPublicId = (string)($taskCreate['payload']['data']['task']['public_id'] ?? '');
    liveAssert($taskPublicId !== '', 'Task public_id is required');

    $fileCreate = liveRequest('POST', 'api/v1/files', [
        'entity_type' => 'task',
        'entity_public_id' => $taskPublicId,
        'name' => 'rcb_' . $suffix . '.txt',
        'mime_type' => 'text/plain',
        'content_base64' => base64_encode('recycle locale payload ' . $suffix),
    ], $headers);
    liveAssert($fileCreate['status'] === 201, 'File create must return 201');
    $filePublicId = (string)($fileCreate['payload']['data']['file']['public_id'] ?? '');
    liveAssert($filePublicId !== '', 'File public_id is required');

    $fileDelete = liveRequest('DELETE', 'api/v1/files/' . $filePublicId, [], $headers);
    liveAssert($fileDelete['status'] === 200, 'File delete must return 200');

    $list = liveRequest('GET', 'api/v1/recycle-bin', [], $headers);
    liveAssert($list['status'] === 200, 'Recycle bin list must return 200');
    liveAssert((string)($list['payload']['message'] ?? '') === 'Recycle bin contents', 'Recycle bin list message mismatch');

    $binPublicId = '';
    foreach ((array)($list['payload']['data']['items'] ?? []) as $row) {
        if (!is_array($row)) {
            continue;
        }
        if ((string)($row['entity_public_id'] ?? '') === $filePublicId) {
            $binPublicId = (string)($row['public_id'] ?? '');
            break;
        }
    }
    liveAssert($binPublicId !== '', 'Recycle bin public_id is required');

    $restore = liveRequest('POST', 'api/v1/recycle-bin/' . $binPublicId . '/restore', [], $headers);
    liveAssert($restore['status'] === 200, 'Recycle bin restore must return 200');
    liveAssert((string)($restore['payload']['message'] ?? '') === 'Recycle bin item restored', 'Recycle bin restore message mismatch');

    $fileDeleteAgain = liveRequest('DELETE', 'api/v1/files/' . $filePublicId, [], $headers);
    liveAssert($fileDeleteAgain['status'] === 200, 'File second delete must return 200');

    $listAgain = liveRequest('GET', 'api/v1/recycle-bin', [], $headers);
    liveAssert($listAgain['status'] === 200, 'Recycle bin list again must return 200');
    $binPublicId2 = '';
    foreach ((array)($listAgain['payload']['data']['items'] ?? []) as $row) {
        if (!is_array($row)) {
            continue;
        }
        if ((string)($row['entity_public_id'] ?? '') === $filePublicId && (int)($row['is_restored'] ?? 0) === 0) {
            $binPublicId2 = (string)($row['public_id'] ?? '');
            break;
        }
    }
    liveAssert($binPublicId2 !== '', 'Recycle bin second public_id is required');

    $purge = liveRequest('DELETE', 'api/v1/recycle-bin/' . $binPublicId2 . '/purge', [], $headers);
    liveAssert($purge['status'] === 200, 'Recycle bin purge must return 200');
    liveAssert((string)($purge['payload']['message'] ?? '') === 'Recycle bin item purged permanently', 'Recycle bin purge message mismatch');

    $notFound = liveRequest('POST', 'api/v1/recycle-bin/rcb_missing_' . $suffix . '/restore', [], $headers);
    liveAssert($notFound['status'] === 404, 'Recycle bin restore not found must return 404');
    liveAssert((string)($notFound['payload']['message'] ?? '') === 'Failed to restore recycle bin item', 'Recycle bin restore not found message mismatch');
    assertNoCyrillicRecycle($notFound['payload']['errors'] ?? [], 'recycle_bin.not_found.errors');

    liveRequest('DELETE', 'api/v1/tasks/' . $taskPublicId, [], $headers);
    liveRequest('DELETE', 'api/v1/projects/' . $projectPublicId, [], $headers);
    liveRequest('DELETE', 'api/v1/users/' . $userPublicId, [], $rootHeaders);
    liveRequest('DELETE', 'api/v1/roles/' . $rolePublicId, [], $rootHeaders);

    echo "[OK] advanced_recycle_bin_locale_mixed_live\n";
} catch (Throwable $e) {
    fwrite(STDERR, '[FAIL] advanced_recycle_bin_locale_mixed_live: ' . $e->getMessage() . "\n");
    exit(1);
}
