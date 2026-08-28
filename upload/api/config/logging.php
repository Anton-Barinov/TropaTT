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
];
