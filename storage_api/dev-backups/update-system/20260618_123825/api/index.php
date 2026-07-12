<?php
declare(strict_types=1);

// Remove PHP execution limits for long-running AI operations
ini_set('max_execution_time', '0');
ini_set('memory_limit', '512M');
set_time_limit(0);

use Api\System\Library\App;
use Api\System\Library\Http\JsonResponse;
use Api\System\Library\Support\EnvLoader;

header('X-Powered-By: Tropa-CRM-API');

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
    $responseCode = $isConfigError ? $exceptionMessage : 'INTERNAL_ERROR';
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
