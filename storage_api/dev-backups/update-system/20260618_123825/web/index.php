<?php
declare(strict_types=1);

ini_set('max_execution_time', '0');
ini_set('memory_limit', '512M');
set_time_limit(0);

$baseDir = __DIR__;

// Redirect to installer when .env is missing
$scriptFile = basename($_SERVER['SCRIPT_NAME'] ?? 'index.php');
$envFile = dirname(__DIR__) . '/api/.env';
$envLocal = dirname(__DIR__) . '/api/.env.local';
$envIsSet = (getenv('DB_CONNECTION') || getenv('CRM_DB_DRIVER') || getenv('CRM_STORAGE_BASE'));
$hasConfig = $envIsSet || is_file($envFile) || is_file($envLocal);
if ($scriptFile !== 'install.php' && !$hasConfig) {
    $webDir = rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? '/web'), '/');
    header('Location: ' . $webDir . '/install.php', true, 302);
    exit;
}

/**
 * Baseline web security headers.
 * CSP is enforced; set CRM_WEB_CSP_REPORT_ONLY=1 only for emergency rollout diagnostics.
 */
if (!headers_sent()) {
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: geolocation=(), microphone=(), camera=()');
    header('Cross-Origin-Opener-Policy: same-origin-allow-popups');
    header('Cross-Origin-Resource-Policy: same-origin');

    $csp = implode('; ', [
        "default-src 'self'",
        "base-uri 'self'",
        "object-src 'none'",
        "frame-ancestors 'self'",
        "img-src 'self' data: blob: https:",
        "font-src 'self' data: https:",
        "style-src 'self' 'unsafe-inline' https:",
        "script-src 'self' 'unsafe-inline' 'unsafe-eval' https:",
        "connect-src 'self' https: wss:",
        "worker-src 'self' blob:",
        "report-uri /api/index.php?route=api/v1/telemetry/csp-report",
    ]);
    $cspHeader = trim((string)getenv('CRM_WEB_CSP_REPORT_ONLY')) === '1'
        ? 'Content-Security-Policy-Report-Only'
        : 'Content-Security-Policy';
    header($cspHeader . ': ' . $csp);
}

spl_autoload_register(static function (string $class) use ($baseDir): void {
    $prefixes = [
        'Web\\System\\' => $baseDir . '/system/',
        'Web\\Controller\\' => $baseDir . '/controller/',
    ];

    foreach ($prefixes as $prefix => $pathBase) {
        if (strncmp($class, $prefix, strlen($prefix)) !== 0) {
            continue;
        }

        $relative = substr($class, strlen($prefix));
        $relativePath = str_replace('\\', '/', $relative) . '.php';
        $fullPath = $pathBase . $relativePath;

        if (is_file($fullPath)) {
            require_once $fullPath;
        }
    }
});

$routes = require $baseDir . '/config/routes.php';

$requestUri = $_SERVER['REQUEST_URI'] ?? '/';
$scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
$basePath = rtrim(str_replace('index.php', '', $scriptName), '/');

$route = $_GET['route'] ?? null;
if ($route === null || $route === '') {
    $path = parse_url($requestUri, PHP_URL_PATH) ?? '/';
    if ($basePath !== '' && str_starts_with($path, $basePath)) {
        $path = substr($path, strlen($basePath));
    }
    $route = trim($path, '/');
}

$route = trim((string)$route, '/');
if ($route === '') {
    $route = 'dashboard';
}

// Handle API routes
if (str_starts_with($route, 'api/')) {
    $_SERVER['REQUEST_URI'] = '/' . $route;
    $_GET['route'] = $route;
    include dirname(__DIR__) . '/api/index.php';
    exit;
}

