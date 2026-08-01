<?php
declare(strict_types=1);

require_once __DIR__ . '/../_live_http.php';

try {
    $root = liveLoginRoot();
    $rootHeaders = ['Authorization' => 'Bearer ' . $root['token']];
    $suffix = strtolower(gmdate('YmdHis') . '_' . bin2hex(random_bytes(3)));

    // Recycle bin restore/purge boundaries.
    $fileCreate = liveRequest('POST', 'api/v1/files', [
        'name' => 'boundary_' . $suffix . '.txt',
        'mime_type' => 'text/plain',
        'content_base64' => base64_encode('boundary payload ' . $suffix),
    ], $rootHeaders);
    liveAssert($fileCreate['status'] === 201, 'File create must return 201');
    $filePublicId = (string)($fileCreate['payload']['data']['file']['public_id'] ?? '');
    liveAssert($filePublicId !== '', 'File public_id is required');

    $fileDelete = liveRequest('DELETE', 'api/v1/files/' . $filePublicId, [], $rootHeaders);
    liveAssert($fileDelete['status'] === 200, 'File delete must return 200');

    $binList = liveRequest('GET', 'api/v1/recycle-bin', [], $rootHeaders);
    liveAssert($binList['status'] === 200, 'Recycle bin list must return 200');
    $binItems = (array)($binList['payload']['data']['items'] ?? []);
    $binItem = null;
    foreach ($binItems as $item) {
        if ((string)($item['entity_public_id'] ?? '') === $filePublicId) {
            $binItem = $item;
            break;
        }
    }
    liveAssert(is_array($binItem), 'Recycle bin item for file must exist');
    $binPublicId = (string)($binItem['public_id'] ?? '');
    liveAssert($binPublicId !== '', 'Recycle bin public_id is required');

    $restoreOk = liveRequest('POST', 'api/v1/recycle-bin/' . $binPublicId . '/restore', [], $rootHeaders);
    liveAssert($restoreOk['status'] === 200, 'First recycle restore must return 200');

    $restoreAgain = liveRequest('POST', 'api/v1/recycle-bin/' . $binPublicId . '/restore', [], $rootHeaders);
    liveAssert($restoreAgain['status'] === 409, 'Second recycle restore must return 409');
    liveAssert((string)($restoreAgain['payload']['code'] ?? '') === 'RECYCLE_BIN_ALREADY_RESTORED', 'Second restore code mismatch');

    $purgeRestored = liveRequest('DELETE', 'api/v1/recycle-bin/' . $binPublicId . '/purge', [], $rootHeaders);
    liveAssert($purgeRestored['status'] === 409, 'Purge restored recycle item must return 409');
    liveAssert((string)($purgeRestored['payload']['code'] ?? '') === 'RECYCLE_BIN_ALREADY_RESTORED', 'Purge restored code mismatch');

    // Webhook delivery chain boundary: after delete -> test must be not found.
    $webhookCreate = liveRequest('POST', 'api/v1/webhooks', [
        'title' => 'Boundary webhook ' . $suffix,
        'endpoint' => 'https://example.com/unreachable',
        'events' => ['task.updated'],
        'secret' => 'boundary-secret-' . $suffix,
        'is_active' => 1,
    ], $rootHeaders);
    liveAssert($webhookCreate['status'] === 201, 'Webhook create must return 201');
    $webhookPublicId = (string)($webhookCreate['payload']['data']['webhook']['public_id'] ?? '');
    liveAssert($webhookPublicId !== '', 'Webhook public_id is required');

    $webhookDelete = liveRequest('DELETE', 'api/v1/webhooks/' . $webhookPublicId, [], $rootHeaders);
    liveAssert($webhookDelete['status'] === 200, 'Webhook delete must return 200');

    $webhookTestAfterDelete = liveRequest('POST', 'api/v1/webhooks/' . $webhookPublicId . '/test', [], $rootHeaders);
    liveAssert($webhookTestAfterDelete['status'] === 404, 'Webhook test after delete must return 404');
    liveAssert((string)($webhookTestAfterDelete['payload']['code'] ?? '') === 'WEBHOOK_NOT_FOUND', 'Webhook test after delete code mismatch');

    // Import/export ownership boundaries + mixed module mismatch.
    $exportRoot = liveRequest('POST', 'api/v1/export/jobs', [
        'type' => 'tasks',
        'filters' => ['search' => $suffix],
    ], $rootHeaders);
    liveAssert($exportRoot['status'] === 201, 'Root export create must return 201');
    $exportRootPublicId = (string)($exportRoot['payload']['data']['job']['public_id'] ?? '');
    liveAssert($exportRootPublicId !== '', 'Root export public_id is required');

    $importRoot = liveRequest('POST', 'api/v1/import/jobs', [
        'type' => 'tasks',
        'rows' => [
            ['title' => 'Boundary import task ' . $suffix, 'status' => 'new', 'priority' => 'normal'],
        ],
    ], $rootHeaders);
    liveAssert($importRoot['status'] === 201, 'Root import create must return 201');
    $importRootPublicId = (string)($importRoot['payload']['data']['job']['public_id'] ?? '');
    liveAssert($importRootPublicId !== '', 'Root import public_id is required');

    $roleCreate = liveRequest('POST', 'api/v1/roles', [
        'code' => 'boundary_chain_' . $suffix,
        'title' => 'Boundary Chain ' . $suffix,
    ], $rootHeaders);
    liveAssert($roleCreate['status'] === 201, 'Role create must return 201');
    $rolePublicId = (string)($roleCreate['payload']['data']['role']['public_id'] ?? '');
    liveAssert($rolePublicId !== '', 'Role public_id is required');

    $setPerms = liveRequest('PUT', 'api/v1/roles/' . $rolePublicId . '/permissions', [
        'permission_codes' => ['import.manage', 'export.manage', 'webhook.manage', 'recycle_bin.manage'],
    ], $rootHeaders);
    liveAssert($setPerms['status'] === 200, 'Role permissions set must return 200');

    $login = 'boundary_chain_' . $suffix;
    $token = 'boundary-chain-token-' . $suffix;
    $userCreate = liveRequest('POST', 'api/v1/users', [
        'login' => $login,
        'password' => 'Boundary123!',
        'token' => $token,
        'email' => $login . '@crm.local',
        'role_public_ids' => [$rolePublicId],
    ], $rootHeaders);
    liveAssert($userCreate['status'] === 201, 'Boundary user create must return 201');
    $userPublicId = (string)($userCreate['payload']['data']['user']['public_id'] ?? '');
    liveAssert($userPublicId !== '', 'Boundary user public_id is required');

    $userLogin = liveRequest('POST', 'api/v1/auth/login', [
        'login' => $login,
        'password' => 'Boundary123!',
        'token' => $token,
    ]);
    liveAssert($userLogin['status'] === 200, 'Boundary user login must return 200');
    $userToken = (string)($userLogin['payload']['data']['access_token'] ?? '');
    liveAssert($userToken !== '', 'Boundary user token is required');
    $userHeaders = ['Authorization' => 'Bearer ' . $userToken];

    $userGetRootExport = liveRequest('GET', 'api/v1/export/jobs/' . $exportRootPublicId, [], $userHeaders);
    liveAssert($userGetRootExport['status'] === 404, 'User must not access root export job');
    liveAssert((string)($userGetRootExport['payload']['code'] ?? '') === 'EXPORT_JOB_NOT_FOUND', 'Root export visibility code mismatch');

    $userGetRootImport = liveRequest('GET', 'api/v1/import/jobs/' . $importRootPublicId, [], $userHeaders);
    liveAssert($userGetRootImport['status'] === 404, 'User must not access root import job');
    liveAssert((string)($userGetRootImport['payload']['code'] ?? '') === 'IMPORT_JOB_NOT_FOUND', 'Root import visibility code mismatch');

    $userWebhookCreate = liveRequest('POST', 'api/v1/webhooks', [
        'title' => 'Non-root webhook ' . $suffix,
        'endpoint' => 'https://localhost/non-root',
        'events' => ['task.created'],
    ], $userHeaders);
    liveAssert($userWebhookCreate['status'] === 403, 'Non-root webhook create must be forbidden by service boundary');
    liveAssert((string)($userWebhookCreate['payload']['code'] ?? '') === 'FORBIDDEN', 'Non-root webhook create code mismatch');

    liveRequest('DELETE', 'api/v1/users/' . $userPublicId, [], $rootHeaders);
    liveRequest('DELETE', 'api/v1/roles/' . $rolePublicId, [], $rootHeaders);

    echo "[OK] advanced_boundary_chains_live\n";
} catch (Throwable $e) {
    fwrite(STDERR, '[FAIL] advanced_boundary_chains_live: ' . $e->getMessage() . "\n");
    exit(1);
}
