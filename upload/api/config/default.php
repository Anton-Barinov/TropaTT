<?php
declare(strict_types=1);

// SEC-002: Block direct web access
if (PHP_SAPI !== 'cli' && ($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    http_response_code(404);
    exit;
}


$storageBase = (string)(getenv('CRM_STORAGE_BASE') ?: dirname(__DIR__, 1) . '/../storage_api');
$appEnv = strtolower(trim((string)(getenv('APP_ENV') ?: 'prod')));

return [
    'app' => [
        'name' => 'TropaTT API',
        'env' => getenv('APP_ENV') ?: 'prod',
        'debug' => in_array($appEnv, ['production', 'prod'], true) ? false : ((getenv('APP_DEBUG') ?: '0') === '1'),
        'timezone' => getenv('APP_TIMEZONE') ?: 'Europe/Moscow',
        'version' => 'v1',
    ],
    'locale' => [
        'default' => 'en-gb',
        'fallback' => 'en-gb',
        'supported' => ['ru-ru', 'en-gb', 'zh-cn', 'es-es', 'pt-br', 'de-de', 'fr-fr'],
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
        'gc_enabled' => (getenv('API_FILE_CACHE_GC_ENABLED') ?: 'true') === 'true',
        'gc_probability' => (int)(getenv('API_FILE_CACHE_GC_PROBABILITY') ?: 1),
        'gc_max_age' => (int)(getenv('API_FILE_CACHE_GC_MAX_AGE') ?: 86400),
        'gc_limit' => (int)(getenv('API_FILE_CACHE_GC_LIMIT') ?: 100),
    ],
];
