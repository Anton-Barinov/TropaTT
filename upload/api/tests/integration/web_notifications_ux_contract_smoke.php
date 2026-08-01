<?php
declare(strict_types=1);

function failUx(string $message): void
{
    fwrite(STDERR, "[FAIL] web_notifications_ux_contract_smoke: {$message}\n");
    exit(1);
}

function readFileUx(string $path): string
{
    $content = file_get_contents($path);
    if (!is_string($content) || $content === '') {
        failUx('Cannot read file: ' . $path);
    }
    return $content;
}

$root = dirname(__DIR__, 3);
$template = readFileUx($root . '/web/view/template/page/notifications.php');
$bindings = readFileUx($root . '/web/assets/js/page-api-bindings.js');
$realtime = readFileUx($root . '/web/assets/js/notifications-realtime.js');
$push = readFileUx($root . '/web/assets/js/notifications-push.js');

foreach ([
    'id="notificationsPushEnable"',
    'id="notificationsSoundEnable"',
    'id="notificationsQuietHoursEnable"',
    'id="notificationsMatrixBody"',
    'id="notificationsMatrixSaveBtn"',
    'id="notificationsMatrixResetBtn"',
    'id="notificationsListSummary"',
    'crm-notifications-feed',
    'data-notifications-list aria-live="polite"',
] as $needle) {
    if (!str_contains($template, $needle)) {
        failUx('Notifications template missing UX contract node: ' . $needle);
    }
}

foreach ([
    'notificationsAriaLive',
    'data-popover-notification-action="mark-read"',
    'data-popover-notification-action="mark-all-read"',
    'notificationsPushPermissionBtn',
    'notificationsPushTestBtn',
    'notificationsSoundTestBtn',
    'notificationsSoundSaveBtn',
    'notificationsMatrixSaveBtn',
    'notificationSafeInternalLink',
    'confirmNotificationsAction',
    'Отключить push-устройство?',
    'Отметить все прочитанным?',
] as $needle) {
    if (!str_contains($bindings, $needle)) {
        failUx('Page bindings missing UX contract hook: ' . $needle);
    }
}

foreach ([
    'SOUND_KEY',
    'QUIET_HOURS_KEY',
    'CHANNEL_MATRIX_KEY',
    'isChannelEnabled',
    'setChannelMatrix',
] as $needle) {
    if (!str_contains($realtime, $needle)) {
        failUx('Realtime notifications module missing contract: ' . $needle);
    }
}

foreach ([
    'crm_push_notifications_enabled',
    'requestPermissionAndEnable',
    'push-subscriptions',
] as $needle) {
    if (!str_contains($push, $needle)) {
        failUx('Push notifications module missing contract: ' . $needle);
    }
}

if (!str_contains($bindings, 'api/v1/notifications/push-test')) {
    failUx('Page bindings missing push-test endpoint contract');
}

fwrite(STDOUT, "[OK] web_notifications_ux_contract_smoke\n");
