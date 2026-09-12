<?php
declare(strict_types=1);

namespace Api\System\Library\Database;

use Api\System\Library\Config;
use PDO;

final class ConnectionManager
{
    /** Idle seconds granted to our own MySQL session when nothing is configured. */
    public const DEFAULT_MYSQL_IDLE_TIMEOUT = 600;

    public const MIN_MYSQL_IDLE_TIMEOUT = 60;

    /** MySQL rejects larger session values on most builds (max 8h). */
    public const MAX_MYSQL_IDLE_TIMEOUT = 28800;

    private ?PDO $pdo = null;

    public function __construct(private readonly Config $config)
    {
    }

    /**
     * Drop the cached handle and open a brand new connection. The cache is what
     * makes `connect()` cheap; that same cache keeps handing out a handle the
     * MySQL server has already closed (see `isLostConnection()`), so recovery
     * must replace it explicitly instead of re-reading the dead one.
     */
    public function reconnect(): PDO
    {
        $this->pdo = null;

        return $this->connect();
    }

    /**
     * Distinguish "the server closed this connection" from a real query error.
     * MySQL answers a write on a connection it already dropped with driver codes
     * 2006 (server has gone away) / 2013 (lost connection during query) / 2055
     * (lost connection to server), surfaced as a PDOException with SQLSTATE
     * HY000. Long-running work (AI completions, imports, exports) is exactly the
     * case where the server can idle out a connection mid-request, so callers
     * can treat this as transient and reconnect instead of failing.
     */
    public static function isLostConnection(\Throwable $e): bool
    {
        if ($e instanceof \PDOException) {
            $driverCode = (int)($e->errorInfo[1] ?? 0);
            if (in_array($driverCode, [2006, 2013, 2055], true)) {
                return true;
            }
        }

        $message = strtolower($e->getMessage());
        foreach (['gone away', 'lost connection', 'broken pipe', 'no connection to the server'] as $needle) {
            if (str_contains($message, $needle)) {
                return true;
            }
        }

        return false;
    }

    public function connect(?array $override = null): PDO
    {
        if ($override === null && $this->pdo instanceof PDO) {
            return $this->pdo;
        }

        $db = $override ?? $this->resolvedDatabaseConfig();
        $driver = (string)($db['driver'] ?? 'sqlite');

        [$dsn, $user, $pass, $options] = $this->buildDsn($driver, $db);

        $pdo = new PDO($dsn, $user, $pass, $options);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

        if ($driver === 'mysql') {
            $this->raiseIdleTimeout($pdo, $db);
        }

        if ($override === null) {
            $this->pdo = $pdo;
        }

        return $pdo;
    }

    /**
     * A long call (an AI completion, an import, an export) keeps the PHP worker
     * busy while MySQL sees an idle client, and the default `wait_timeout` of 60s
     * on plenty of shared hosts is shorter than those calls: the server hangs up
     * on a connection the request is still using, and the next query dies with
     * "MySQL server has gone away". The session value can be raised on our own
     * connection without any special privilege, so do it here; `wait_timeout` in
     * the connection config overrides the default. Hosts that refuse the hint are
     * ignored on purpose - `reconnect()` still recovers such a handle.
     *
     * @param array<string,mixed> $db
     */
    private function raiseIdleTimeout(PDO $pdo, array $db): void
    {
        $seconds = self::mysqlIdleTimeout($db);

        try {
            $pdo->exec('SET SESSION wait_timeout = ' . $seconds);
        } catch (\Throwable) {
            // Best effort: a locked-down host must never break connect().
        }
    }

    /**
     * Clamp the configured idle timeout to a value MySQL accepts.
     *
     * @param array<string,mixed> $db
     */
    public static function mysqlIdleTimeout(array $db): int
    {
        $seconds = (int)($db['wait_timeout'] ?? self::DEFAULT_MYSQL_IDLE_TIMEOUT);

        return max(self::MIN_MYSQL_IDLE_TIMEOUT, min(self::MAX_MYSQL_IDLE_TIMEOUT, $seconds));
    }

    /** @return array<string,mixed> */
    public function resolvedDatabaseConfig(): array
    {
        $base = $this->config->get('database', []);
        if (!is_array($base)) {
            $base = [];
        }

        $localPath = $this->config->get('install.config_file', '');
        $local = [];
        if (is_string($localPath) && $localPath !== '' && is_file($localPath)) {
            $localLoaded = require $localPath;
            if (is_array($localLoaded) && !$this->shouldIgnoreLocalConfig($localLoaded)) {
                $local = $localLoaded;
            }
        }

        $cfg = array_replace_recursive($base, $local);
        $default = (string)($cfg['default'] ?? 'sqlite');
        $conn = $cfg['connections'][$default] ?? [];

        return is_array($conn) ? $conn : [];
    }