function crmWebApiSessionCookieIsValid(string $sessionToken, string $webBaseDir): bool
{
    $sessionToken = trim($sessionToken);
    if ($sessionToken === '' || strlen($sessionToken) > 4096) {
        return false;
    }

    $apiBaseDir = dirname($webBaseDir) . '/api';
    $apiAutoloader = $apiBaseDir . '/system/library/support/Autoloader.php';
    if (!is_file($apiAutoloader)) {
        return false;
    }

    require_once $apiAutoloader;

    static $apiAutoloaderRegistered = false;
    if (!$apiAutoloaderRegistered) {
        $loader = new Api\System\Library\Support\Autoloader($apiBaseDir);
        $loader->register();
        $apiAutoloaderRegistered = true;
    }

    if (class_exists(Api\System\Library\Support\EnvLoader::class)) {
        Api\System\Library\Support\EnvLoader::loadFiles([
            dirname($apiBaseDir) . '/.env',
            $apiBaseDir . '/.env',
            dirname($apiBaseDir) . '/.env.local',
            $apiBaseDir . '/.env.local',
        ]);
    }

    $databaseConfigPath = $apiBaseDir . '/config/database.php';
    if (!is_file($databaseConfigPath)) {
        return false;
    }

    $databaseConfig = require $databaseConfigPath;
    if (!is_array($databaseConfig)) {
        return false;
    }

    $localDbConfigPath = $apiBaseDir . '/config/database.local.php';
    if (is_file($localDbConfigPath)) {
        $localOverride = require $localDbConfigPath;
        if (is_array($localOverride)) {
            $databaseConfig = array_replace_recursive($databaseConfig, $localOverride);
        }
    }

    $default = (string)($databaseConfig['default'] ?? 'sqlite');
    $connections = is_array($databaseConfig['connections'] ?? null) ? $databaseConfig['connections'] : [];
    $connection = is_array($connections[$default] ?? null) ? $connections[$default] : [];

    $driver = strtolower((string)($connection['driver'] ?? 'sqlite'));

    try {
        if ($driver === 'sqlite') {
            $databaseFile = (string)($connection['database'] ?? '');
            if ($databaseFile === '') {
                $storageBase = (string)(getenv('CRM_STORAGE_BASE') ?: dirname($apiBaseDir) . '/../storage_api');
                $databaseFile = rtrim($storageBase, '/\\') . '/temp/crm.sqlite';
            }
            if (!is_file($databaseFile)) {
                return false;
            }
            $pdo = new PDO('sqlite:' . $databaseFile);
        } elseif ($driver === 'mysql') {
            $pdo = new PDO(
                sprintf(
                    'mysql:host=%s;port=%d;dbname=%s;charset=%s',
                    (string)($connection['host'] ?? '127.0.0.1'),
                    (int)($connection['port'] ?? 3306),
                    (string)($connection['database'] ?? ''),
                    (string)($connection['charset'] ?? 'utf8mb4')
                ),
                (string)($connection['username'] ?? ''),
                (string)($connection['password'] ?? '')
            );
        } elseif ($driver === 'pgsql') {
            $pdo = new PDO(
                sprintf(
                    'pgsql:host=%s;port=%d;dbname=%s',
                    (string)($connection['host'] ?? '127.0.0.1'),
                    (int)($connection['port'] ?? 5432),
                    (string)($connection['database'] ?? '')
                ),
                (string)($connection['username'] ?? ''),
                (string)($connection['password'] ?? '')
            );
        } else {
            return false;
        }

        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $tokenHash = hash('sha256', $sessionToken);
        $stmt = $pdo->prepare(
            'SELECT us.id
              FROM user_sessions us
              INNER JOIN users u ON u.id = us.user_id
              WHERE us.token_hash = :token_hash
                AND us.revoked_at IS NULL
                AND us.expires_at > :now
                AND u.is_active = 1
                AND u.deleted_at IS NULL
              LIMIT 1'
        );
        if ($stmt === false) {
            return false;
        }
        $stmt->execute([
            'token_hash' => $tokenHash,
            'now' => gmdate('Y-m-d H:i:s'),
        ]);

        return (bool)$stmt->fetchColumn();
    } catch (Throwable) {
        return false;
    }
}

