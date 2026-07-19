<?php
declare(strict_types=1);

// Block direct web access — this file is only meant to be require'd
if (PHP_SAPI !== 'cli' && ($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    http_response_code(404);
    exit;
}

$env = strtolower(trim((string)(getenv('APP_ENV') ?: 'prod')));
$isProduction = in_array($env, ['prod', 'production'], true);
$appKey = trim((string)(getenv('APP_KEY') ?: ''));
$csrfSecret = trim((string)(getenv('CSRF_SECRET_KEY') ?: ''));
$webhookSecret = trim((string)(getenv('WEBHOOK_SECRET_KEY') ?: ''));
$aiEncryptionKey = trim((string)(getenv('AI_ENCRYPTION_KEY') ?: ''));

// Production: each secret MUST be explicitly set; fail-fast if missing.
// Non-production: generate independent random keys with a warning (no fallback chain).
if ($isProduction) {
    if ($csrfSecret === '') {
        throw new \RuntimeException('CSRF_SECRET_KEY must be set in production');
    }
    if ($webhookSecret === '') {
        throw new \RuntimeException('WEBHOOK_SECRET_KEY must be set in production');
    }
    if ($aiEncryptionKey === '') {
        throw new \RuntimeException('AI_ENCRYPTION_KEY must be set in production');
    }
} else {
    $missingKeys = [];
    if ($csrfSecret === '') {
        $csrfSecret = bin2hex(random_bytes(32));
        $missingKeys[] = 'CSRF_SECRET_KEY';
    }
    if ($webhookSecret === '') {
        $webhookSecret = bin2hex(random_bytes(32));
        $missingKeys[] = 'WEBHOOK_SECRET_KEY';
    }
    if ($aiEncryptionKey === '') {
        $aiEncryptionKey = bin2hex(random_bytes(32));
        $missingKeys[] = 'AI_ENCRYPTION_KEY';
    }
    if ($missingKeys !== []) {
        error_log('SECURITY WARNING: Auto-generated random secrets for non-production: ' . implode(', ', $missingKeys) . '. Set explicit keys in .env for production.');
    }
}

$accessTokenTtl = (int)(getenv('CRM_AUTH_ACCESS_TOKEN_TTL') ?: getenv('AUTH_ACCESS_TOKEN_TTL') ?: (3600 * 24 * 3));
$refreshTokenTtl = (int)(getenv('CRM_AUTH_REFRESH_TOKEN_TTL') ?: getenv('AUTH_REFRESH_TOKEN_TTL') ?: (3600 * 24 * 14));
$maxSessionLifetime = (int)(getenv('CRM_AUTH_MAX_SESSION_LIFETIME') ?: (3600 * 24 * 30));

return [
    'auth' => [
        'access_token_ttl' => max(3600, $accessTokenTtl),
        'refresh_token_ttl' => max(3600 * 24, $refreshTokenTtl),
        'max_session_lifetime' => max(3600 * 24, $maxSessionLifetime),
        'password_algo' => PASSWORD_ARGON2ID,
        'lock_threshold' => 5,
        'lock_seconds' => 300,
        'cookie' => [
            'name' => 'crm_api_session',
            'path' => '/',
            'same_site' => 'Strict',
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
    // SEC-005: Trusted proxies for client IP resolution.
    // Comma-separated CIDR ranges from CRM_TRUSTED_PROXIES env var.
    // Empty by default — no proxy processing, raw REMOTE_ADDR is used.
    'trusted_proxies' => array_values(array_filter(array_map('trim',
        explode(',', (string)(getenv('CRM_TRUSTED_PROXIES') ?: ''))
    ))),
    'trusted_proxy_header' => (string)(getenv('CRM_TRUSTED_PROXY_HEADER') ?: 'X-Forwarded-For'),

    'cors' => [
        'allow_origin' => (string)(getenv('CORS_ALLOW_ORIGIN') ?: ($isProduction ? '' : 'https://localhost,http://localhost,https://127.0.0.1,http://127.0.0.1')),
        'allow_methods' => 'GET, POST, PATCH, PUT, DELETE, OPTIONS',
        'allow_headers' => 'Content-Type, Authorization, X-CSRF-Token, X-Request-Id, X-Correlation-Id, X-Idempotency-Key, X-Locale',
    ],
    'uploads' => [
        'max_size_bytes' => 20 * 1024 * 1024,
        'rate_limit' => [
            'max' => 50,
            'window_sec' => 3600,
        ],
        // SEC-001: Files matching these extensions are REJECTED entirely — never written to disk
        'forbidden_extensions' => [
            'php', 'phtml', 'php3', 'php4', 'php5', 'php7', 'php8', 'phps', 'phar', 'pht',
            'cgi', 'pl', 'py', 'rb', 'sh', 'bash', 'bat', 'cmd', 'com', 'exe', 'msi', 'dll',
            'so', 'jsp', 'jspx', 'asp', 'aspx', 'ashx', 'asmx', 'cfm', 'htaccess', 'user.ini',
        ],
        // SEC-001: Files matching these extensions ARE saved, but served with neutral Content-Type and forced attachment
        'quarantine_extensions' => ['svg', 'html', 'htm', 'xhtml', 'shtml', 'xml', 'swf'],
        'quarantine_mime_prefixes' => ['application/x-php', 'application/x-sh', 'application/x-msdownload'],
    ],
];
