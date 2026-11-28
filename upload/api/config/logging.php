<?php
declare(strict_types=1);

// SEC-002: Block direct web access
if (PHP_SAPI !== 'cli' && ($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    http_response_code(404);
    exit;
}


$storageBase = (string)(getenv('CRM_STORAGE_BASE') ?: dirname(__DIR__, 2) . '/storage_api');

return [
    'channels' => [
        'request' => $storageBase . '/logs/request.log',
        'audit' => $storageBase . '/logs/audit.log',
        'security' => $storageBase . '/logs/security.log',
        'error' => $storageBase . '/logs/error.log',
        'install' => $storageBase . '/logs/install.log',
    ],
    'mask_keys' => ['password', 'token', 'authorization', 'secret', 'api_key', 'cookie', 'set-cookie', 'refresh_token', 'invitation_token', 'reset_token', 'accept_token', 'user_token'],
    'db_skip_routes_on_success' => [
        'api/v1/notifications/counters',
        'api/v1/chats/unread-count',
        'api/v1/notifications',
        'api/v1/telemetry/heartbeat',
        'api/v1/health',
    ],
    'rotation' => [
        'enabled' => (bool)(getenv('CRM_LOG_ROTATION_ENABLED') !== 'false'),
        'max_file_size_bytes' => (int)(getenv('CRM_LOG_MAX_FILE_SIZE') ?: 10 * 1024 * 1024),
        'max_files' => (int)(getenv('CRM_LOG_MAX_FILES') ?: 5),
        'compress' => (bool)(getenv('CRM_LOG_COMPRESS') !== 'false'),
        'retention_days' => (int)(getenv('CRM_LOG_RETENTION_DAYS') ?: 14),
    ],
];
