<?php
declare(strict_types=1);

ini_set('max_execution_time', '0');
ini_set('memory_limit', '512M');
set_time_limit(0);

$baseDir = __DIR__;
require_once $baseDir . '/system/I18n/EarlyResponse.php';

/**
 * Baseline web security headers.
 * CSP is enforced; set CRM_WEB_CSP_REPORT_ONLY=1 only for emergency rollout diagnostics.
 *
 * This block intentionally runs before any redirect, so that the unauthenticated
 * entry point has the same browser protections as every rendered web page.
 */
if (!headers_sent()) {
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: geolocation=(), microphone=(), camera=()');
    header('Cross-Origin-Opener-Policy: same-origin-allow-popups');
    header('Cross-Origin-Resource-Policy: same-origin');

    // SEC-007: Per-request CSP nonce and style-src without 'unsafe-inline'.
    // The nonce is generated and exposed to templates via $GLOBALS['crm_csp_nonce']
    // (Controller::render() copies it to $data['csp_nonce']).
    //
    // style-src: 'unsafe-inline' is removed — <style> tags require nonce.
    // style-src-attr: retains 'unsafe-inline' for style="" attributes (CSP Level 3).
    // This preserves backward compatibility with inline style attributes used by
    // Bootstrap components (progress bars, colors, widths) while hardening against
    // injected <style> blocks. Older browsers without style-src-attr support fall
    // back to style-src without 'unsafe-inline', blocking style="" attributes.
    //
    // To migrate fully: convert style="" attributes to CSS classes and remove
    // style-src-attr 'unsafe-inline'.
    $cspNonce = base64_encode(random_bytes(16));
    $GLOBALS['crm_csp_nonce'] = $cspNonce;

    $csp = implode('; ', [
        "default-src 'self'",
        "base-uri 'self'",
        "object-src 'none'",
        "frame-ancestors 'none'",
        "img-src 'self' data: blob: https:",
        "font-src 'self' data: https:",
        "style-src 'self' https: 'nonce-{$cspNonce}'",
        "style-src-attr 'unsafe-inline'",
        "script-src 'self' 'unsafe-inline' https:",
        "connect-src 'self' https: wss:",
        "worker-src 'self' blob:",
        "report-uri /api/index.php?route=api/v1/telemetry/csp-report",
    ]);
    header('Content-Security-Policy: ' . $csp);
}

$maintenanceFlag = dirname(__DIR__) . '/storage_api/maintenance.flag';
if (is_file($maintenanceFlag)) {
    // Guard 3 (maintenance hold): a failed update (e.g. partial DB migration)
    // leaves maintenance ON so the CRM is not served in a broken state. The
    // admin-updates page and its core/updates API must stay reachable so the
    // admin can roll back or retry. Everything else stays behind maintenance.
    $maintenanceRoute = trim((string)($_GET['route'] ?? ''), '/');
    // 'login' must stay reachable during held maintenance: an update that
    // failed after mutating files/DB leaves maintenance ON, and if the admin's
    // session has expired they could otherwise never log in again to roll
    // back or retry from the admin-updates page (recovery would be locked to
    // the one-time rescue key). Login is rate-limited like any other attempt.
    $maintenanceRecoveryAllowed = $maintenanceRoute === 'admin-updates'
        || $maintenanceRoute === 'login'
        || str_starts_with($maintenanceRoute, 'api/v1/core/updates');
    if (!$maintenanceRecoveryAllowed) {
        http_response_code(503);
        header('Content-Type: text/html; charset=utf-8');
        echo \Web\System\I18n\EarlyResponse::maintenancePage($baseDir);
        exit;
    }
}

// Redirect to installer when .env is missing
$scriptFile = basename($_SERVER['SCRIPT_NAME'] ?? 'index.php');
$rootEnvFile = dirname(__DIR__) . '/.env';
$rootEnvLocal = dirname(__DIR__) . '/.env.local';
$envFile = dirname(__DIR__) . '/api/.env';
$envLocal = dirname(__DIR__) . '/api/.env.local';
$envIsSet = (getenv('DB_CONNECTION') || getenv('CRM_DB_DRIVER') || getenv('CRM_STORAGE_BASE'));
$hasConfig = $envIsSet || is_file($rootEnvFile) || is_file($rootEnvLocal) || is_file($envFile) || is_file($envLocal);
if ($scriptFile !== 'install.php' && !$hasConfig) {
    $webDir = rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? '/web'), '/');
    header('Location: ' . $webDir . '/install.php', true, 302);
    exit;
}

