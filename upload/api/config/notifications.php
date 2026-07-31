<?php
declare(strict_types=1);

// SEC-002: Block direct web access
if (PHP_SAPI !== 'cli' && ($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    http_response_code(404);
    exit;
}


return [
    'push' => [
        'gateway_url' => (string)(getenv('NOTIFICATIONS_PUSH_GATEWAY_URL') ?: ''),
        'vapid_public_key' => (string)(getenv('NOTIFICATIONS_PUSH_VAPID_PUBLIC_KEY') ?: ''),
        'vapid_private_key' => (string)(getenv('NOTIFICATIONS_PUSH_VAPID_PRIVATE_KEY') ?: ''),
        'vapid_subject' => (string)(getenv('NOTIFICATIONS_PUSH_VAPID_SUBJECT') ?: ''),
        'timeout_sec' => max(1, (int)(getenv('NOTIFICATIONS_PUSH_TIMEOUT_SEC') ?: 5)),
        'max_subscriptions_per_dispatch' => max(1, (int)(getenv('NOTIFICATIONS_PUSH_MAX_SUBSCRIPTIONS_PER_DISPATCH') ?: 100)),
    ],
];
