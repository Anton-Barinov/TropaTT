<?php
declare(strict_types=1);

// SEC-002: Block direct web access
if (PHP_SAPI !== 'cli' && ($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    http_response_code(404);
    exit;
}


$storageBase = (string)(getenv('CRM_STORAGE_BASE') ?: dirname(__DIR__, 2) . '/../storage_api');

return [
    'lock_file' => $storageBase . '/install.lock',
    'config_file' => __DIR__ . '/database.local.php',
    'logging_config_file' => __DIR__ . '/logging.local.php',
    'bootstrap_secret' => (string)(getenv('INSTALL_BOOTSTRAP_SECRET') ?: ''),
    'allow_loopback' => true,
    'root_user_login' => 'root',
    'root_user_email' => 'root@crm.local',
];
