<?php
declare(strict_types=1);

// SEC-002: Block direct web access
if (PHP_SAPI !== 'cli' && ($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    http_response_code(404);
    exit;
}


return [
    'enabled' => true,
    'product' => 'tropatt-core',
    'channel' => getenv('TROPATT_UPDATE_CHANNEL') ?: 'stable',
    'update_center_url' => getenv('TROPATT_UPDATE_CENTER_URL') ?: 'https://update.tropatt.com',
    'local_updater_url' => getenv('TROPATT_LOCAL_UPDATER_URL') ?: '',
    'storage_dir' => dirname(__DIR__, 2) . '/storage_api/updates',
    'public_key_path' => dirname(__DIR__, 2) . '/updater/keys/update_public.pem',
    'timeouts' => [
        'check' => 10,
        'download' => 120,
        // Used by CoreUpdateController when it proxies the updater over HTTP
        // (preflight). Actual apply/rollback are invoked by the page JS directly
        // against updater/index.php, which lifts max_execution_time to 600s.
        // Generous anyway so slow shared hosts survive a slow preflight.
        'apply_step' => 300,
    ],
    'limits' => [
        'max_package_bytes' => 100 * 1024 * 1024,
        'min_free_space_multiplier' => 3,
    ],
    'core_paths' => [
        'api/**',
        'web/**',
        'index.php',
        'favicon.ico',
        'README.md',
        'CHANGELOG.md',
        'SECURITY.md',
        'AGENTS.md',
        'docs/**',
        'updater/**',
    ],
    'protected_paths' => [
        'modules/**',
        'storage/**',
        'storage_api/**',
        'uploads/**',
        'backups/**',
        'logs/**',
        'cache/**',
        '.env',
        '.env.local',
        'api/.env',
        'api/.env.local',
        'api/config/*.local.php',
        'web/config/*.local.php',
    ],
    'endpoints' => [
        'health' => '/api/v1/health',
        'product' => '/api/v1/products/{product}',
        'channel' => '/api/v1/products/{product}/channels/{channel}',
        'update_plan' => '/api/v1/products/{product}/update-plan',
        'changes' => '/api/v1/products/{product}/changes',
        'manifest_delta' => '/api/v1/manifests/{product}/{from}/{to}',
        'manifest_full' => '/api/v1/manifests/{product}/full/{to}',
        'public_key' => '/api/v1/products/{product}/public-key',
    ],
];