function crmWebApiCheckPermission(string $sessionToken, string $permission, string $webBaseDir): bool
{
    $sessionToken = trim($sessionToken);
    if ($sessionToken === '' || strlen($sessionToken) > 4096) {
        return false;
    }

    $apiBaseDir = dirname($webBaseDir) . '/api';
    $apiAutoloader = $apiBaseDir . '/system/library/support/Autoloader.php';
    if (!is_file($apiAutoloader)) {
        return false;
    }

    require_once $apiAutoloader;

    static $apiAutoloaderRegistered2 = false;
    if (!$apiAutoloaderRegistered2) {
        $loader = new Api\System\Library\Support\Autoloader($apiBaseDir);
        $loader->register();
        $apiAutoloaderRegistered2 = true;
    }

    if (class_exists(Api\System\Library\Support\EnvLoader::class)) {
        Api\System\Library\Support\EnvLoader::loadFiles([
            dirname($apiBaseDir) . '/.env',
            $apiBaseDir . '/.env',
            dirname($apiBaseDir) . '/.env.local',
            $apiBaseDir . '/.env.local',
        ]);
    }

    $databaseConfigPath = $apiBaseDir . '/config/database.php';
    if (!is_file($databaseConfigPath)) {
        return false;
    }

    $databaseConfig = require $databaseConfigPath;
    if (!is_array($databaseConfig)) {
        return false;
    }

    $localDbConfigPath = $apiBaseDir . '/config/database.local.php';
    if (is_file($localDbConfigPath)) {
        $localOverride = require $localDbConfigPath;
        if (is_array($localOverride)) {
            $databaseConfig = array_replace_recursive($databaseConfig, $localOverride);
        }
    }

    $default = (string)($databaseConfig['default'] ?? 'sqlite');
    $connections = is_array($databaseConfig['connections'] ?? null) ? $databaseConfig['connections'] : [];
    $connection = is_array($connections[$default] ?? null) ? $connections[$default] : [];

    $driver = strtolower((string)($connection['driver'] ?? 'sqlite'));

    try {
        if ($driver === 'sqlite') {
            $databaseFile = (string)($connection['database'] ?? '');
            if ($databaseFile === '') {
                $storageBase = (string)(getenv('CRM_STORAGE_BASE') ?: dirname($apiBaseDir) . '/../storage_api');
                $databaseFile = rtrim($storageBase, '/\\') . '/temp/crm.sqlite';
            }
            if (!is_file($databaseFile)) {
                return false;
            }
            $pdo = new PDO('sqlite:' . $databaseFile);
        } elseif ($driver === 'mysql') {
            $pdo = new PDO(
                sprintf(
                    'mysql:host=%s;port=%d;dbname=%s;charset=%s',
                    (string)($connection['host'] ?? '127.0.0.1'),
                    (int)($connection['port'] ?? 3306),
                    (string)($connection['database'] ?? ''),
                    (string)($connection['charset'] ?? 'utf8mb4')
                ),
                (string)($connection['username'] ?? ''),
                (string)($connection['password'] ?? '')
            );
        } elseif ($driver === 'pgsql') {
            $pdo = new PDO(
                sprintf(
                    'pgsql:host=%s;port=%d;dbname=%s',
                    (string)($connection['host'] ?? '127.0.0.1'),
                    (int)($connection['port'] ?? 5432),
                    (string)($connection['database'] ?? '')
                ),
                (string)($connection['username'] ?? ''),
                (string)($connection['password'] ?? '')
            );
        } else {
            return false;
        }

        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $tokenHash = hash('sha256', $sessionToken);

        $sessionStmt = $pdo->prepare(
            'SELECT u.is_root
              FROM user_sessions us
              INNER JOIN users u ON u.id = us.user_id
              WHERE us.token_hash = :token_hash
                AND us.revoked_at IS NULL
                AND us.expires_at > :now
                AND u.is_active = 1
                AND u.deleted_at IS NULL
              LIMIT 1'
        );
        if ($sessionStmt === false) {
            return false;
        }
        $sessionStmt->execute([
            'token_hash' => $tokenHash,
            'now' => gmdate('Y-m-d H:i:s'),
        ]);

        if ((bool)$sessionStmt->fetchColumn()) {
            return true;
        }

        $stmt = $pdo->prepare(
            'SELECT COUNT(*) > 0
              FROM user_sessions us
              INNER JOIN users u ON u.id = us.user_id
              INNER JOIN user_roles ur ON ur.user_id = u.id
              INNER JOIN roles r ON r.id = ur.role_id
              INNER JOIN role_permissions rp ON rp.role_id = r.id
              INNER JOIN permissions p ON p.id = rp.permission_id
              WHERE us.token_hash = :token_hash
                AND us.revoked_at IS NULL
                AND us.expires_at > :now
                AND u.is_active = 1
                AND u.deleted_at IS NULL
                AND p.code = :permission
              LIMIT 1'
        );
        if ($stmt === false) {
            return false;
        }
        $stmt->execute([
            'token_hash' => $tokenHash,
            'permission' => $permission,
            'now' => gmdate('Y-m-d H:i:s'),
        ]);

        return (bool)$stmt->fetchColumn();
    } catch (Throwable) {
        return false;
    }
}

