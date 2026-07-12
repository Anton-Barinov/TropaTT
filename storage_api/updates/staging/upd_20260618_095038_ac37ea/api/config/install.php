<?php
declare(strict_types=1);

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
