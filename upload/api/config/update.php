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
    // Database snapshot taken by the updater right before migrations, so a
    // rollback can restore schema AND data. The snapshot is MANDATORY when the
    // update has pending migrations: without it a mid-way migration failure
    // could not be undone, so apply aborts BEFORE any schema change. Updates
    // that only change files (no pending migrations) skip the dump entirely.
    // Set enabled=false only if you accept that updates with pending migrations
    // will be blocked; files-only updates still apply.
    'db_backup' => [
        'enabled' => true,
    ],
    // Rate limits for preflight/download requests per client IP. These actions
    // are allowed without a one-time token when dry_run=true so the
    // admin-updates page can drive them straight from the browser, which on
    // shared hosting makes them a DoS / disk-fill vector. The limit applies
    // regardless of whether a token is present (a session token must not be a
    // free pass for unlimited downloads either). Set enabled=false only when a
    // stricter gateway already does the job.
    'rate_limits' => [
        'enabled' => true,
        // Up to max_attempts requests allowed per window; the next one is
        // rejected and locks the IP for lock_seconds.
        'max_attempts' => 20,
        'window_seconds' => 300,
        'lock_seconds' => 900,
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
