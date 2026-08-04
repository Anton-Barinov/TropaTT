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
        // Per-socket-read timeout for the package download. The package is
        // fetched in ONE streaming pass (memory-flat); on very slow shared
        // hosts where a 100MB package cannot transfer inside the web-server
        // proxy timeout, lower max_package_bytes below or mirror the package
        // on a faster URL. Extraction itself is step-chunked.
        'download' => 120,
        // Used by CoreUpdateController when it proxies the updater over HTTP
        // (preflight). Actual apply/rollback are invoked by the page JS directly
        // against updater/index.php, which lifts max_execution_time to 600s.
        // Generous anyway so slow shared hosts survive a slow preflight.
        'apply_step' => 300,
    ],
    // Step budgets: how much REAL work a single updater HTTP request may do
    // before it returns {continue:true} so the page issues the next request.
    // This keeps every request far below shared-hosting limits that no amount
    // of set_time_limit() can lift: nginx proxy_read_timeout (60s by default),
    // Apache Timeout (300s), PHP-FPM request_terminate_timeout, and hosting
    // firewalls that kill long requests. Large updates and big databases are
    // processed as many small requests instead of one huge one, so the same
    // code runs identically on virtual/shared hosting and on a VPS.
    'steps' => [
        // Hard ceiling of wall-clock work per updater request. 20s leaves
        // comfortable headroom under a 30s/60s web-server timeout even when
        // PHP startup, token checks and JSON I/O add a few seconds.
        'max_seconds_per_request' => 20,
        // File backup / file apply / rollback: at most this many files per
        // request (whichever limit trips first with the time budget).
        'max_files_per_request' => 150,
        // DB backup: at most this many dumped rows per request. Tables are
        // resumed with LIMIT/OFFSET, so memory stays flat per chunk.
        'max_rows_per_request' => 50000,
        // DB migrations: at most this many migrations per request. One is the
        // safest default for shared hosting - a single slow migration cannot
        // blow the whole request.
        'max_migrations_per_request' => 1,
        // DB restore: at most this many SQL statements per request.
        'max_statements_per_request' => 500,
        // How long the apply/rollback lock stays valid between steps without
        // a heartbeat. Every step renews it; a crashed job (browser closed
        // mid-update) becomes reclaimable after this many seconds.
        'lock_ttl_seconds' => 600,
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
