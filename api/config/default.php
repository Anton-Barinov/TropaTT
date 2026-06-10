<?php
declare(strict_types=1);

$storageBase = (string)(getenv('CRM_STORAGE_BASE') ?: dirname(__DIR__, 2) . '/../storage_api');

return [
    'app' => [
        'name' => 'TropaTT API',
        'env' => getenv('APP_ENV') ?: 'prod',
        'debug' => (getenv('APP_DEBUG') ?: '0') === '1',
        'timezone' => getenv('APP_TIMEZONE') ?: 'Europe/Moscow',
        'version' => 'v1',
    ],
    'locale' => [
        'default' => 'en-gb',
        'fallback' => 'en-gb',
        'supported' => ['ru-ru', 'en-gb'],
    ],
    'storage' => [
        'base' => $storageBase,
        'uploads' => $storageBase . '/uploads',
        'quarantine' => $storageBase . '/quarantine',
        'logs' => $storageBase . '/logs',
        'sessions' => $storageBase . '/sessions',
        'temp' => $storageBase . '/temp',
        'cache' => $storageBase . '/cache',
        'secrets' => $storageBase . '/secrets',
    ],

    'api_file_cache' => [
        'enabled' => (getenv('API_FILE_CACHE_ENABLED') ?: 'false') === 'true',
        'default_ttl' => (int)(getenv('API_FILE_CACHE_TTL') ?: 60),
        'debug' => (getenv('API_FILE_CACHE_DEBUG') ?: 'false') === 'true',
    ],
];
