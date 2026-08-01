<?php
declare(strict_types=1);

require_once __DIR__ . '/../_live_http.php';

try {
    $root = liveLoginRoot();
    $rootHeaders = ['Authorization' => 'Bearer ' . $root['token']];
    $suffix = strtolower(gmdate('YmdHis') . '_' . bin2hex(random_bytes(3)));

    $roleCreate = liveRequest('POST', 'api/v1/roles', [
        'code' => 'cross_auth_locale_' . $suffix,
        'title' => 'Cross Auth Locale ' . $suffix,
    ], $rootHeaders);
    liveAssert($roleCreate['status'] === 201, 'Role create must return 201');
    $rolePublicId = (string)($roleCreate['payload']['data']['role']['public_id'] ?? '');
    liveAssert($rolePublicId !== '', 'Role public_id is required');

    $setPerms = liveRequest('PUT', 'api/v1/roles/' . $rolePublicId . '/permissions', [
        'permission_codes' => [
            'import.manage',
            'export.manage',
            'recycle_bin.manage',
            'webhook.manage',
        ],
    ], $rootHeaders);
    liveAssert($setPerms['status'] === 200, 'Role permissions set must return 200');

    $login = 'cross_locale_' . $suffix;
    $token = 'cross-locale-token-' . $suffix;
    $userCreate = liveRequest('POST', 'api/v1/users', [
        'login' => $login,
        'password' => 'CrossLocale123!',
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
        'password' => 'CrossLocale123!',
        'token' => $token,
    ]);
    liveAssert($userLogin['status'] === 200, 'User login must return 200');
    $userToken = (string)($userLogin['payload']['data']['access_token'] ?? '');
    liveAssert($userToken !== '', 'User token is required');

    $userHeaders = ['Authorization' => 'Bearer ' . $userToken];
    $userHeadersRu = [
        'Authorization' => 'Bearer ' . $userToken,
        'X-Locale' => 'ru-ru',
    ];

    // Locale-bound security edge-case: authenticated locale must win over X-Locale override.
    $sessions = liveRequest('GET', 'api/v1/security/sessions', [], $userHeadersRu);
    liveAssert($sessions['status'] === 200, 'Sessions list must return 200');
    liveAssert((string)($sessions['payload']['message'] ?? '') === 'Session list', 'Authenticated locale binding must keep English security message');

    $me = liveRequest('GET', 'api/v1/auth/me', [], $userHeadersRu);
    liveAssert($me['status'] === 200, 'auth/me must return 200');
    liveAssert((string)($me['payload']['message'] ?? '') === 'Current user profile', 'Authenticated locale binding must keep English auth message');

    // Root-owned jobs: must stay invisible for non-owner user on both canonical and alias status endpoints.
    $rootExport = liveRequest('POST', 'api/v1/export/jobs', [
        'type' => 'tasks',
        'filters' => ['search' => $suffix],
    ], $rootHeaders);
    liveAssert($rootExport['status'] === 201, 'Root export create must return 201');
    $rootExportPublicId = (string)($rootExport['payload']['data']['job']['public_id'] ?? '');
    liveAssert($rootExportPublicId !== '', 'Root export public_id is required');

    $rootImport = liveRequest('POST', 'api/v1/import/jobs', [
        'type' => 'tasks',
        'rows' => [
            ['title' => 'Root import task ' . $suffix, 'status' => 'new', 'priority' => 'normal'],
        ],
    ], $rootHeaders);
    liveAssert($rootImport['status'] === 201, 'Root import create must return 201');
    $rootImportPublicId = (string)($rootImport['payload']['data']['job']['public_id'] ?? '');
    liveAssert($rootImportPublicId !== '', 'Root import public_id is required');

    $userGetRootExportCanonical = liveRequest('GET', 'api/v1/export/jobs/' . $rootExportPublicId, [], $userHeaders);
    $userGetRootExportAlias = liveRequest('GET', 'api/v1/export/status/' . $rootExportPublicId, [], $userHeaders);
    liveAssert($userGetRootExportCanonical['status'] === 404, 'Non-owner must not access root export via canonical endpoint');
    liveAssert($userGetRootExportAlias['status'] === 404, 'Non-owner must not access root export via alias endpoint');
    liveAssert((string)($userGetRootExportCanonical['payload']['code'] ?? '') === 'EXPORT_JOB_NOT_FOUND', 'Canonical root export deny code mismatch');
    liveAssert((string)($userGetRootExportAlias['payload']['code'] ?? '') === 'EXPORT_JOB_NOT_FOUND', 'Alias root export deny code mismatch');

    $userGetRootImportCanonical = liveRequest('GET', 'api/v1/import/jobs/' . $rootImportPublicId, [], $userHeaders);
    $userGetRootImportAlias = liveRequest('GET', 'api/v1/import/status/' . $rootImportPublicId, [], $userHeaders);
    liveAssert($userGetRootImportCanonical['status'] === 404, 'Non-owner must not access root import via canonical endpoint');
    liveAssert($userGetRootImportAlias['status'] === 404, 'Non-owner must not access root import via alias endpoint');
    liveAssert((string)($userGetRootImportCanonical['payload']['code'] ?? '') === 'IMPORT_JOB_NOT_FOUND', 'Canonical root import deny code mismatch');
    liveAssert((string)($userGetRootImportAlias['payload']['code'] ?? '') === 'IMPORT_JOB_NOT_FOUND', 'Alias root import deny code mismatch');

    // User-owned jobs: must be readable through both canonical and alias endpoints.
    $userExportAliasCreate = liveRequest('POST', 'api/v1/export/create', [
        'type' => 'tasks',
        'filters' => ['search' => $suffix],
    ], $userHeaders);
    liveAssert($userExportAliasCreate['status'] === 201, 'User export alias create must return 201');
    $userExportPublicId = (string)($userExportAliasCreate['payload']['data']['job']['public_id'] ?? '');
    liveAssert($userExportPublicId !== '', 'User export public_id is required');

    $userExportCanonicalGet = liveRequest('GET', 'api/v1/export/jobs/' . $userExportPublicId, [], $userHeaders);
    $userExportAliasGet = liveRequest('GET', 'api/v1/export/status/' . $userExportPublicId, [], $userHeaders);
    liveAssert($userExportCanonicalGet['status'] === 200, 'User export canonical get must return 200');
    liveAssert($userExportAliasGet['status'] === 200, 'User export alias get must return 200');

    $userExportCanonicalDownload = liveRequest('GET', 'api/v1/export/jobs/' . $userExportPublicId . '/download', [], $userHeaders);
    $userExportAliasDownload = liveRequest('GET', 'api/v1/export/download/' . $userExportPublicId, [], $userHeaders);
    liveAssert($userExportCanonicalDownload['status'] === 200, 'User export canonical download must return 200');
    liveAssert($userExportAliasDownload['status'] === 200, 'User export alias download must return 200');

    $userImportAliasCreate = liveRequest('POST', 'api/v1/import/create', [
        'type' => 'tasks',
        'rows' => [
            ['title' => 'User import task ' . $suffix, 'status' => 'new', 'priority' => 'normal'],
        ],
    ], $userHeaders);
    liveAssert($userImportAliasCreate['status'] === 201, 'User import alias create must return 201');
    $userImportPublicId = (string)($userImportAliasCreate['payload']['data']['job']['public_id'] ?? '');
    liveAssert($userImportPublicId !== '', 'User import public_id is required');

    $userImportCanonicalGet = liveRequest('GET', 'api/v1/import/jobs/' . $userImportPublicId, [], $userHeaders);
    $userImportAliasGet = liveRequest('GET', 'api/v1/import/status/' . $userImportPublicId, [], $userHeaders);
    liveAssert($userImportCanonicalGet['status'] === 200, 'User import canonical get must return 200');
    liveAssert($userImportAliasGet['status'] === 200, 'User import alias get must return 200');

    // Webhook manage permission is not enough for write without root (service-level guard): canonical+alias must match.
    $userWebhookCanonicalCreate = liveRequest('POST', 'api/v1/webhooks', [
        'title' => 'Webhook canonical deny ' . $suffix,
        'endpoint' => 'https://localhost/canonical-deny-' . $suffix,
        'events' => ['task.created'],
    ], $userHeaders);
    $userWebhookAliasCreate = liveRequest('POST', 'api/v1/webhook/create', [
        'title' => 'Webhook alias deny ' . $suffix,
        'endpoint' => 'https://localhost/alias-deny-' . $suffix,
        'events' => ['task.created'],
    ], $userHeaders);
    liveAssert($userWebhookCanonicalCreate['status'] === 403, 'User webhook canonical create must be forbidden');
    liveAssert($userWebhookAliasCreate['status'] === 403, 'User webhook alias create must be forbidden');
    liveAssert((string)($userWebhookCanonicalCreate['payload']['code'] ?? '') === 'FORBIDDEN', 'User webhook canonical code mismatch');
    liveAssert((string)($userWebhookAliasCreate['payload']['code'] ?? '') === 'FORBIDDEN', 'User webhook alias code mismatch');

    // Recycle restore/purge chain via canonical+alias should keep consistent state transitions.
    $fileCreate = liveRequest('POST', 'api/v1/files', [
        'name' => 'cross_auth_' . $suffix . '.txt',
        'mime_type' => 'text/plain',
        'content_base64' => base64_encode('cross payload ' . $suffix),
    ], $rootHeaders);
    liveAssert($fileCreate['status'] === 201, 'File create for recycle test must return 201');
    $filePublicId = (string)($fileCreate['payload']['data']['file']['public_id'] ?? '');
    liveAssert($filePublicId !== '', 'File public_id for recycle test is required');

    $fileDelete = liveRequest('DELETE', 'api/v1/files/' . $filePublicId, [], $rootHeaders);
    liveAssert($fileDelete['status'] === 200, 'File delete for recycle test must return 200');

    $binList = liveRequest('GET', 'api/v1/recycle-bin', [], $userHeaders);
    liveAssert($binList['status'] === 200, 'Recycle list must return 200');
    $binItems = (array)($binList['payload']['data']['items'] ?? []);
    $binPublicId = '';
    foreach ($binItems as $item) {
        if ((string)($item['entity_public_id'] ?? '') === $filePublicId) {
            $binPublicId = (string)($item['public_id'] ?? '');
            break;
        }
    }
    liveAssert($binPublicId !== '', 'Recycle bin public_id for file is required');

    $restoreCanonical = liveRequest('POST', 'api/v1/recycle-bin/' . $binPublicId . '/restore', [], $userHeaders);
    liveAssert($restoreCanonical['status'] === 200, 'Recycle restore canonical must return 200');

    $restoreAliasAgain = liveRequest('POST', 'api/v1/recycle-bin/restore/' . $binPublicId, [], $userHeaders);
    $purgeCanonicalAfterRestore = liveRequest('DELETE', 'api/v1/recycle-bin/' . $binPublicId . '/purge', [], $userHeaders);
    $purgeAliasAfterRestore = liveRequest('DELETE', 'api/v1/recycle-bin/purge/' . $binPublicId, [], $userHeaders);
    liveAssert($restoreAliasAgain['status'] === 409, 'Recycle restore alias after restore must return 409');
    liveAssert($purgeCanonicalAfterRestore['status'] === 409, 'Recycle purge canonical after restore must return 409');
    liveAssert($purgeAliasAfterRestore['status'] === 409, 'Recycle purge alias after restore must return 409');
    liveAssert((string)($restoreAliasAgain['payload']['code'] ?? '') === 'RECYCLE_BIN_ALREADY_RESTORED', 'Recycle alias restore-after-restore code mismatch');
    liveAssert((string)($purgeCanonicalAfterRestore['payload']['code'] ?? '') === 'RECYCLE_BIN_ALREADY_RESTORED', 'Recycle canonical purge-after-restore code mismatch');
    liveAssert((string)($purgeAliasAfterRestore['payload']['code'] ?? '') === 'RECYCLE_BIN_ALREADY_RESTORED', 'Recycle alias purge-after-restore code mismatch');

    liveRequest('DELETE', 'api/v1/users/' . $userPublicId, [], $rootHeaders);
    liveRequest('DELETE', 'api/v1/roles/' . $rolePublicId, [], $rootHeaders);

    echo "[OK] advanced_cross_endpoint_auth_locale_live\n";
} catch (Throwable $e) {
    fwrite(STDERR, '[FAIL] advanced_cross_endpoint_auth_locale_live: ' . $e->getMessage() . "\n");
    exit(1);
}