function crmWebInitModuleSystem(string $webBaseDir, Web\System\Core\Router $router): void
{
    $projectRoot = dirname($webBaseDir);

    $apiAutoloaderFile = $projectRoot . '/api/system/library/support/Autoloader.php';
    if (!is_file($apiAutoloaderFile)) {
        return;
    }
    require_once $apiAutoloaderFile;

    if (!class_exists(\Api\System\Library\Support\Autoloader::class)) {
        return;
    }

    $apiAutoloader = new \Api\System\Library\Support\Autoloader($projectRoot . '/api');
    $apiAutoloader->register();

    if (class_exists(\Api\System\Library\Support\EnvLoader::class)) {
        \Api\System\Library\Support\EnvLoader::loadFiles([
            $projectRoot . '/.env',
            $projectRoot . '/api/.env',
            $projectRoot . '/.env.local',
        ]);
    }

    if (!class_exists(\Api\System\Library\Module\PluginManager::class)) {
        return;
    }

    $pluginManager = new \Api\System\Library\Module\PluginManager($projectRoot);
    $pluginManager->discover();

    $moduleAutoloader = new \Api\System\Library\Module\ModuleAutoloader($projectRoot);
    $moduleAutoloader->register();

    $discovered = $pluginManager->getDiscovered();
    foreach ($discovered as $name => $manifest) {
        $pluginManager->load($name);
        $moduleAutoloader->registerModule($manifest->name, $manifest->vendor);
    }

    $webHookManager = new Web\System\Hook\HookManager();
    Web\System\Core\Controller::setWebHookManager($webHookManager);

    $assetManager = new Web\System\Module\ModuleAssetManager($pluginManager, $projectRoot);
    $assetManager->collect();

    Web\System\Core\Controller::setModuleAssets(
        $assetManager->getCssFiles(),
        $assetManager->getJsFiles(),
        $assetManager->getJsRoutes()
    );

    $active = $pluginManager->getActive();
    foreach ($active as $name => $manifest) {
        if ($manifest->webRoutes !== null) {
            $moduleDir = $pluginManager->getModulesDir() . '/' . $manifest->name;
            $routeFile = $moduleDir . '/' . $manifest->webRoutes;
            if (is_file($routeFile)) {
                $moduleRoutes = require $routeFile;
                if (is_array($moduleRoutes) && $moduleRoutes !== []) {
                    $router->addRoutes($moduleRoutes);
                }
            }
        }
    }
}

/**
 * Server-side route guard for protected pages.
 * Web remains an MPA shell; API auth/RBAC stays the source of truth for data access.
 */
$publicRoutes = [
    'login',
    'password-reset-request',
    'password-reset-confirm',
    'invitation-accept',
];

$isPublic = in_array($route, $publicRoutes, true)
    || str_ends_with($route, '.css')
    || str_ends_with($route, '.js')
    || str_ends_with($route, '.svg')
    || str_ends_with($route, '.png')
    || str_ends_with($route, '.ico')
    || str_ends_with($route, '.woff2')
    || str_ends_with($route, '.woff')
    || str_ends_with($route, '.ttf')
    || str_ends_with($route, '.map');

// Server-side auth check for protected pages
if (!$isPublic) {
    $sessionCookieName = trim((string)(getenv('CRM_API_SESSION_COOKIE') ?: 'crm_api_session'));
    $sessionToken = trim((string)($_COOKIE[$sessionCookieName] ?? ''));

    $isAuthenticated = false;
    if ($sessionToken !== '') {
        $isAuthenticated = crmWebApiSessionCookieIsValid($sessionToken, $baseDir);
    }

    if (!$isAuthenticated) {
        $redirectRoute = 'dashboard';
        if (isset($routes[$route]) && $route !== 'login') {
            $redirectRoute = $route;
        }

        $loginEntry = trim((string)($_SERVER['SCRIPT_NAME'] ?? '/web/index.php'));
        if ($loginEntry === '') {
            $loginEntry = '/web/index.php';
        }
        header('Location: ' . $loginEntry . '?route=login&redirect=' . rawurlencode($redirectRoute), true, 302);
        exit;
    }
}

// Additional permission check for protected routes
if ($route === 'admin-ai') {
    $sessionCookieName = trim((string)(getenv('CRM_API_SESSION_COOKIE') ?: 'crm_api_session'));
    $sessionToken = trim((string)($_COOKIE[$sessionCookieName] ?? ''));
    $hasPermission = crmWebApiCheckPermission($sessionToken, 'ai.admin', $baseDir);
    if (!$hasPermission) {
        http_response_code(403);
        echo '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><title>403 Forbidden</title></head><body><h1>403 Forbidden</h1><p>You do not have permission to access this page.</p></body></html>';
        exit;
    }
}

$router = new Web\System\Core\Router($routes, $baseDir);

crmWebInitModuleSystem($baseDir, $router);

$router->dispatch($route);