// SEC-011: Load global template helpers (e() html-escape) before any controller
// or template runs, so plain <?php open tags in templates can call e($x) without
// pulling in the class autoloader. The function_exists() guard inside the
// helpers file is idempotent.
require_once $baseDir . '/system/Core/helpers.php';

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
$isPathDerived = false;
if ($route === null || $route === '') {
    $path = parse_url($requestUri, PHP_URL_PATH) ?? '/';
    if ($basePath !== '' && str_starts_with($path, $basePath)) {
        $path = substr($path, strlen($basePath));
    }
    $route = trim($path, '/');
    $isPathDerived = true;
}

$route = trim((string)$route, '/');
// Canonical entry-point redirect: a path-derived request that resolves to
// 'index.php' (e.g. the root entry redirects to /web/index.php, or the user
// types /web/index.php by hand) is redirected to the clean base path
// (/web/), preserving any query string. Explicit ?route=index.php stays
// unknown on purpose (still 404). Other path-derived '.php' files (e.g.
// install.php) are served directly by the web server and never reach the
// router; if one does, strip the extension rather than 404 on the raw name.
if ($route === 'index.php' && $isPathDerived) {
    $query = $_SERVER['QUERY_STRING'] ?? '';
    $target = $basePath !== '' ? $basePath . '/' : '/';
    if ($query !== '') {
        $target .= '?' . $query;
    }
    header('Location: ' . $target, true, 302);
    exit;
}
if ($route !== '' && $isPathDerived && str_ends_with($route, '.php')) {
    $route = substr($route, 0, -4);
}
if ($route === '') {
    $route = 'dashboard';
}

// SEC-008: Hide install API endpoints after setup.
// Return 404 instead of leaking that the installer endpoint exists.
// The browser installer (web/install.php) is unaffected — it is served
// directly by the web server as a static file path.
if (str_starts_with($route, 'install/')) {
    http_response_code(404);
    exit;
}

// Handle API routes
if (str_starts_with($route, 'api/')) {
    // Rate-limit auth-sensitive endpoints before proxying to the API.
    // This ensures brute-force protection even when the request enters
    // through the web entry point (shared-hosting / nginx scenarios).
    $rateLimitedApiRoutes = [
        'api/v1/auth/login',
        'api/v1/security/password-reset',
        'api/v1/security/password-reset/request',
        'api/v1/security/password-reset/confirm',
        'api/v1/security/invitations/accept',
    ];
    $rateLimitCheck = in_array($route, $rateLimitedApiRoutes, true)
        ? webRateLimitCheck($route)
        : null;
    if ($rateLimitCheck !== null && $rateLimitCheck['blocked'] === true) {
        http_response_code(429);
        header('Content-Type: application/json; charset=utf-8');
        header('Retry-After: ' . $rateLimitCheck['retry_after']);
        echo json_encode([
            'success' => false,
            'code' => 'RATE_LIMITED',
            'message' => 'Too many requests. Please try again later.',
            'retry_after' => $rateLimitCheck['retry_after'],
        ], JSON_UNESCAPED_SLASHES);
        exit;
    }

    $_SERVER['REQUEST_URI'] = '/' . $route;
    $_GET['route'] = $route;
    include dirname(__DIR__) . '/api/index.php';
    exit;
}

/**
 * Simple file-based rate limiter for the web entry point.
 * Uses the same storage directory as AuthService's rate limiter.
 */
