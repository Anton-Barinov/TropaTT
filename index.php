<?php declare(strict_types=1);

$maintenanceFlag = __DIR__ . '/storage_api/maintenance.flag';
if (is_file($maintenanceFlag)) {
    http_response_code(503);
    header('Content-Type: text/html; charset=utf-8');
    echo '<!doctype html><meta charset="utf-8"><title>Maintenance</title><body style="font-family:sans-serif;padding:40px"><h1>TropaTT maintenance</h1><p>Core update maintenance mode is active. Recovery is available at <code>/updater/rescue.php</code>.</p></body>';
    exit;
}

$dashboardUrl = '/web/index.php?route=dashboard';
$loginUrl = '/web/index.php?route=login';

function redirectTo(string $url): never
{
    header('Location: ' . $url, true, 302);
    exit;
}

function isAuthorized(): bool
{
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $apiUrl = $scheme . '://' . $host . '/api/index.php?route=api/v1/auth/me';

    $headers = [
        'Accept: application/json',
        'X-Requested-With: XMLHttpRequest',
    ];

    if (!empty($_SERVER['HTTP_COOKIE'])) {
        $headers[] = 'Cookie: ' . (string)$_SERVER['HTTP_COOKIE'];
    }

    $context = stream_context_create([
        'http' => [
            'method' => 'GET',
            'header' => implode("\r\n", $headers) . "\r\n",
            'timeout' => 2,
            'ignore_errors' => true,
        ],
    ]);

    $response = @file_get_contents($apiUrl, false, $context);
    if ($response === false) {
        return false;
    }

    $decoded = json_decode($response, true);
    if (!is_array($decoded)) {
        return false;
    }

    if (!array_key_exists('success', $decoded)) {
        return false;
    }

    return $decoded['success'] === true;
}

$requestUri = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?? '/';

if (preg_match('/\.(css|js|svg|png|ico|woff2?|ttf|map|jpg|gif|webp)$/i', $requestUri)) {
    http_response_code(404);
    exit;
}

if (isAuthorized()) {
    redirectTo($dashboardUrl);
}

redirectTo($loginUrl);
