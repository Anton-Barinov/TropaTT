<?php declare(strict_types=1);

// Maintenance mode check — blocks all requests during core updates
$maintenanceFlag = __DIR__ . '/storage_api/maintenance.flag';
if (is_file($maintenanceFlag)) {
    http_response_code(503);
    header('Content-Type: text/html; charset=utf-8');
    echo '<!doctype html><meta charset="utf-8"><title>Maintenance</title><body style="font-family:sans-serif;padding:40px"><h1>TropaTT maintenance</h1>'
        . '<p>Core update maintenance mode is active. Follow or finish the update from the admin panel: '
        . '<code>/web/index.php?route=admin-updates</code>. The updates page stays reachable during maintenance.</p>'
        . '<p>Emergency recovery (disabling maintenance) is available at <code>/updater/rescue.php</code> using the '
        . 'recovery key shown once at installation or re-issued at <code>admin-updates</code> &rarr; &laquo;Emergency recovery&raquo;.</p></body>';
    exit;
}

// Block direct access to static assets at the document root — server should handle these
$requestUri = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?? '/';
if (preg_match('/\.(css|js|svg|png|ico|woff2?|ttf|map|jpg|gif|webp)$/i', $requestUri)) {
    http_response_code(404);
    exit;
}

// SEC-008: Hide install API endpoints after setup.
// The install endpoints (status, check, setup) reveal whether the system
// is installed. When already installed, return 404 to avoid information
// disclosure. The browser installer (web/install.php) is NOT affected —
// it is a real file served directly by Apache.
$requestPath = ltrim($requestUri, '/');
if (str_starts_with($requestPath, 'install/')) {
    // Check if system is already installed (same check as web/index.php)
    $rootEnvExists = is_file(__DIR__ . '/.env') || is_file(__DIR__ . '/.env.local');
    $apiEnvExists = is_file(__DIR__ . '/api/.env') || is_file(__DIR__ . '/api/.env.local');
    $envIsSet = (getenv('DB_CONNECTION') || getenv('CRM_DB_DRIVER') || getenv('CRM_STORAGE_BASE'));
    if ($rootEnvExists || $apiEnvExists || $envIsSet) {
        http_response_code(404);
        exit;
    }
}

// SEC-003: Delegate auth and routing to the web entry point.
// The root index.php previously made a self-referencing HTTP request
// (@file_get_contents to /api/index.php?route=api/v1/auth/me) on every
// page load. This created an SSRF vector via the Host header and risked
// PHP-FPM worker pool exhaustion under load. The web entry point
// (web/index.php) handles all auth checks internally without loopback.
//
// See: https://github.com/Anton-Barinov/TropaTT/issues/security
header('Location: /web/index.php', true, 302);
exit;