function webRateLimitCheck(string $route): ?array
{
    $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    $dir = dirname(__DIR__) . '/storage_api/cache/rate_limits';
    if (!is_dir($dir)) {
        @mkdir($dir, 0700, true);
    }
    $fileName = $dir . '/crm_web_' . hash('sha256', $route . ':' . $ip) . '.counter';

    $now = time();
    $maxAttempts = 5;
    $windowSeconds = 300;
    $lockSeconds = 900;

    $fp = @fopen($fileName, 'c+');
    if (!$fp) {
        return null;
    }
    if (!flock($fp, LOCK_EX)) {
        fclose($fp);
        return null;
    }

    $raw = stream_get_contents($fp);
    $data = ($raw !== false && $raw !== '') ? @json_decode($raw, true) : null;
    if (!is_array($data)) {
        $data = ['count' => 0, 'window_start' => 0, 'blocked_until' => 0];
    }
    $data['count'] = (int)($data['count'] ?? 0);
    $data['window_start'] = (int)($data['window_start'] ?? 0);
    $data['blocked_until'] = (int)($data['blocked_until'] ?? 0);

    if ($data['blocked_until'] > $now) {
        flock($fp, LOCK_UN);
        fclose($fp);
        return ['blocked' => true, 'retry_after' => $data['blocked_until'] - $now];
    }

    if (($now - $data['window_start']) > $windowSeconds) {
        $data = ['count' => 1, 'window_start' => $now, 'blocked_until' => 0];
    } else {
        $data['count']++;
        if ($data['count'] >= $maxAttempts) {
            $data['blocked_until'] = $now + $lockSeconds;
        }
    }
    ftruncate($fp, 0);
    rewind($fp);
    fwrite($fp, json_encode($data, JSON_UNESCAPED_SLASHES));

    flock($fp, LOCK_UN);
    fclose($fp);

    if ($data['blocked_until'] > $now) {
        return ['blocked' => true, 'retry_after' => $data['blocked_until'] - $now];
    }
    return ['blocked' => false, 'retry_after' => 0];
}

/**
 * Create a PDO connection for the web entry point using the API database config.
 * Extracted to eliminate ~40 lines of duplicated connection logic between
 * crmWebApiSessionCookieIsValid and crmWebApiCheckAnyPermission.
 */
function crmWebApiDbConnect(string $webBaseDir): ?PDO
{
    $apiBaseDir = dirname($webBaseDir) . '/api';
    $apiAutoloader = $apiBaseDir . '/system/library/support/Autoloader.php';
    if (!is_file($apiAutoloader)) {
        return null;
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
        return null;
    }

    $databaseConfig = require $databaseConfigPath;
    if (!is_array($databaseConfig)) {
        return null;
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
                return null;
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
            return null;
        }

        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        return $pdo;
    } catch (\Throwable $e) {
        error_log('[index::crmWebApiDbConnect] ' . $e->getMessage());
        return null;
    }
}

function crmWebApiSessionCookieIsValid(string $sessionToken, string $webBaseDir): bool
{
    $sessionToken = trim($sessionToken);
    if ($sessionToken === '' || strlen($sessionToken) > 4096) {
        return false;
    }

    $pdo = crmWebApiDbConnect($webBaseDir);
    if ($pdo === null) {
        return false;
    }

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
}

function crmWebApiCheckAnyPermission(string $sessionToken, array $permissions, string $webBaseDir): bool
{
    $sessionToken = trim($sessionToken);
    $permissions = array_values(array_unique(array_filter(array_map(
        static fn($permission): string => trim((string)$permission),
        $permissions
    ), static fn(string $permission): bool => $permission !== '')));

    if ($sessionToken === '' || strlen($sessionToken) > 4096 || $permissions === []) {
        return false;
    }

    $pdo = crmWebApiDbConnect($webBaseDir);
    if ($pdo === null) {
        return false;
    }

    $tokenHash = hash('sha256', $sessionToken);
    $now = gmdate('Y-m-d H:i:s');

    // Root role mirrors AuthService root detection: users with the super_admin
    // role bypass all permission checks even when the users is_root flag is
    // stale or missing (self-hosted installs may carry both). Other admin-like
    // roles must map their permissions through role_permissions — the same
    // source the API auth layer uses (see the permission query below).
    $rootRoles = ['super_admin'];
    $rootRolePlaceholders = [];
    foreach ($rootRoles as $i => $rootRole) {
        $rootRolePlaceholders[':root_role' . $i] = $rootRole;
    }

    // Root check: root user (flag or root role) bypasses all permission checks.
    // All placeholders are named: PDO rejects a mix of named and positional
    // parameters in a single statement (HY093).
    $sessionStmt = $pdo->prepare(
        'SELECT (u.is_root = 1 OR EXISTS (
                  SELECT 1
                    FROM user_roles ur
                    INNER JOIN roles r ON r.id = ur.role_id
                   WHERE ur.user_id = u.id
                     AND r.code IN (' . implode(',', array_keys($rootRolePlaceholders)) . ')
                )) AS is_root
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
    $sessionStmt->execute(array_merge(
        ['token_hash' => $tokenHash, 'now' => $now],
        $rootRolePlaceholders
    ));

    if ((bool)$sessionStmt->fetchColumn()) {
        return true;
    }

    // Any-listed permission grants access (same semantics as the API
    // withPermissionAny / MenuController gating). Named placeholders only.
    $permissionPlaceholders = [];
    foreach ($permissions as $i => $permission) {
        $permissionPlaceholders[':perm' . $i] = $permission;
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
            AND p.code IN (' . implode(',', array_keys($permissionPlaceholders)) . ')
          LIMIT 1'
    );
    if ($stmt === false) {
        return false;
    }

    $stmt->execute(array_merge(
        ['token_hash' => $tokenHash, 'now' => $now],
        $permissionPlaceholders
    ));

    return (bool)$stmt->fetchColumn();
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