    /** @param array<string,mixed> $db */
    private function buildDsn(string $driver, array $db): array
    {
        $options = [
            PDO::ATTR_EMULATE_PREPARES => false,
        ];

        if ($driver === 'mysql') {
            // PHP 8.5+ deprecates PDO::MYSQL_ATTR_INIT_COMMAND in favor of Pdo\Mysql::ATTR_INIT_COMMAND.
            // The constant value is 1002; we resolve it via the appropriate class to keep PHP 8.1+ compatibility
            // without triggering deprecation notices on 8.5+.
            // Defensive guard: define the old constant if missing (edge case for custom PHP builds
            // where pdo_mysql may not define it despite the driver being configured for mysql).
            if (!defined('PDO::MYSQL_ATTR_INIT_COMMAND')) {
                define('PDO::MYSQL_ATTR_INIT_COMMAND', 1002);
            }
            $initCommandAttr = PHP_VERSION_ID >= 80500
                ? \Pdo\Mysql::ATTR_INIT_COMMAND
                : PDO::MYSQL_ATTR_INIT_COMMAND;
            $options[$initCommandAttr] = 'SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci';
        }

        return match ($driver) {
            'mysql' => [
                sprintf(
                    'mysql:host=%s;port=%d;dbname=%s;charset=%s',
                    (string)($db['host'] ?? '127.0.0.1'),
                    (int)($db['port'] ?? 3306),
                    (string)($db['database'] ?? ''),
                    (string)($db['charset'] ?? 'utf8mb4')
                ),
                (string)($db['username'] ?? ''),
                (string)($db['password'] ?? ''),
                $options,
            ],
            'pgsql' => [
                sprintf(
                    'pgsql:host=%s;port=%d;dbname=%s',
                    (string)($db['host'] ?? '127.0.0.1'),
                    (int)($db['port'] ?? 5432),
                    (string)($db['database'] ?? '')
                ),
                (string)($db['username'] ?? ''),
                (string)($db['password'] ?? ''),
                $options,
            ],
            'sqlsrv' => [
                sprintf(
                    'sqlsrv:Server=%s,%d;Database=%s',
                    (string)($db['host'] ?? '127.0.0.1'),
                    (int)($db['port'] ?? 1433),
                    (string)($db['database'] ?? '')
                ),
                (string)($db['username'] ?? ''),
                (string)($db['password'] ?? ''),
                $options,
            ],
            default => (function () use ($db, $options): array {
                $file = (string)($db['database'] ?? '');
                if ($file === '') {
                    $file = dirname(__DIR__, 4) . '/storage_api/temp/crm.sqlite';
                }
                $dir = dirname($file);
                if (!is_dir($dir)) {
                    @mkdir($dir, 0775, true);
                }

                return [
                    'sqlite:' . $file,
                    '',
                    '',
                    $options,
                ];
            })(),
        };
    }

    /** @param array<string,mixed> $config */
    private function shouldIgnoreLocalConfig(array $config): bool
    {
        if (!$this->isProductionEnvironment()) {
            return false;
        }

        foreach ($this->flattenConfigValues($config) as $value) {
            $normalized = str_replace('\\', '/', (string)$value);
            if (str_contains($normalized, '/storage_test_runtime/') || str_starts_with($normalized, '/Users/')) {
                return true;
            }
            if (str_ends_with($normalized, '.sqlite') && !file_exists($normalized)) {
                return true;
            }
        }

        return false;
    }

    private function isProductionEnvironment(): bool
    {
        $env = strtolower(trim((string)$this->config->get('default.app.env', getenv('APP_ENV') ?: 'prod')));
        return in_array($env, ['prod', 'production'], true);
    }

    /**
     * @param mixed $value
     * @return array<int,scalar>
     */
    private function flattenConfigValues(mixed $value): array
    {
        if (!is_array($value)) {
            return is_scalar($value) ? [$value] : [];
        }

        $result = [];
        foreach ($value as $item) {
            foreach ($this->flattenConfigValues($item) as $flattened) {
                $result[] = $flattened;
            }
        }

        return $result;
    }
}
