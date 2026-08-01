<?php
declare(strict_types=1);

require_once __DIR__ . '/../_live_http.php';

/** @param mixed $value */
function assertNoCyrillicApiClient(mixed $value, string $context): void
{
    if (is_string($value)) {
        liveAssert(!preg_match('/\p{Cyrillic}/u', $value), $context . ': value contains Cyrillic');
        return;
    }

    if (is_array($value)) {
        foreach ($value as $k => $v) {
            assertNoCyrillicApiClient($v, $context . '.' . (string)$k);
        }
    }
}

try {
    $root = liveLoginRoot();
    $rootHeaders = ['Authorization' => 'Bearer ' . $root['token']];
    $suffix = strtolower(gmdate('YmdHis') . '_' . bin2hex(random_bytes(3)));

    $roleCreate = liveRequest('POST', 'api/v1/roles', [
        'code' => 'api_client_locale_' . $suffix,
        'title' => 'API Client Locale ' . $suffix,
    ], $rootHeaders);
    liveAssert($roleCreate['status'] === 201, 'Role create must return 201');
    $rolePublicId = (string)($roleCreate['payload']['data']['role']['public_id'] ?? '');
    liveAssert($rolePublicId !== '', 'Role public_id is required');

    $setPerms = liveRequest('PUT', 'api/v1/roles/' . $rolePublicId . '/permissions', [
        'permission_codes' => ['api_client.view', 'api_client.manage'],
    ], $rootHeaders);
    liveAssert($setPerms['status'] === 200, 'Role permissions set must return 200');

    $login = 'api_client_locale_' . $suffix;
    $token = 'api-client-locale-token-' . $suffix;
    $userCreate = liveRequest('POST', 'api/v1/users', [
        'login' => $login,
        'password' => 'ApiClientLocale123!',
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
        'password' => 'ApiClientLocale123!',
        'token' => $token,
    ]);
    liveAssert($userLogin['status'] === 200, 'User login must return 200');
    $userToken = (string)($userLogin['payload']['data']['access_token'] ?? '');
    liveAssert($userToken !== '', 'User token is required');

    $headers = [
        'Authorization' => 'Bearer ' . $userToken,
        'X-Locale' => 'ru-ru',
    ];

    $rootClientCreate = liveRequest('POST', 'api/v1/api-clients', [
        'title' => 'Locale API client ' . $suffix,
        'scopes' => ['tasks.read', 'projects.read'],
        'is_active' => 1,
    ], $rootHeaders);
    liveAssert($rootClientCreate['status'] === 201, 'Root API client create must return 201');
    $clientPublicId = (string)($rootClientCreate['payload']['data']['api_client']['public_id'] ?? '');
    liveAssert($clientPublicId !== '', 'API client public_id is required');

    $rootKeyIssue = liveRequest('POST', 'api/v1/api-clients/' . $clientPublicId . '/keys', [
        'scopes' => ['tasks.read'],
    ], $rootHeaders);
    liveAssert($rootKeyIssue['status'] === 201, 'Root API key issue must return 201');
    $keyPublicId = (string)($rootKeyIssue['payload']['data']['api_key']['public_id'] ?? '');
    liveAssert($keyPublicId !== '', 'API key public_id is required');

    $list = liveRequest('GET', 'api/v1/api-clients', [], $headers);
    liveAssert($list['status'] === 200, 'API clients list must return 200');
    liveAssert((string)($list['payload']['message'] ?? '') === 'API clients list', 'API clients list message mismatch');
    assertNoCyrillicApiClient($list['payload'], 'api_client.list.payload');

    $get = liveRequest('GET', 'api/v1/api-clients/' . $clientPublicId, [], $headers);
    liveAssert($get['status'] === 200, 'API client get must return 200');
    liveAssert((string)($get['payload']['message'] ?? '') === 'API client', 'API client get message mismatch');
    assertNoCyrillicApiClient($get['payload'], 'api_client.get.payload');

    $keys = liveRequest('GET', 'api/v1/api-clients/' . $clientPublicId . '/keys', [], $headers);
    liveAssert($keys['status'] === 200, 'API keys list must return 200');
    liveAssert((string)($keys['payload']['message'] ?? '') === 'API keys list', 'API keys list message mismatch');
    assertNoCyrillicApiClient($keys['payload'], 'api_client.keys.payload');

    $usage = liveRequest('GET', 'api/v1/api-keys/' . $keyPublicId . '/usage', [], $headers);
    liveAssert($usage['status'] === 200, 'API key usage must return 200');
    liveAssert((string)($usage['payload']['message'] ?? '') === 'API key usage log', 'API key usage message mismatch');
    assertNoCyrillicApiClient($usage['payload'], 'api_client.usage.payload');

    $validation = liveRequest('POST', 'api/v1/api-clients', [
        'title' => str_repeat('A', 260),
    ], $headers);
    liveAssert($validation['status'] === 422, 'API client validation must return 422');
    liveAssert((string)($validation['payload']['message'] ?? '') === 'Validation error', 'API client validation message mismatch');
    assertNoCyrillicApiClient($validation['payload']['errors'] ?? [], 'api_client.validation.errors');

    $createForbidden = liveRequest('POST', 'api/v1/api-clients', [
        'title' => 'Forbidden create ' . $suffix,
        'scopes' => ['tasks.read'],
    ], $headers);
    liveAssert($createForbidden['status'] === 403, 'API client create by non-root must return 403');
    liveAssert((string)($createForbidden['payload']['message'] ?? '') === 'Failed to create API client', 'API client create failed message mismatch');

    $issueForbidden = liveRequest('POST', 'api/v1/api-clients/' . $clientPublicId . '/keys', [
        'scopes' => ['tasks.read'],
    ], $headers);
    liveAssert($issueForbidden['status'] === 403, 'API key issue by non-root must return 403');
    liveAssert((string)($issueForbidden['payload']['message'] ?? '') === 'Failed to issue API key', 'API key issue failed message mismatch');

    $rotateForbidden = liveRequest('POST', 'api/v1/api-keys/' . $keyPublicId . '/rotate', [], $headers);
    liveAssert($rotateForbidden['status'] === 403, 'API key rotate by non-root must return 403');
    liveAssert((string)($rotateForbidden['payload']['message'] ?? '') === 'Failed to rotate API key', 'API key rotate failed message mismatch');

    $revokeForbidden = liveRequest('POST', 'api/v1/api-keys/' . $keyPublicId . '/revoke', [], $headers);
    liveAssert($revokeForbidden['status'] === 403, 'API key revoke by non-root must return 403');
    liveAssert((string)($revokeForbidden['payload']['message'] ?? '') === 'Failed to revoke API key', 'API key revoke failed message mismatch');

    $notFoundClient = liveRequest('GET', 'api/v1/api-clients/apc_missing_' . $suffix, [], $headers);
    liveAssert($notFoundClient['status'] === 404, 'API client not found must return 404');
    liveAssert((string)($notFoundClient['payload']['message'] ?? '') === 'API client not found', 'API client not found message mismatch');

    $notFoundKey = liveRequest('GET', 'api/v1/api-keys/apk_missing_' . $suffix . '/usage', [], $headers);
    liveAssert($notFoundKey['status'] === 404, 'API key not found must return 404');
    liveAssert((string)($notFoundKey['payload']['message'] ?? '') === 'API key not found', 'API key not found message mismatch');

    liveRequest('POST', 'api/v1/api-keys/' . $keyPublicId . '/revoke', [], $rootHeaders);
    liveRequest('DELETE', 'api/v1/api-clients/' . $clientPublicId, [], $rootHeaders);
    liveRequest('DELETE', 'api/v1/users/' . $userPublicId, [], $rootHeaders);
    liveRequest('DELETE', 'api/v1/roles/' . $rolePublicId, [], $rootHeaders);

    echo "[OK] advanced_api_client_locale_mixed_live\n";
} catch (Throwable $e) {
    fwrite(STDERR, '[FAIL] advanced_api_client_locale_mixed_live: ' . $e->getMessage() . "\n");
    exit(1);
}