// Additional permission check for protected routes.
// Admin/privileged pages mirror their API routes' required_permissions so that
// a user without the underlying rights gets a 403 at the page shell instead of
// an empty page full of API errors. Any-listed permission grants access (like
// the API withPermissionAny / MenuController gating).
$adminRoutePermissions = [
    // Admin hub: it renders the users/roles summary, KPI widgets (logs.view)
    // and API-keys KPI (api_client.view), so any of those grants the shell.
    'admin' => ['user.view', 'role.view', 'logs.view', 'api_client.view'],
    'admin-users' => ['user.view'],
    'admin-roles' => ['role.view'],
    'admin-statuses' => ['task.manage'],
    'admin-priorities' => ['task.manage'],
    'admin-tags' => ['task.manage'],
    'admin-logs' => ['logs.view'],
    'admin-api-clients' => ['api_client.view'],
    'admin-settings' => ['settings.manage'],
    // Jobs queue aggregates import/export/AI-job lists plus ops endpoints.
    'admin-jobs' => ['logs.view', 'import.manage', 'export.manage', 'ai.view_cron_results'],
    'admin-ai' => ['ai.admin'],
    'admin-workflow' => ['settings.manage'],
    'admin-sla' => ['settings.manage'],
    'admin-custom-fields' => ['settings.manage'],
    'admin-calendar' => ['settings.manage'],
    // Knowledge admin pages map to the knowledge.admin permission seeded by
    // KnowledgeBaseMigration (see KnowledgeController::actorCanManagePermissions).
    'admin-knowledge' => ['knowledge.admin'],
    'admin-templates' => ['task.manage', 'project.manage'],
    'admin-webhooks' => ['webhook.manage'],
    // Recurring task templates live in the admin hub; their API is task-scoped.
    'recurring' => ['task.manage'],
    'admin-modules' => ['settings.manage'],
    'admin-modules-install' => ['settings.manage'],
    'admin-module-detail' => ['settings.manage'],
    'admin-updates' => ['settings.manage'],
    // Estimate sets/options are project-scoped: /api/v1/estimate-sets etc.
    // require project.manage (there is no estimate.* permission code).
    'admin-estimates' => ['project.manage'],
    'organizations' => ['organization.manage'],
    'recycle-bin' => ['recycle_bin.manage'],
    'approvals' => ['approval.manage'],
    'intake' => ['intake.view'],
    'project-modules' => ['project.manage'],
    // Core features gated by permissions: shell 403s when the API would deny.
    'ideas' => ['idea.view'],
    'chat' => ['chat.use'],
];

if (isset($adminRoutePermissions[$route])) {
    $sessionCookieName = trim((string)(getenv('CRM_API_SESSION_COOKIE') ?: 'crm_api_session'));
    $sessionToken = trim((string)($_COOKIE[$sessionCookieName] ?? ''));
    $hasPermission = crmWebApiCheckAnyPermission($sessionToken, $adminRoutePermissions[$route], $baseDir);
    if (!$hasPermission) {
        http_response_code(403);
        header('Content-Type: text/html; charset=utf-8');
        echo \Web\System\I18n\EarlyResponse::forbiddenPage($baseDir);
        exit;
    }
}

$router = new Web\System\Core\Router($routes, $baseDir);

crmWebInitModuleSystem($baseDir, $router);

$router->dispatch($route);
