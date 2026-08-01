<?php
declare(strict_types=1);

require_once __DIR__ . '/../_live_http.php';

try {
    $root = liveLoginRoot();
    $rootHeaders = ['Authorization' => 'Bearer ' . $root['token']];
    $suffix = strtolower(gmdate('YmdHis') . '_' . bin2hex(random_bytes(3)));

    $login = 'device_model_' . $suffix;
    $tokenFactor = 'device-token-' . $suffix;
    $createdUserId = '';

    $createUser = liveRequest('POST', 'api/v1/users', [
        'login' => $login,
        'password' => 'DeviceUser123!',
        'token' => $tokenFactor,
        'email' => $login . '@crm.local',
        'locale' => 'en-gb',
    ], $rootHeaders);
    liveAssert($createUser['status'] === 201, 'Device model user create must return 201');
    $createdUserId = (string)($createUser['payload']['data']['user']['public_id'] ?? '');
    liveAssert($createdUserId !== '', 'Device model user public_id is required');

    $loginA = liveRequest('POST', 'api/v1/auth/login', [
        'login' => $login,
        'password' => 'DeviceUser123!',
        'token' => $tokenFactor,
    ], [
        'User-Agent' => 'CodexDeviceTest/Chrome-A',
    ]);
    liveAssert($loginA['status'] === 200, 'Device model login A must return 200');
    $accessA = (string)($loginA['payload']['data']['access_token'] ?? '');
    liveAssert($accessA !== '', 'Access token A is required');
    $headersA = ['Authorization' => 'Bearer ' . $accessA];

    $loginB = liveRequest('POST', 'api/v1/auth/login', [
        'login' => $login,
        'password' => 'DeviceUser123!',
        'token' => $tokenFactor,
    ], [
        'User-Agent' => 'CodexDeviceTest/Firefox-B',
    ]);
    liveAssert($loginB['status'] === 200, 'Device model login B must return 200');
    $accessB = (string)($loginB['payload']['data']['access_token'] ?? '');
    liveAssert($accessB !== '', 'Access token B is required');
    $headersB = ['Authorization' => 'Bearer ' . $accessB];

    $sessionsBefore = liveRequest('GET', 'api/v1/security/sessions', [], $headersA);
    liveAssert($sessionsBefore['status'] === 200, 'Session list before device revoke must return 200');
    $items = (array)($sessionsBefore['payload']['data']['items'] ?? []);
    liveAssert(count($items) >= 2, 'Session list must contain at least two sessions');

    $fingerprints = [];
    foreach ($items as $item) {
        $fp = (string)($item['device_fingerprint'] ?? '');
        $name = (string)($item['device_name'] ?? '');
        liveAssert($fp !== '', 'Session item device_fingerprint is required');
        liveAssert($name !== '', 'Session item device_name is required');
        $fingerprints[] = $fp;
    }

    $fingerprints = array_values(array_unique($fingerprints));
    liveAssert(count($fingerprints) >= 2, 'Two different device fingerprints are expected for different User-Agent logins');

    $mySessionBefore = liveRequest('GET', 'api/v1/auth/me', [], $headersA);
    liveAssert($mySessionBefore['status'] === 200, 'Auth me for session A must return 200');
    $mySessionId = (string)($mySessionBefore['payload']['data']['session_public_id'] ?? '');
    liveAssert($mySessionId !== '', 'Session public_id for A is required');

    $targetFingerprint = '';
    foreach ($items as $item) {
        if ((string)($item['public_id'] ?? '') === $mySessionId) {
            continue;
        }
        $targetFingerprint = (string)($item['device_fingerprint'] ?? '');
        if ($targetFingerprint !== '') {
            break;
        }
    }
    liveAssert($targetFingerprint !== '', 'Target fingerprint for revoke-device is required');

    $revokeDevice = liveRequest('POST', 'api/v1/security/sessions/revoke-device', [
        'device_fingerprint' => $targetFingerprint,
    ], $headersA);
    liveAssert($revokeDevice['status'] === 200, 'Revoke by device must return 200');
    liveAssert((string)($revokeDevice['payload']['code'] ?? '') === 'SESSION_REVOKE_DEVICE', 'Revoke by device code mismatch');
    liveAssert((int)($revokeDevice['payload']['data']['revoked_count'] ?? 0) >= 1, 'Revoke by device must revoke at least one session');

    $meB = liveRequest('GET', 'api/v1/auth/me', [], $headersB);
    liveAssert($meB['status'] === 401, 'Session B must be unauthorized after device revoke');

    $meAAfter = liveRequest('GET', 'api/v1/auth/me', [], $headersA);
    liveAssert($meAAfter['status'] === 200, 'Session A must stay authorized after device revoke');

    liveRequest('DELETE', 'api/v1/users/' . $createdUserId, [], $rootHeaders);

    echo "[OK] advanced_device_session_model_live\n";
} catch (Throwable $e) {
    fwrite(STDERR, '[FAIL] advanced_device_session_model_live: ' . $e->getMessage() . "\n");
    exit(1);
}

