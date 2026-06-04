<?php
declare(strict_types=1);

return [
    'push' => [
        // Optional HTTP gateway that performs actual Web Push delivery.
        // When empty, push attempts are only logged.
        'gateway_url' => (string)(getenv('NOTIFICATIONS_PUSH_GATEWAY_URL') ?: ''),
        'timeout_sec' => max(1, (int)(getenv('NOTIFICATIONS_PUSH_TIMEOUT_SEC') ?: 5)),
        'max_subscriptions_per_dispatch' => max(1, (int)(getenv('NOTIFICATIONS_PUSH_MAX_SUBSCRIPTIONS_PER_DISPATCH') ?: 100)),
    ],
];
