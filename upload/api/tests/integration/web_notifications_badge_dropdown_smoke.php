<?php
declare(strict_types=1);

require __DIR__ . '/_bootstrap.php';

try {
    $root = loginRoot();
    $token = (string)($root['token'] ?? '');
    assertTrue($token !== '', 'Root token is required');
    $headers = authHeaders($token);

    $create = request('POST', '/api/v1/notifications', [
        'title' => 'Web notify smoke ' . randomSuffix(),
        'body' => 'Smoke notification body',
        'category' => 'system',
        'entity_type' => 'task',
        'entity_public_id' => '',
        'action_code' => 'smoke_notification',
        'link' => 'index.php?route=notifications',
    ], $headers);
    assertTrue($create['status'] === 201, 'Notification create must return 201');
    $notificationPublicId = (string)($create['payload']['data']['notification']['public_id'] ?? '');
    assertTrue($notificationPublicId !== '', 'Notification public_id is required');

    $counters = request('GET', '/api/v1/notifications/counters', [], $headers);
    assertTrue($counters['status'] === 200, 'Notification counters must return 200');
    $unread = (int)($counters['payload']['data']['counters']['unread'] ?? 0);
    assertTrue($unread >= 1, 'Unread notifications counter must be >= 1 after create');

    $markRead = request('PATCH', '/api/v1/notifications/' . $notificationPublicId . '/read', [], $headers);
    assertTrue($markRead['status'] === 200, 'Mark read must return 200');

    $webIndex = dirname(__DIR__, 2) . '/../web/index.php';
    assertTrue(is_file($webIndex), 'Web index.php must exist');

    $_GET = ['route' => 'notifications'];
    $_POST = [];
    $_FILES = [];
    $_COOKIE = [];
    $_SERVER = [
        'REQUEST_METHOD' => 'GET',
        'REQUEST_URI' => '/index.php?route=notifications',
        'SCRIPT_NAME' => '/index.php',
        'REMOTE_ADDR' => '127.0.0.1',
        'HTTP_USER_AGENT' => 'crm-web-notify-smoke/1.0',
    ];

    ob_start();
    require $webIndex;
    $html = (string)ob_get_clean();
    assertTrue($html !== '', 'Rendered notifications html must not be empty');
    assertTrue(str_contains($html, 'data-notifications-list'), 'Notifications list container must be rendered');
    assertTrue(str_contains($html, 'id="notificationsMarkAllBtn"'), 'Mark-all-read button must be rendered');

    $bindingsJsPath = dirname(__DIR__, 2) . '/../web/assets/js/page-api-bindings.js';
    assertTrue(is_file($bindingsJsPath), 'page-api-bindings.js must exist');
    $bindingsJs = (string)file_get_contents($bindingsJsPath);
    assertTrue(str_contains($bindingsJs, 'renderNotificationsPopover'), 'renderNotificationsPopover handler must exist');
    assertTrue(str_contains($bindingsJs, 'data-notification-badge'), 'Notification badge markup logic must exist');
    assertTrue(str_contains($bindingsJs, 'data-popover-notification-action="mark-all-read"'), 'Popover mark-all-read action must exist');

    fwrite(STDOUT, "[OK] web_notifications_badge_dropdown_smoke\n");
} catch (Throwable $e) {
    fwrite(STDERR, "[FAIL] web_notifications_badge_dropdown_smoke: " . $e->getMessage() . "\n");
    exit(1);
}

