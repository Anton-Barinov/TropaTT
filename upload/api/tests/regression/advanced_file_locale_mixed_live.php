<?php
declare(strict_types=1);

require_once __DIR__ . '/../_live_http.php';

/** @param mixed $value */
function assertNoCyrillicFile(mixed $value, string $context): void
{
    if (is_string($value)) {
        liveAssert(!preg_match('/\p{Cyrillic}/u', $value), $context . ': value contains Cyrillic');
        return;
    }

    if (is_array($value)) {
        foreach ($value as $k => $v) {
            assertNoCyrillicFile($v, $context . '.' . (string)$k);
        }
    }
}

/**
 * @param array<string,string> $headers
 * @return array{status:int,headers:array<int,string>,body:string}
 */
function liveDownload(string $route, array $headers = []): array
{
    $url = LIVE_API_BASE . '?route=' . rawurlencode($route);

    $headerLines = ['Accept: */*'];
    foreach ($headers as $name => $value) {
        $headerLines[] = $name . ': ' . $value;
    }

    $context = stream_context_create([
        'http' => [
            'method' => 'GET',
            'header' => implode("\r\n", $headerLines),
            'ignore_errors' => true,
            'timeout' => 20,
        ],
        'ssl' => [
            'verify_peer' => false,
            'verify_peer_name' => false,
            'allow_self_signed' => true,
        ],
    ]);

    $body = @file_get_contents($url, false, $context);
    $responseHeaders = $http_response_header;
    if (!is_string($body)) {
        $body = '';
    }

    $status = 0;
    if (isset($responseHeaders[0]) && preg_match('/\s(\d{3})\s/', $responseHeaders[0], $m)) {
        $status = (int)$m[1];
    }

    return [
        'status' => $status,
        'headers' => $responseHeaders,
        'body' => $body,
    ];
}

