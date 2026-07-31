<?php
declare(strict_types=1);

namespace Api\System\Library\Database;

use Api\System\Library\Config;
use PDO;

final class ConnectionManager
{
    private ?PDO $pdo = null;

    public function __construct(private readonly Config $config)
    {
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

        if ($override === null) {
            $this->pdo = $pdo;
        }

        return $pdo;
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
                    $file = dirname(__DIR__, 3) . '/../storage_api/temp/crm.sqlite';
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
            if (str_contains($normalized, '/storage_test_runtime/')) {
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
