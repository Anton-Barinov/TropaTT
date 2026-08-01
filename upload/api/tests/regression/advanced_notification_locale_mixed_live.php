<?php
declare(strict_types=1);

require_once __DIR__ . '/../_live_http.php';

/** @param mixed $value */
function assertNoCyrillicNotification(mixed $value, string $context): void
{
    if (is_string($value)) {
        liveAssert(!preg_match('/\p{Cyrillic}/u', $value), $context . ': value contains Cyrillic');
        return;
    }

    if (is_array($value)) {
        foreach ($value as $k => $v) {
            assertNoCyrillicNotification($v, $context . '.' . (string)$k);
        }
    }
}

try {
    $root = liveLoginRoot();
    $rootHeaders = ['Authorization' => 'Bearer ' . $root['token']];
    $suffix = strtolower(gmdate('YmdHis') . '_' . bin2hex(random_bytes(3)));

    $login = 'ntf_locale_' . $suffix;
    $token = 'ntf-locale-token-' . $suffix;
    $userCreate = liveRequest('POST', 'api/v1/users', [
        'login' => $login,
        'password' => 'NtfLocale123!',
        'token' => $token,
        'email' => $login . '@crm.local',
        'locale' => 'en-gb',
    ], $rootHeaders);
    liveAssert($userCreate['status'] === 201, 'User create must return 201');
    $userPublicId = (string)($userCreate['payload']['data']['user']['public_id'] ?? '');
    liveAssert($userPublicId !== '', 'User public_id is required');

    $userLogin = liveRequest('POST', 'api/v1/auth/login', [
        'login' => $login,
        'password' => 'NtfLocale123!',
        'token' => $token,
    ]);
    liveAssert($userLogin['status'] === 200, 'User login must return 200');
    $userToken = (string)($userLogin['payload']['data']['access_token'] ?? '');
    liveAssert($userToken !== '', 'User token is required');

    $headers = [
        'Authorization' => 'Bearer ' . $userToken,
        'X-Locale' => 'ru-ru',
    ];

    $list = liveRequest('GET', 'api/v1/notifications', [], $headers);
    liveAssert($list['status'] === 200, 'Notifications list must return 200');
    liveAssert((string)($list['payload']['message'] ?? '') === 'Notification list', 'Notifications list message mismatch');

    $counters = liveRequest('GET', 'api/v1/notifications/counters', [], $headers);
    liveAssert($counters['status'] === 200, 'Notifications counters must return 200');
    liveAssert((string)($counters['payload']['message'] ?? '') === 'Notification counters', 'Notifications counters message mismatch');

    $validation = liveRequest('POST', 'api/v1/notifications', [
        'body' => 'x',
        'category' => 'locale_' . $suffix,
    ], $headers);
    liveAssert($validation['status'] === 422, 'Notifications validation must return 422');
    liveAssert((string)($validation['payload']['message'] ?? '') === 'Validation error', 'Notifications validation message mismatch');
    assertNoCyrillicNotification($validation['payload']['errors'] ?? [], 'notification.validation.errors');

    $create = liveRequest('POST', 'api/v1/notifications', [
        'title' => 'Locale notification ' . $suffix,
        'body' => 'Body',
        'category' => 'locale_' . $suffix,
    ], $headers);
    liveAssert($create['status'] === 201, 'Notifications create must return 201');
    liveAssert((string)($create['payload']['message'] ?? '') === 'Notification created', 'Notifications create message mismatch');
    $notificationPublicId = (string)($create['payload']['data']['notification']['public_id'] ?? '');
    liveAssert($notificationPublicId !== '', 'Notification public_id is required');

    $markRead = liveRequest('PATCH', 'api/v1/notifications/' . $notificationPublicId . '/read', [], $headers);
    liveAssert($markRead['status'] === 200, 'Notifications mark read must return 200');
    liveAssert((string)($markRead['payload']['message'] ?? '') === 'Notification marked as read', 'Notifications mark read message mismatch');

    $markUnread = liveRequest('PATCH', 'api/v1/notifications/' . $notificationPublicId . '/unread', [], $headers);
    liveAssert($markUnread['status'] === 200, 'Notifications mark unread must return 200');
    liveAssert((string)($markUnread['payload']['message'] ?? '') === 'Notification marked as unread', 'Notifications mark unread message mismatch');

    $markAll = liveRequest('POST', 'api/v1/notifications/mark-all-read', [
        'category' => 'locale_' . $suffix,
    ], $headers);
    liveAssert($markAll['status'] === 200, 'Notifications mark all read must return 200');
    liveAssert((string)($markAll['payload']['message'] ?? '') === 'Notifications marked as read', 'Notifications mark all read message mismatch');

    $notFound = liveRequest('PATCH', 'api/v1/notifications/ntf_missing_' . $suffix . '/read', [], $headers);
    liveAssert($notFound['status'] === 404, 'Notifications not found must return 404');
    liveAssert((string)($notFound['payload']['message'] ?? '') === 'Notification not found', 'Notifications not found message mismatch');
    assertNoCyrillicNotification($notFound['payload']['errors'] ?? [], 'notification.not_found.errors');

    liveRequest('DELETE', 'api/v1/users/' . $userPublicId, [], $rootHeaders);

    echo "[OK] advanced_notification_locale_mixed_live\n";
} catch (Throwable $e) {
    fwrite(STDERR, '[FAIL] advanced_notification_locale_mixed_live: ' . $e->getMessage() . "\n");
    exit(1);
}
