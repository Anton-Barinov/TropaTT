<?php
declare(strict_types=1);

$env = strtolower(trim((string)(getenv('APP_ENV') ?: 'prod')));
$isProduction = in_array($env, ['prod', 'production'], true);
$appKey = trim((string)(getenv('APP_KEY') ?: ''));
$localSecret = trim((string)(getenv('CRM_LOCAL_SECRET') ?: $appKey));
$csrfSecret = trim((string)(getenv('CSRF_SECRET_KEY') ?: ''));
$webhookSecret = trim((string)(getenv('WEBHOOK_SECRET_KEY') ?: ''));
$aiEncryptionKey = trim((string)(getenv('AI_ENCRYPTION_KEY') ?: ''));

if ($csrfSecret === '') {
    $csrfSecret = $appKey;
}
if ($webhookSecret === '') {
    $webhookSecret = $appKey;
}

if (!$isProduction) {
    if ($csrfSecret === '') {
        $csrfSecret = $localSecret;
    }
    if ($webhookSecret === '') {
        $webhookSecret = $localSecret;
    }
    if ($aiEncryptionKey === '') {
        $aiEncryptionKey = $localSecret;
    }
}
if ($webhookSecret === '') {
    $webhookSecret = $appKey;
}

if (!$isProduction) {
    if ($csrfSecret === '') {
        $csrfSecret = $localSecret;
    }
    if ($webhookSecret === '') {
        $webhookSecret = $localSecret;
    }
}

$accessTokenTtl = (int)(getenv('CRM_AUTH_ACCESS_TOKEN_TTL') ?: getenv('AUTH_ACCESS_TOKEN_TTL') ?: (3600 * 24 * 3));
$refreshTokenTtl = (int)(getenv('CRM_AUTH_REFRESH_TOKEN_TTL') ?: getenv('AUTH_REFRESH_TOKEN_TTL') ?: (3600 * 24 * 14));

return [
    'auth' => [
        'access_token_ttl' => max(3600, $accessTokenTtl),
        'refresh_token_ttl' => max(3600 * 24, $refreshTokenTtl),
        'password_algo' => PASSWORD_ARGON2ID,
        'lock_threshold' => 5,
        'lock_seconds' => 300,
        'cookie' => [
            'name' => 'crm_api_session',
            'path' => '/',
            'same_site' => 'Lax',
            'secure_only' => $isProduction,
        ],
        'csrf' => [
            'header' => 'X-CSRF-Token',
            'cookie' => 'crm_csrf_token',
            'secret_key' => $csrfSecret,
        ],
    ],
    'rate_limit' => [
        'auth_login' => [
            'max' => 15,
            'window_sec' => 60,
        ],
        'password_reset' => [
            'max' => 5,
            'window_sec' => 300,
            'lock_seconds' => 900,
        ],
        'route_global' => [
            'max' => 120,
            'window_sec' => 60,
            'lock_seconds' => 60,
        ],
    ],
    'webhook' => [
        'retry_attempts' => 3,
        'retry_backoff_ms' => 200,
        'auto_disable_after_failures' => 3,
        'timeout_sec' => 5,
        'secret_key' => $webhookSecret,
        'allowed_schemes' => ['https'],
        'block_private_networks_in_production' => true,
        'allow_insecure_local_dev_urls' => false,
    ],
    'ai' => [
        'encryption_key' => $aiEncryptionKey,
    ],
    'cors' => [
        'allow_origin' => (string)(getenv('CORS_ALLOW_ORIGIN') ?: 'https://localhost,http://localhost,https://127.0.0.1,http://127.0.0.1,https://crm.ru,http://crm.ru'),
        'allow_methods' => 'GET, POST, PATCH, PUT, DELETE, OPTIONS',
        'allow_headers' => 'Content-Type, Authorization, X-CSRF-Token, X-Request-Id, X-Correlation-Id, X-Idempotency-Key, X-Locale',
    ],
    'uploads' => [
        'max_size_bytes' => 20 * 1024 * 1024,
        'quarantine_extensions' => ['php', 'phtml', 'phar', 'exe', 'sh', 'bat', 'cmd', 'com', 'msi', 'dll', 'html', 'htm', 'svg', 'xhtml'],
        'quarantine_mime_prefixes' => ['application/x-php', 'application/x-sh', 'application/x-msdownload'],
    ],
];
