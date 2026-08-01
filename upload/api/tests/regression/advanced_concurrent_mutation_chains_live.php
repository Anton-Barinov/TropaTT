<?php
declare(strict_types=1);

require_once __DIR__ . '/../_live_http.php';

try {
    $root = liveLoginRoot();
    $headers = ['Authorization' => 'Bearer ' . $root['token']];
    $suffix = strtolower(gmdate('YmdHis') . '_' . bin2hex(random_bytes(3)));

    // API key mutation chain: issue -> revoke -> revoke again -> rotate revoked.
    $clientCreate = liveRequest('POST', 'api/v1/api-clients', [
        'title' => 'Concurrent Chain Client ' . $suffix,
        'scopes' => ['tasks.read', 'tasks.write'],
        'is_active' => 1,
    ], $headers);
    liveAssert($clientCreate['status'] === 201, 'API client create must return 201');
    $clientPublicId = (string)($clientCreate['payload']['data']['api_client']['public_id'] ?? '');
    liveAssert($clientPublicId !== '', 'API client public_id is required');

    $issueKey = liveRequest('POST', 'api/v1/api-clients/' . $clientPublicId . '/keys', [
        'scopes' => ['tasks.read'],
    ], $headers);
    liveAssert($issueKey['status'] === 201, 'API key issue must return 201');
    $keyPublicId = (string)($issueKey['payload']['data']['api_key']['public_id'] ?? '');
    liveAssert($keyPublicId !== '', 'API key public_id is required');

    $revokeFirst = liveRequest('POST', 'api/v1/api-keys/' . $keyPublicId . '/revoke', [], $headers);
    liveAssert($revokeFirst['status'] === 200, 'First API key revoke must return 200');

    $revokeSecond = liveRequest('POST', 'api/v1/api-keys/' . $keyPublicId . '/revoke', [], $headers);
    liveAssert($revokeSecond['status'] === 409, 'Second API key revoke must return 409');
    liveAssert((string)($revokeSecond['payload']['code'] ?? '') === 'API_KEY_REVOKED', 'Second API key revoke code mismatch');

    $rotateRevoked = liveRequest('POST', 'api/v1/api-keys/' . $keyPublicId . '/rotate', [], $headers);
    liveAssert($rotateRevoked['status'] === 409, 'Rotate revoked API key must return 409');
    liveAssert((string)($rotateRevoked['payload']['code'] ?? '') === 'API_KEY_REVOKED', 'Rotate revoked API key code mismatch');

    // Impersonation mutation chain: start -> start again -> stop -> stop again.
    $targetLogin = 'imp_chain_' . $suffix;
    $targetToken = 'imp-chain-token-' . $suffix;
    $targetCreate = liveRequest('POST', 'api/v1/users', [
        'login' => $targetLogin,
        'password' => 'ImpChain123!',
        'token' => $targetToken,
        'email' => $targetLogin . '@crm.local',
    ], $headers);
    liveAssert($targetCreate['status'] === 201, 'Impersonation target user create must return 201');
    $targetPublicId = (string)($targetCreate['payload']['data']['user']['public_id'] ?? '');
    liveAssert($targetPublicId !== '', 'Impersonation target public_id is required');

    $impStart = liveRequest('POST', 'api/v1/security/impersonation/start', [
        'target_user_public_id' => $targetPublicId,
        'reason' => 'Concurrent chain test',
    ], $headers);
    liveAssert($impStart['status'] === 200, 'Impersonation start must return 200');
    $auditPublicId = (string)($impStart['payload']['data']['audit']['public_id'] ?? '');
    liveAssert($auditPublicId !== '', 'Impersonation audit public_id is required');

    $impStartAgain = liveRequest('POST', 'api/v1/security/impersonation/start', [
        'target_user_public_id' => $targetPublicId,
        'reason' => 'Concurrent chain test duplicate',
    ], $headers);
    liveAssert($impStartAgain['status'] === 409, 'Second impersonation start must return 409');
    liveAssert((string)($impStartAgain['payload']['code'] ?? '') === 'IMPERSONATION_ALREADY_ACTIVE', 'Second impersonation start code mismatch');

    $impStop = liveRequest('POST', 'api/v1/security/impersonation/stop', [
        'audit_public_id' => $auditPublicId,
    ], $headers);
    liveAssert($impStop['status'] === 200, 'Impersonation stop must return 200');

    $impStopAgain = liveRequest('POST', 'api/v1/security/impersonation/stop', [
        'audit_public_id' => $auditPublicId,
    ], $headers);
    liveAssert($impStopAgain['status'] === 409, 'Second impersonation stop must return 409');
    liveAssert((string)($impStopAgain['payload']['code'] ?? '') === 'IMPERSONATION_ALREADY_STOPPED', 'Second impersonation stop code mismatch');

    liveRequest('DELETE', 'api/v1/users/' . $targetPublicId, [], $headers);
    liveRequest('DELETE', 'api/v1/api-clients/' . $clientPublicId, [], $headers);

    echo "[OK] advanced_concurrent_mutation_chains_live\n";
} catch (Throwable $e) {
    fwrite(STDERR, '[FAIL] advanced_concurrent_mutation_chains_live: ' . $e->getMessage() . "\n");
    exit(1);
}

