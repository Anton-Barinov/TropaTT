<?php
declare(strict_types=1);

require_once __DIR__ . '/../_live_http.php';

try {
    $root = liveLoginRoot();
    $headers = ['Authorization' => 'Bearer ' . $root['token']];

    $suffix = 'edge-' . date('YmdHis') . '-' . bin2hex(random_bytes(2));

    $clientCreate = liveRequest('POST', 'api/v1/api-clients', [
        'title' => 'Edge API Client ' . $suffix,
        'scopes' => ['task.read', 'task.write'],
    ], $headers);
    liveAssert($clientCreate['status'] === 201, 'API client create must return 201');

    $clientPublicId = (string)($clientCreate['payload']['data']['api_client']['public_id'] ?? '');
    liveAssert($clientPublicId !== '', 'api_client public_id is required');

    $issue = liveRequest('POST', 'api/v1/api-clients/' . $clientPublicId . '/keys', [
        'scopes' => ['task.read'],
    ], $headers);
    liveAssert($issue['status'] === 201, 'API key issue must return 201');

    $keyPublicId = (string)($issue['payload']['data']['api_key']['public_id'] ?? '');
    liveAssert($keyPublicId !== '', 'api_key public_id is required');

    $deleteWithActiveKey = liveRequest('DELETE', 'api/v1/api-clients/' . $clientPublicId, [], $headers);
    liveAssert($deleteWithActiveKey['status'] === 409, 'Delete API client with active key must return 409');
    liveAssert((string)($deleteWithActiveKey['payload']['code'] ?? '') === 'API_CLIENT_HAS_ACTIVE_KEYS', 'Expected API_CLIENT_HAS_ACTIVE_KEYS');

    $rotate = liveRequest('POST', 'api/v1/api-keys/' . $keyPublicId . '/rotate', [], $headers);
    liveAssert($rotate['status'] === 200, 'Rotate key must return 200');
    liveAssert((string)($rotate['payload']['code'] ?? '') === 'API_KEY_ROTATED', 'Expected API_KEY_ROTATED');

    $rotatedPublicId = (string)($rotate['payload']['data']['api_key']['public_id'] ?? '');
    liveAssert($rotatedPublicId !== '' && $rotatedPublicId !== $keyPublicId, 'Rotated key public_id must be new');

    $rotateRevokedOld = liveRequest('POST', 'api/v1/api-keys/' . $keyPublicId . '/rotate', [], $headers);
    liveAssert($rotateRevokedOld['status'] === 409, 'Rotate revoked key must return 409');
    liveAssert((string)($rotateRevokedOld['payload']['code'] ?? '') === 'API_KEY_REVOKED', 'Expected API_KEY_REVOKED on rotating old key');

    $revoke = liveRequest('POST', 'api/v1/api-keys/' . $rotatedPublicId . '/revoke', [], $headers);
    liveAssert($revoke['status'] === 200, 'Revoke rotated key must return 200');
    liveAssert((string)($revoke['payload']['code'] ?? '') === 'API_KEY_REVOKED', 'Expected API_KEY_REVOKED response code');

    $revokeAgain = liveRequest('POST', 'api/v1/api-keys/' . $rotatedPublicId . '/revoke', [], $headers);
    liveAssert($revokeAgain['status'] === 409, 'Revoke already revoked key must return 409');
    liveAssert((string)($revokeAgain['payload']['code'] ?? '') === 'API_KEY_REVOKED', 'Expected API_KEY_REVOKED on second revoke');

    $deleteClient = liveRequest('DELETE', 'api/v1/api-clients/' . $clientPublicId, [], $headers);
    liveAssert($deleteClient['status'] === 200, 'Delete API client after key revoke must return 200');

    echo "[OK] api_keys_rotate_revoke_permutations_live\n";
} catch (Throwable $e) {
    fwrite(STDERR, '[FAIL] api_keys_rotate_revoke_permutations_live: ' . $e->getMessage() . "\n");
    exit(1);
}
