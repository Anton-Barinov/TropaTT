<?php
declare(strict_types=1);

require_once __DIR__ . '/../_live_http.php';

/** @param mixed $value */
function assertNoCyrillicFeatureFlag(mixed $value, string $context): void
{
    if (is_string($value)) {
        liveAssert(!preg_match('/\p{Cyrillic}/u', $value), $context . ': value contains Cyrillic');
        return;
    }

    if (is_array($value)) {
        foreach ($value as $k => $v) {
            assertNoCyrillicFeatureFlag($v, $context . '.' . (string)$k);
        }
    }
}

try {
    $root = liveLoginRoot();
    $rootHeaders = ['Authorization' => 'Bearer ' . $root['token']];
    $suffix = strtolower(gmdate('YmdHis') . '_' . bin2hex(random_bytes(3)));

    $roleCreate = liveRequest('POST', 'api/v1/roles', [
        'code' => 'ff_locale_' . $suffix,
        'title' => 'FeatureFlag Locale ' . $suffix,
    ], $rootHeaders);
    liveAssert($roleCreate['status'] === 201, 'Role create must return 201');
    $rolePublicId = (string)($roleCreate['payload']['data']['role']['public_id'] ?? '');
    liveAssert($rolePublicId !== '', 'Role public_id is required');

    $setPerms = liveRequest('PUT', 'api/v1/roles/' . $rolePublicId . '/permissions', [
        'permission_codes' => ['feature_flag.manage'],
    ], $rootHeaders);
    liveAssert($setPerms['status'] === 200, 'Role permissions set must return 200');

    $login = 'ff_locale_' . $suffix;
    $token = 'ff-locale-token-' . $suffix;
    $userCreate = liveRequest('POST', 'api/v1/users', [
        'login' => $login,
        'password' => 'FeatureFlagLocale123!',
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
        'password' => 'FeatureFlagLocale123!',
        'token' => $token,
    ]);
    liveAssert($userLogin['status'] === 200, 'User login must return 200');
    $userToken = (string)($userLogin['payload']['data']['access_token'] ?? '');
    liveAssert($userToken !== '', 'User token is required');

    $headers = [
        'Authorization' => 'Bearer ' . $userToken,
        'X-Locale' => 'ru-ru',
    ];

    $list = liveRequest('GET', 'api/v1/feature-flags', [], $headers);
    liveAssert($list['status'] === 200, 'Feature flag list must return 200');
    liveAssert((string)($list['payload']['message'] ?? '') === 'Feature flags list', 'Feature flag list message mismatch');
    $items = (array)($list['payload']['data']['items'] ?? []);
    liveAssert(count($items) >= 1, 'Feature flag list must return items');
    $flag = (array)$items[0];
    $flagPublicId = (string)($flag['public_id'] ?? '');
    liveAssert($flagPublicId !== '', 'Feature flag public_id is required');
    $currentEnabled = (bool)($flag['is_enabled'] ?? false);

    $update = liveRequest('PATCH', 'api/v1/feature-flags/' . $flagPublicId, [
        'is_enabled' => $currentEnabled ? 0 : 1,
        'payload' => ['locale' => true, 'suffix' => $suffix],
    ], $headers);
    liveAssert($update['status'] === 200, 'Feature flag update must return 200');
    liveAssert((string)($update['payload']['message'] ?? '') === 'Feature flag updated', 'Feature flag update message mismatch');

    $notFound = liveRequest('PATCH', 'api/v1/feature-flags/ff_missing_' . $suffix, [
        'is_enabled' => 1,
    ], $headers);
    liveAssert($notFound['status'] === 404, 'Feature flag not found must return 404');
    liveAssert((string)($notFound['payload']['message'] ?? '') === 'Failed to update feature flag', 'Feature flag update failed message mismatch');
    assertNoCyrillicFeatureFlag($notFound['payload']['errors'] ?? [], 'feature_flag.not_found.errors');

    liveRequest('DELETE', 'api/v1/users/' . $userPublicId, [], $rootHeaders);
    liveRequest('DELETE', 'api/v1/roles/' . $rolePublicId, [], $rootHeaders);

    echo "[OK] advanced_feature_flag_locale_mixed_live\n";
} catch (Throwable $e) {
    fwrite(STDERR, '[FAIL] advanced_feature_flag_locale_mixed_live: ' . $e->getMessage() . "\n");
    exit(1);
}
