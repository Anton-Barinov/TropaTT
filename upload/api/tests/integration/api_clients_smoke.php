<?php
declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';

try {
    $auth = loginRoot();
    $headers = authHeaders($auth['token']);

    $suffix = randomSuffix();
    $create = request('POST', '/api/v1/api-clients', [
        'title' => 'Integration Client ' . $suffix,
        'scopes' => ['tasks.read', 'projects.read'],
        'is_active' => 1,
    ], $headers);

    assertTrue($create['status'] === 201, 'API client create status must be 201');
    $clientPublicId = (string)($create['payload']['data']['api_client']['public_id'] ?? '');
    assertTrue($clientPublicId !== '', 'API client public_id is required');

    $list = request('GET', '/api/v1/api-clients', [], $headers);
    assertTrue($list['status'] === 200, 'API clients list status must be 200');

    $get = request('GET', '/api/v1/api-clients/' . $clientPublicId, [], $headers);
    assertTrue($get['status'] === 200, 'API client get status must be 200');

    $issue = request('POST', '/api/v1/api-clients/' . $clientPublicId . '/keys', [
        'scopes' => ['tasks.read'],
    ], $headers);
    assertTrue($issue['status'] === 201, 'API key issue status must be 201');
    $keyPublicId = (string)($issue['payload']['data']['api_key']['public_id'] ?? '');
    $plainKey = (string)($issue['payload']['data']['plain_key'] ?? '');
    assertTrue($keyPublicId !== '', 'API key public_id is required');
    assertTrue($plainKey !== '', 'API key plain_key is required');

    $keys = request('GET', '/api/v1/api-clients/' . $clientPublicId . '/keys', [], $headers);
    assertTrue($keys['status'] === 200, 'API keys list status must be 200');

    $usage = request('GET', '/api/v1/api-keys/' . $keyPublicId . '/usage', [], $headers);
    assertTrue($usage['status'] === 200, 'API key usage status must be 200');

    $rotate = request('POST', '/api/v1/api-keys/' . $keyPublicId . '/rotate', [], $headers);
    assertTrue($rotate['status'] === 200, 'API key rotate status must be 200');
    $rotatedPublicId = (string)($rotate['payload']['data']['api_key']['public_id'] ?? '');
    assertTrue($rotatedPublicId !== '' && $rotatedPublicId !== $keyPublicId, 'Rotated key public_id must differ');

    $revoke = request('POST', '/api/v1/api-keys/' . $rotatedPublicId . '/revoke', [], $headers);
    assertTrue($revoke['status'] === 200, 'API key revoke status must be 200');

    $delete = request('DELETE', '/api/v1/api-clients/' . $clientPublicId, [], $headers);
    assertTrue($delete['status'] === 200, 'API client delete status must be 200');

    $blockedSuffix = randomSuffix();
    $blockedClient = request('POST', '/api/v1/api-clients', [
        'title' => 'Blocked Client ' . $blockedSuffix,
        'scopes' => ['tasks.read'],
    ], $headers);
    assertTrue($blockedClient['status'] === 201, 'Blocked client create status must be 201');
    $blockedClientPublicId = (string)($blockedClient['payload']['data']['api_client']['public_id'] ?? '');
    assertTrue($blockedClientPublicId !== '', 'Blocked client public_id is required');

    $blockedKey = request('POST', '/api/v1/api-clients/' . $blockedClientPublicId . '/keys', [
        'scopes' => ['tasks.read'],
    ], $headers);
    assertTrue($blockedKey['status'] === 201, 'Blocked client issue key status must be 201');

    $deleteBlocked = request('DELETE', '/api/v1/api-clients/' . $blockedClientPublicId, [], $headers);
    assertTrue($deleteBlocked['status'] === 409, 'Delete with active key must return 409');

    $blockedKeyPublicId = (string)($blockedKey['payload']['data']['api_key']['public_id'] ?? '');
    request('POST', '/api/v1/api-keys/' . $blockedKeyPublicId . '/revoke', [], $headers);
    $deleteBlockedAfterRevoke = request('DELETE', '/api/v1/api-clients/' . $blockedClientPublicId, [], $headers);
    assertTrue($deleteBlockedAfterRevoke['status'] === 200, 'Delete after key revoke must return 200');

    $unauthorized = request('GET', '/api/v1/api-clients');
    assertTrue($unauthorized['status'] === 401, 'API clients unauthorized status must be 401');

    echo "[OK] API clients smoke passed\n";
    exit(0);
} catch (Throwable $e) {
    fwrite(STDERR, '[FAIL] ' . $e->getMessage() . "\n");
    exit(1);
}
