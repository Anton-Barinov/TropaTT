<?php declare(strict_types=1);

// Maintenance mode check — blocks all requests during core updates
$maintenanceFlag = __DIR__ . '/storage_api/maintenance.flag';
if (is_file($maintenanceFlag)) {
    http_response_code(503);
    header('Content-Type: text/html; charset=utf-8');
    echo '<!doctype html><meta charset="utf-8"><title>Maintenance</title><body style="font-family:sans-serif;padding:40px"><h1>TropaTT maintenance</h1><p>Core update maintenance mode is active. Recovery is available at <code>/updater/rescue.php</code>.</p></body>';
    exit;
}

// Block direct access to static assets at the document root — server should handle these
$requestUri = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?? '/';
if (preg_match('/\.(css|js|svg|png|ico|woff2?|ttf|map|jpg|gif|webp)$/i', $requestUri)) {
    http_response_code(404);
    exit;
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
