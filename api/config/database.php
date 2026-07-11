<?php
declare(strict_types=1);

// SEC-002: Block direct web access
if (PHP_SAPI !== 'cli' && ($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    http_response_code(404);
    exit;
}


$default = trim((string)(getenv('DB_CONNECTION') ?: getenv('CRM_DB_DRIVER') ?: 'sqlite'));
if ($default === '') {
    $default = 'sqlite';
}

$storageBase = (string)(getenv('CRM_STORAGE_BASE') ?: dirname(__DIR__, 2) . '/../storage_api');

return [
    'default' => $default,
    'connections' => [
        'sqlite' => [
            'driver' => 'sqlite',
            'database' => (string)(getenv('CRM_SQLITE_DATABASE') ?: ($storageBase . '/temp/crm.sqlite')),
            'prefix' => '',
        ],
        'mysql' => [
            'driver' => 'mysql',
            'host' => (string)(getenv('DB_HOST') ?: getenv('MYSQL_HOST') ?: '127.0.0.1'),
            'port' => (int)(getenv('DB_PORT') ?: getenv('MYSQL_PORT') ?: 3306),
            'database' => (string)(getenv('DB_DATABASE') ?: getenv('MYSQL_DATABASE') ?: 'crm'),
            'username' => (string)(getenv('DB_USERNAME') ?: getenv('MYSQL_USER') ?: 'root'),
            'password' => (string)(getenv('DB_PASSWORD') ?: getenv('MYSQL_PASSWORD') ?: ''),
            'charset' => (string)(getenv('DB_CHARSET') ?: 'utf8mb4'),
            'prefix' => (string)(getenv('DB_PREFIX') ?: ''),
        ],
        'pgsql' => [
            'driver' => 'pgsql',
            'host' => (string)(getenv('DB_HOST') ?: '127.0.0.1'),
            'port' => (int)(getenv('DB_PORT') ?: 5432),
            'database' => (string)(getenv('DB_DATABASE') ?: 'crm'),
            'username' => (string)(getenv('DB_USERNAME') ?: 'postgres'),
            'password' => (string)(getenv('DB_PASSWORD') ?: ''),
            'charset' => (string)(getenv('DB_CHARSET') ?: 'utf8'),
            'prefix' => (string)(getenv('DB_PREFIX') ?: ''),
        ],
        'sqlsrv' => [
            'driver' => 'sqlsrv',
            'host' => (string)(getenv('DB_HOST') ?: '127.0.0.1'),
            'port' => (int)(getenv('DB_PORT') ?: 1433),
            'database' => (string)(getenv('DB_DATABASE') ?: 'crm'),
            'username' => (string)(getenv('DB_USERNAME') ?: 'sa'),
            'password' => (string)(getenv('DB_PASSWORD') ?: ''),
            'charset' => (string)(getenv('DB_CHARSET') ?: 'utf8'),
            'prefix' => (string)(getenv('DB_PREFIX') ?: ''),
        ],
    ],
];
