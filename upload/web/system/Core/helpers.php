<?php
declare(strict_types=1);

/*
 * SEC-011: Global helper functions for web templates.
 *
 * The e() function provides a single, consistent htmlspecialchars() entry
 * point with full quote/encoding safety. Templates should use e($x) for any
 * user-supplied data instead of bare <?= $x ?>, which would render any HTML
 * or JS in user input as live markup.
 *
 * Nullable signature: passing null returns an empty string. This is more
 * template-friendly than the non-nullable install.php helper (templates
 * often pass $data['x'] where x may be unset), at the cost of a small
 * divergence with web/install.php's e() definition. Both helpers use the
 * same flags (ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8').
 *
 * The function_exists() guard makes this file safe to require multiple times
 * and safe to load alongside web/install.php (which defines the same
 * helper unconditionally).
 */

if (!function_exists('e')) {
    function e(?string $value): string
    {
        if ($value === null) {
            return '';
        }
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

if (!function_exists('crmWebApiDbConnect')) {
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
            if (class_exists(\Api\System\Library\Support\AppLog::class)) {
                \Api\System\Library\Support\AppLog::error('[helpers::crmWebApiDbConnect] ' . $e->getMessage());
            }
            return null;
        }
    }
}

