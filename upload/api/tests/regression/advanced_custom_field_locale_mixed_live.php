<?php
declare(strict_types=1);

require_once __DIR__ . '/../_live_http.php';

/** @param mixed $value */
function assertNoCyrillicCustomField(mixed $value, string $context): void
{
    if (is_string($value)) {
        liveAssert(!preg_match('/\p{Cyrillic}/u', $value), $context . ': value contains Cyrillic');
        return;
    }

    if (is_array($value)) {
        foreach ($value as $k => $v) {
            assertNoCyrillicCustomField($v, $context . '.' . (string)$k);
        }
    }
}

try {
    $root = liveLoginRoot();
    $rootHeaders = ['Authorization' => 'Bearer ' . $root['token']];
    $suffix = strtolower(gmdate('YmdHis') . '_' . bin2hex(random_bytes(3)));

    $roleCreate = liveRequest('POST', 'api/v1/roles', [
        'code' => 'cf_locale_' . $suffix,
        'title' => 'Custom Field Locale ' . $suffix,
    ], $rootHeaders);
    liveAssert($roleCreate['status'] === 201, 'Role create must return 201');
    $rolePublicId = (string)($roleCreate['payload']['data']['role']['public_id'] ?? '');
    liveAssert($rolePublicId !== '', 'Role public_id is required');

    $setPerms = liveRequest('PUT', 'api/v1/roles/' . $rolePublicId . '/permissions', [
        'permission_codes' => ['settings.manage'],
    ], $rootHeaders);
    liveAssert($setPerms['status'] === 200, 'Role permissions set must return 200');

    $login = 'cf_locale_' . $suffix;
    $token = 'cf-locale-token-' . $suffix;
    $userCreate = liveRequest('POST', 'api/v1/users', [
        'login' => $login,
        'password' => 'CfLocale123!',
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
        'password' => 'CfLocale123!',
        'token' => $token,
    ]);
    liveAssert($userLogin['status'] === 200, 'User login must return 200');
    $userToken = (string)($userLogin['payload']['data']['access_token'] ?? '');
    liveAssert($userToken !== '', 'User token is required');

    $headers = [
        'Authorization' => 'Bearer ' . $userToken,
        'X-Locale' => 'ru-ru',
    ];

    $list = liveRequest('GET', 'api/v1/custom-fields', [], $headers);
    liveAssert($list['status'] === 200, 'Custom fields list must return 200');
    liveAssert((string)($list['payload']['message'] ?? '') === 'Custom field list', 'Custom fields list message mismatch');

    $validation = liveRequest('POST', 'api/v1/custom-fields', [
        'scope' => 'task',
        'type' => 'text',
    ], $headers);
    liveAssert($validation['status'] === 422, 'Custom fields validation must return 422');
    liveAssert((string)($validation['payload']['message'] ?? '') === 'Validation error', 'Custom fields validation message mismatch');
    assertNoCyrillicCustomField($validation['payload']['errors'] ?? [], 'custom_field.validation.errors');

    $code = 'cf_' . substr($suffix, -6);
    $create = liveRequest('POST', 'api/v1/custom-fields', [
        'scope' => 'task',
        'code' => $code,
        'title' => 'CF ' . $suffix,
        'type' => 'text',
        'options' => [],
        'is_required' => 0,
    ], $headers);
    liveAssert($create['status'] === 201, 'Custom field create must return 201');
    liveAssert((string)($create['payload']['message'] ?? '') === 'Custom field created', 'Custom field create message mismatch');
    $fieldPublicId = (string)($create['payload']['data']['field']['public_id'] ?? '');
    liveAssert($fieldPublicId !== '', 'Custom field public_id is required');

    $get = liveRequest('GET', 'api/v1/custom-fields/' . $fieldPublicId, [], $headers);
    liveAssert($get['status'] === 200, 'Custom field get must return 200');
    liveAssert((string)($get['payload']['message'] ?? '') === 'Custom field details', 'Custom field detail message mismatch');

    $setValuesValidation = liveRequest('POST', 'api/v1/custom-fields/values', [
        'entity_type' => 'task',
        'entity_public_id' => 'tsk_cf_' . $suffix,
    ], $headers);
    liveAssert($setValuesValidation['status'] === 422, 'Custom field values validation must return 422');
    liveAssert((string)($setValuesValidation['payload']['message'] ?? '') === 'Validation error', 'Custom field values validation message mismatch');
    assertNoCyrillicCustomField($setValuesValidation['payload']['errors'] ?? [], 'custom_field.values.validation.errors');

    $setValues = liveRequest('POST', 'api/v1/custom-fields/values', [
        'entity_type' => 'task',
        'entity_public_id' => 'tsk_cf_' . $suffix,
        'values' => [$fieldPublicId => 'x'],
    ], $headers);
    liveAssert($setValues['status'] === 200, 'Custom field values save must return 200');
    liveAssert((string)($setValues['payload']['message'] ?? '') === 'Custom field values saved', 'Custom field values saved message mismatch');

    $notFound = liveRequest('GET', 'api/v1/custom-fields/cf_missing_' . $suffix, [], $headers);
    liveAssert($notFound['status'] === 404, 'Custom field not found must return 404');
    liveAssert((string)($notFound['payload']['message'] ?? '') === 'Custom field not found', 'Custom field not found message mismatch');

    liveRequest('DELETE', 'api/v1/custom-fields/' . $fieldPublicId, [], $headers);
    liveRequest('DELETE', 'api/v1/users/' . $userPublicId, [], $rootHeaders);
    liveRequest('DELETE', 'api/v1/roles/' . $rolePublicId, [], $rootHeaders);

    echo "[OK] advanced_custom_field_locale_mixed_live\n";
} catch (Throwable $e) {
    fwrite(STDERR, '[FAIL] advanced_custom_field_locale_mixed_live: ' . $e->getMessage() . "\n");
    exit(1);
}