try {
    $root = liveLoginRoot();
    $rootHeaders = ['Authorization' => 'Bearer ' . $root['token']];
    $suffix = strtolower(gmdate('YmdHis') . '_' . bin2hex(random_bytes(3)));

    $roleCreate = liveRequest('POST', 'api/v1/roles', [
        'code' => 'file_locale_' . $suffix,
        'title' => 'File Locale ' . $suffix,
    ], $rootHeaders);
    liveAssert($roleCreate['status'] === 201, 'Role create must return 201');
    $rolePublicId = (string)($roleCreate['payload']['data']['role']['public_id'] ?? '');
    liveAssert($rolePublicId !== '', 'Role public_id is required');

    $setPerms = liveRequest('PUT', 'api/v1/roles/' . $rolePublicId . '/permissions', [
        'permission_codes' => ['task.manage'],
    ], $rootHeaders);
    liveAssert($setPerms['status'] === 200, 'Role permissions set must return 200');

    $login = 'file_locale_' . $suffix;
    $token = 'file-locale-token-' . $suffix;
    $userCreate = liveRequest('POST', 'api/v1/users', [
        'login' => $login,
        'password' => 'FileLocale123!',
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
        'password' => 'FileLocale123!',
        'token' => $token,
    ]);
    liveAssert($userLogin['status'] === 200, 'User login must return 200');
    $userToken = (string)($userLogin['payload']['data']['access_token'] ?? '');
    liveAssert($userToken !== '', 'User token is required');

    $headers = [
        'Authorization' => 'Bearer ' . $userToken,
        'X-Locale' => 'ru-ru',
    ];

    $foreignTask = liveRequest('POST', 'api/v1/tasks', [
        'title' => 'Foreign file task ' . $suffix,
    ], $rootHeaders);
    liveAssert($foreignTask['status'] === 201, 'Foreign task create must return 201');
    $foreignTaskPublicId = (string)($foreignTask['payload']['data']['task']['public_id'] ?? '');
    liveAssert($foreignTaskPublicId !== '', 'Foreign task public_id is required');

    $taskCreate = liveRequest('POST', 'api/v1/tasks', [
        'title' => 'File locale task ' . $suffix,
    ], $headers);
    liveAssert($taskCreate['status'] === 201, 'Task create must return 201');
    $taskPublicId = (string)($taskCreate['payload']['data']['task']['public_id'] ?? '');
    liveAssert($taskPublicId !== '', 'Task public_id is required');

    $validation = liveRequest('POST', 'api/v1/files', [], $headers);
    liveAssert($validation['status'] === 422, 'File validation must return 422');
    liveAssert((string)($validation['payload']['message'] ?? '') === 'File upload error', 'File validation message mismatch');
    assertNoCyrillicFile($validation['payload']['errors'] ?? [], 'file.validation.errors');

    $forbidden = liveRequest('POST', 'api/v1/files', [
        'entity_type' => 'task',
        'entity_public_id' => $foreignTaskPublicId,
        'name' => 'forbidden.txt',
        'mime_type' => 'text/plain',
        'content_base64' => base64_encode('forbidden'),
    ], $headers);
    liveAssert($forbidden['status'] === 403, 'Linked entity forbidden must return 403');
    liveAssert((string)($forbidden['payload']['message'] ?? '') === 'Insufficient permissions for linked entity', 'Linked entity forbidden message mismatch');
    assertNoCyrillicFile($forbidden['payload']['errors'] ?? [], 'file.forbidden.errors');

    $content = 'file locale content ' . $suffix;
    $create = liveRequest('POST', 'api/v1/files', [
        'entity_type' => 'task',
        'entity_public_id' => $taskPublicId,
        'name' => 'locale-file-' . $suffix . '.txt',
        'mime_type' => 'text/plain',
        'content_base64' => base64_encode($content),
    ], $headers);
    liveAssert($create['status'] === 201, 'File create must return 201');
    liveAssert((string)($create['payload']['message'] ?? '') === 'File uploaded', 'File create message mismatch');
    $filePublicId = (string)($create['payload']['data']['file']['public_id'] ?? '');
    liveAssert($filePublicId !== '', 'File public_id is required');

    $get = liveRequest('GET', 'api/v1/files/' . $filePublicId, [], $headers);
    liveAssert($get['status'] === 200, 'File get must return 200');
    liveAssert((string)($get['payload']['message'] ?? '') === 'File details', 'File get message mismatch');

    $download = liveDownload('api/v1/files/' . $filePublicId . '/download', $headers);
    liveAssert($download['status'] === 200, 'File download must return 200');
    liveAssert($download['body'] === $content, 'File download content mismatch');

    $delete = liveRequest('DELETE', 'api/v1/files/' . $filePublicId, [], $headers);
    liveAssert($delete['status'] === 200, 'File delete must return 200');
    liveAssert((string)($delete['payload']['message'] ?? '') === 'File deleted', 'File delete message mismatch');

    $notFound = liveRequest('GET', 'api/v1/files/' . $filePublicId, [], $headers);
    liveAssert($notFound['status'] === 404, 'File not found must return 404');
    liveAssert((string)($notFound['payload']['message'] ?? '') === 'File not found', 'File not found message mismatch');
    assertNoCyrillicFile($notFound['payload']['errors'] ?? [], 'file.not_found.errors');

    liveRequest('DELETE', 'api/v1/tasks/' . $taskPublicId, [], $headers);
    liveRequest('DELETE', 'api/v1/tasks/' . $foreignTaskPublicId, [], $rootHeaders);
    liveRequest('DELETE', 'api/v1/users/' . $userPublicId, [], $rootHeaders);
    liveRequest('DELETE', 'api/v1/roles/' . $rolePublicId, [], $rootHeaders);

    echo "[OK] advanced_file_locale_mixed_live\n";
} catch (Throwable $e) {
    fwrite(STDERR, '[FAIL] advanced_file_locale_mixed_live: ' . $e->getMessage() . "\n");
    exit(1);
}
