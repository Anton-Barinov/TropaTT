<?php
declare(strict_types=1);

require_once __DIR__ . '/../_live_http.php';

/** @param mixed $value */
function assertNoCyrillicInValue(mixed $value, string $context): void
{
    if (is_string($value)) {
        liveAssert(!preg_match('/\p{Cyrillic}/u', $value), $context . ': value contains Cyrillic');
        return;
    }

    if (is_array($value)) {
        foreach ($value as $k => $v) {
            assertNoCyrillicInValue($v, $context . '.' . (string)$k);
        }
    }
}

try {
    $root = liveLoginRoot();
    $rootHeaders = ['Authorization' => 'Bearer ' . $root['token']];
    $suffix = strtolower(gmdate('YmdHis') . '_' . bin2hex(random_bytes(3)));

    $roleCreate = liveRequest('POST', 'api/v1/roles', [
        'code' => 'dict_locale_' . $suffix,
        'title' => 'Dict Locale ' . $suffix,
    ], $rootHeaders);
    liveAssert($roleCreate['status'] === 201, 'Role create must return 201');
    $rolePublicId = (string)($roleCreate['payload']['data']['role']['public_id'] ?? '');
    liveAssert($rolePublicId !== '', 'Role public_id is required');

    $setPerms = liveRequest('PUT', 'api/v1/roles/' . $rolePublicId . '/permissions', [
        'permission_codes' => ['task.manage'],
    ], $rootHeaders);
    liveAssert($setPerms['status'] === 200, 'Role permissions set must return 200');

    $login = 'dict_locale_' . $suffix;
    $token = 'dict-locale-token-' . $suffix;
    $userCreate = liveRequest('POST', 'api/v1/users', [
        'login' => $login,
        'password' => 'DictLocale123!',
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
        'password' => 'DictLocale123!',
        'token' => $token,
    ]);
    liveAssert($userLogin['status'] === 200, 'User login must return 200');
    $userToken = (string)($userLogin['payload']['data']['access_token'] ?? '');
    liveAssert($userToken !== '', 'User token is required');

    $headers = [
        'Authorization' => 'Bearer ' . $userToken,
        'X-Locale' => 'ru-ru',
    ];

    $statusList = liveRequest('GET', 'api/v1/statuses', [], $headers);
    liveAssert($statusList['status'] === 200, 'Statuses list must return 200');
    liveAssert((string)($statusList['payload']['message'] ?? '') === 'Status list', 'Statuses list message mismatch');
    assertNoCyrillicInValue((string)($statusList['payload']['message'] ?? ''), 'statuses.list.message');

    $priorityList = liveRequest('GET', 'api/v1/priorities', [], $headers);
    liveAssert($priorityList['status'] === 200, 'Priorities list must return 200');
    liveAssert((string)($priorityList['payload']['message'] ?? '') === 'Priority list', 'Priorities list message mismatch');
    assertNoCyrillicInValue((string)($priorityList['payload']['message'] ?? ''), 'priorities.list.message');

    $tagList = liveRequest('GET', 'api/v1/tags', [], $headers);
    liveAssert($tagList['status'] === 200, 'Tags list must return 200');
    liveAssert((string)($tagList['payload']['message'] ?? '') === 'Tag list', 'Tags list message mismatch');
    assertNoCyrillicInValue((string)($tagList['payload']['message'] ?? ''), 'tags.list.message');

    $statusValidation = liveRequest('POST', 'api/v1/statuses', ['scope' => 'task'], $headers);
    liveAssert($statusValidation['status'] === 422, 'Statuses validation must return 422');
    liveAssert((string)($statusValidation['payload']['message'] ?? '') === 'Validation error', 'Statuses validation message mismatch');
    assertNoCyrillicInValue((string)($statusValidation['payload']['message'] ?? ''), 'statuses.validation.message');
    assertNoCyrillicInValue($statusValidation['payload']['errors'] ?? [], 'statuses.validation.errors');

    $priorityValidation = liveRequest('POST', 'api/v1/priorities', ['code' => 'x'], $headers);
    liveAssert($priorityValidation['status'] === 422, 'Priorities validation must return 422');
    liveAssert((string)($priorityValidation['payload']['message'] ?? '') === 'Validation error', 'Priorities validation message mismatch');
    assertNoCyrillicInValue((string)($priorityValidation['payload']['message'] ?? ''), 'priorities.validation.message');
    assertNoCyrillicInValue($priorityValidation['payload']['errors'] ?? [], 'priorities.validation.errors');

    $tagValidation = liveRequest('POST', 'api/v1/tags', ['code' => 'x'], $headers);
    liveAssert($tagValidation['status'] === 422, 'Tags validation must return 422');
    liveAssert((string)($tagValidation['payload']['message'] ?? '') === 'Validation error', 'Tags validation message mismatch');
    assertNoCyrillicInValue((string)($tagValidation['payload']['message'] ?? ''), 'tags.validation.message');
    assertNoCyrillicInValue($tagValidation['payload']['errors'] ?? [], 'tags.validation.errors');

    $statusNotFound = liveRequest('GET', 'api/v1/statuses/sts_missing_' . $suffix, [], $headers);
    liveAssert($statusNotFound['status'] === 404, 'Status not found must return 404');
    liveAssert((string)($statusNotFound['payload']['message'] ?? '') === 'Status not found', 'Status not found message mismatch');
    assertNoCyrillicInValue((string)($statusNotFound['payload']['message'] ?? ''), 'statuses.not_found.message');
    assertNoCyrillicInValue($statusNotFound['payload']['errors'] ?? [], 'statuses.not_found.errors');

    $priorityNotFound = liveRequest('GET', 'api/v1/priorities/pri_missing_' . $suffix, [], $headers);
    liveAssert($priorityNotFound['status'] === 404, 'Priority not found must return 404');
    liveAssert((string)($priorityNotFound['payload']['message'] ?? '') === 'Priority not found', 'Priority not found message mismatch');
    assertNoCyrillicInValue((string)($priorityNotFound['payload']['message'] ?? ''), 'priorities.not_found.message');
    assertNoCyrillicInValue($priorityNotFound['payload']['errors'] ?? [], 'priorities.not_found.errors');

    $tagNotFound = liveRequest('GET', 'api/v1/tags/tag_missing_' . $suffix, [], $headers);
    liveAssert($tagNotFound['status'] === 404, 'Tag not found must return 404');
    liveAssert((string)($tagNotFound['payload']['message'] ?? '') === 'Tag not found', 'Tag not found message mismatch');
    assertNoCyrillicInValue((string)($tagNotFound['payload']['message'] ?? ''), 'tags.not_found.message');
    assertNoCyrillicInValue($tagNotFound['payload']['errors'] ?? [], 'tags.not_found.errors');

    liveRequest('DELETE', 'api/v1/users/' . $userPublicId, [], $rootHeaders);
    liveRequest('DELETE', 'api/v1/roles/' . $rolePublicId, [], $rootHeaders);

    echo "[OK] advanced_dictionaries_locale_mixed_live\n";
} catch (Throwable $e) {
    fwrite(STDERR, '[FAIL] advanced_dictionaries_locale_mixed_live: ' . $e->getMessage() . "\n");
    exit(1);
}
