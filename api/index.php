<?php
declare(strict_types=1);

// Remove PHP execution limits for long-running AI operations
ini_set('max_execution_time', '0');
ini_set('memory_limit', '512M');
set_time_limit(0);

use Api\System\Library\App;
use Api\System\Library\Http\JsonResponse;
use Api\System\Library\Support\EnvLoader;

// Block direct access to sensitive files that nginx may serve before PHP processing.
// .htaccess handles this for Apache; this is the defence-in-depth layer.
$requestPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/';
$blockedPatterns = [
    '#/composer\.(json|lock)$#',
    '#/\.env#' ,
    '#/config/#' ,
    '#/scripts/#' ,
    '#/system/#' ,
    '#/tests/#' ,
    '#/vendor/#' ,
    '#/storage_test_runtime/#' ,
];
foreach ($blockedPatterns as $pattern) {
    if (preg_match($pattern, $requestPath)) {
        http_response_code(404);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['error' => 'Not Found'], JSON_UNESCAPED_SLASHES);
        exit;
    }
}

$maintenanceFlag = dirname(__DIR__) . '/storage_api/maintenance.flag';
if (is_file($maintenanceFlag)) {
    http_response_code(503);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'success' => false,
        'code' => 'MAINTENANCE_MODE',
        'message' => 'Core update maintenance mode is active',
        'data' => json_decode((string) file_get_contents($maintenanceFlag), true) ?: ['enabled' => true],
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

require_once __DIR__ . '/system/library/support/Autoloader.php';
require_once __DIR__ . '/system/library/ai_diag.php';

$autoloader = new Api\System\Library\Support\Autoloader(__DIR__);
$autoloader->register();

EnvLoader::loadFiles([
    dirname(__DIR__) . '/.env',
    __DIR__ . '/.env',
    dirname(__DIR__) . '/.env.local',
    __DIR__ . '/.env.local',
]);

try {
    $app = new App(__DIR__);
    $response = $app->run();
    $response->send();
} catch (Throwable $e) {
    $requestId = bin2hex(random_bytes(8));
    $isDev = defined('APP_ENV') && APP_ENV === 'dev';
    $exceptionMessage = $e->getMessage();
    $isConfigError = str_starts_with($exceptionMessage, 'CONFIG_');
    $responseCode = $isConfigError ? 'CONFIGURATION_ERROR' : 'INTERNAL_ERROR';
    $responseMessage = $isConfigError ? 'Configuration error' : 'Internal server error';
    error_log(sprintf(
        'Tropa API bootstrap error [%s]: %s in %s:%d',
        $requestId,
        $exceptionMessage,
        $e->getFile(),
        $e->getLine()
    ));

    $response = JsonResponse::error(
        code: $responseCode,
        message: $responseMessage,
        status: 500,
        errors: ['exception' => [$isDev || $isConfigError ? $responseCode : 'Internal server error']],
        requestId: $requestId,
        correlationId: $requestId
    );
    $response->send();
}
