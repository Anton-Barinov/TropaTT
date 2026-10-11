<?php
declare(strict_types=1);

// Remove PHP execution limits for long-running AI operations
require_once __DIR__ . '/system/library/language/LanguageManager.php';

$earlyLanguage = new Api\System\Library\Language\LanguageManager(__DIR__ . '/language', 'en-gb');
$earlyLocale = strtolower(str_replace('_', '-', trim((string)($_SERVER['HTTP_X_LOCALE'] ?? $_COOKIE['crm_locale'] ?? 'en-gb'))));
$earlyLocale = match ($earlyLocale) {
    'ru' => 'ru-ru',
    'en' => 'en-gb',
    'zh', 'cn', 'zh-hans' => 'zh-cn',
    'es' => 'es-es',
    'pt' => 'pt-br',
    'de' => 'de-de',
    'fr' => 'fr-fr',
    'he', 'iw' => 'he-il',
    default => $earlyLocale,
};
if (!in_array($earlyLocale, ['ru-ru', 'en-gb', 'zh-cn'], true)) {
    $earlyLocale = 'en-gb';
}
$earlyLanguage->setLocale($earlyLocale);

// M-4 fix: suppress error output in production
ini_set('display_errors', '0');
ini_set('error_reporting', E_ALL & ~E_DEPRECATED & ~E_STRICT);
ini_set('max_execution_time', '0');
ini_set('memory_limit', '512M');
set_time_limit(0);

// Compatibility polyfill for platforms without Argon2 support (e.g. Android/Termux)
if (!defined('PASSWORD_ARGON2ID')) {
    define('PASSWORD_ARGON2ID', PASSWORD_BCRYPT);
}
if (!defined('PASSWORD_ARGON2I')) {
    define('PASSWORD_ARGON2I', PASSWORD_BCRYPT);
}

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
    '#/storage_api/secrets/#' ,
    '#\.json\.dist$#' ,
];
foreach ($blockedPatterns as $pattern) {
    if (preg_match($pattern, $requestPath)) {
        http_response_code(404);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['error' => 'Not Found'], JSON_UNESCAPED_SLASHES);
        exit;
    }
}

// Coordinate normal HTTP requests with updater/deployment mutations. The
// updater obtains the same inode exclusively; ordinary API requests hold a
// shared lock until this front controller finishes. An exclusive deployment
// lock therefore drains already-running requests and prevents new requests
// from entering while files or the database are being changed. The updater
// has its own entry point and must not acquire this shared lock.
$deploymentRoot = dirname(__DIR__);
$deploymentRuntimeConfigured = (getenv('DB_CONNECTION') || getenv('CRM_DB_DRIVER') || getenv('CRM_STORAGE_BASE'))
    || is_file($deploymentRoot . '/.env')
    || is_file(__DIR__ . '/.env')
    || is_file($deploymentRoot . '/.env.local')
    || is_file(__DIR__ . '/.env.local');
if ($deploymentRuntimeConfigured) {
    require_once $deploymentRoot . '/updater/src/State/DeploymentMutex.php';
    $requestDeploymentMutex = new \Updater\State\DeploymentMutex($deploymentRoot);
    $requestLockError = false;
    try {
        $requestLockAcquired = $requestDeploymentMutex->acquire(false);
    } catch (\Throwable) {
        $requestLockAcquired = false;
        $requestLockError = true;
    }
    if (!$requestLockAcquired) {
        http_response_code(503);
        header('Retry-After: 2');
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'success' => false,
            'code' => $requestLockError ? 'DEPLOYMENT_GUARD_UNAVAILABLE' : 'DEPLOYMENT_BUSY',
            'message' => $requestLockError
                ? 'The release safety lock is unavailable. No CRM operation was started.'
                : 'A deployment or verification is in progress. Retry shortly.',
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        exit;
    }
}

$maintenanceFlag = dirname(__DIR__) . '/storage_api/maintenance.flag';
if (is_file($maintenanceFlag)) {
    // Guard 3 (maintenance hold): keep the update-center API reachable during
    // held maintenance so the admin-updates page can roll back or retry a
    // failed update. These routes are still auth + RBAC protected. The list of
    // reachable routes lives in one shared policy (MaintenancePolicy.php) so
    // this entry point and web/index.php can never drift again.
    require_once __DIR__ . '/system/library/support/MaintenancePolicy.php';
    $maintenanceRoute = trim((string)($_GET['route'] ?? ''), '/');
    $maintenanceState = json_decode((string)@file_get_contents($maintenanceFlag), true);
    $strictDeploymentMaintenance = is_array($maintenanceState)
        && ($maintenanceState['reason'] ?? null) === 'deployment_pipeline';
    $maintenanceRecoveryAllowed = tropatt_maintenance_policy_allows_request($strictDeploymentMaintenance);
    if (!$maintenanceRecoveryAllowed) {
        http_response_code(503);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'success' => false,
            'code' => 'MAINTENANCE_MODE',
            'message' => $earlyLanguage->get('common/messages.maintenance_mode', 'Core update maintenance mode is active'),
            'data' => is_array($maintenanceState) ? $maintenanceState : ['enabled' => true],
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        exit;
    }
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

// Register server error logging to database (after autoloader + env).
// EnvLoader exposes .env values via getenv(), not PHP constants — mirror
// config/database.php fallbacks exactly.
$errorPdo = null;
try {
    $dbConfig = [
        'host' => (string)(getenv('DB_HOST') ?: getenv('MYSQL_HOST') ?: '127.0.0.1'),
        'port' => (int)(getenv('DB_PORT') ?: getenv('MYSQL_PORT') ?: 3306),
        'name' => (string)(getenv('DB_DATABASE') ?: getenv('MYSQL_DATABASE') ?: ''),
        'user' => (string)(getenv('DB_USERNAME') ?: getenv('MYSQL_USER') ?: ''),
        'pass' => (string)(getenv('DB_PASSWORD') ?: getenv('MYSQL_PASSWORD') ?: ''),
    ];
    if ($dbConfig['name'] !== '' && $dbConfig['user'] !== '') {
        $errorPdo = new PDO(
            'mysql:host=' . $dbConfig['host'] . ';port=' . $dbConfig['port'] . ';dbname=' . $dbConfig['name'] . ';charset=utf8mb4',
            $dbConfig['user'],
            $dbConfig['pass'],
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_SILENT, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
        );
        \Api\System\Library\Service\ServerErrorService::register($errorPdo);
    }
} catch (Throwable $e) {
    // Silently fail — error logging is best-effort
}

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
    $responseMessage = $isConfigError
        ? $earlyLanguage->get('common/messages.configuration_error', 'Configuration error')
        : $earlyLanguage->get('common/messages.internal_error', 'Internal server error');
    error_log(sprintf(
        'Tropa API bootstrap error [%s]: %s in %s:%d',
        $requestId,
        $exceptionMessage,
        $e->getFile(),
        $e->getLine()
    ));
    // Log to database if ServerErrorService is available
    try {
        if ($errorPdo !== null) {
            \Api\System\Library\Service\ServerErrorService::getInstance($errorPdo)->logError(
                'exception',
                $exceptionMessage,
                $e->getFile(),
                $e->getLine(),
                $e->getCode(),
                $e->getTraceAsString()
            );
        }
    } catch (Throwable $ignored) {}

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
