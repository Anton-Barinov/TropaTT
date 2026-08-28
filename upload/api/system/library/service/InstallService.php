<?php
declare(strict_types=1);

namespace Api\System\Library\Service;

use Api\Model\Common\UserRepository;
use Api\System\Library\Config;
use Api\System\Library\Database\ConnectionManager;
use Api\System\Library\Database\Migration\MigrationManager;
use Api\System\Library\Database\SchemaManager;
use Api\System\Library\Language\LanguageManager;
use Api\System\Library\Language\TranslatableTrait;
use Api\System\Library\Logger\JsonLogger;
use Api\System\Library\Security\PasswordHasher;
use Api\System\Library\Support\Ulid;
use PDO;
use Throwable;

final class InstallService
{
    use TranslatableTrait;

    public function __construct(
        private readonly Config $config,
        private readonly ConnectionManager $connections,
        private readonly SchemaManager $schema,
        private readonly MigrationManager $migrations,
        private readonly PasswordHasher $hasher,
        private readonly JsonLogger $logger,
        ?LanguageManager $lang = null
    ) {
        $this->lang = $lang ?? new LanguageManager(__DIR__ . '/../../language');
    }

    public function isInstalled(): bool
    {
        $lock = (string)$this->config->get('install.lock_file', '');
        return $lock !== '' && is_file($lock);
    }

    public function status(): array
    {
        return [
            'installed' => $this->isInstalled(),
        ];
    }

    public function checkConnection(array $payload): array
    {
        $db = $this->resolveDbConfig($payload);
        $pdo = $this->connections->connect($db);
        $stmt = $pdo->query('SELECT 1');
        $stmt->fetchColumn();

        $status = $this->migrations->status($pdo, (string)($db['driver'] ?? 'sqlite'));

        return [
            'ok' => true,
            'migration_status' => $status,
        ];
    }

    public function setup(array $payload): array
    {
        if ($this->isInstalled()) {
            return ['ok' => false, 'code' => 'ALREADY_INSTALLED'];
        }

        $db = $this->resolveDbConfig($payload);

        if ($db['driver'] === 'sqlite' && $db['database'] === '') {
            $tempDir = rtrim((string)$this->config->get('default.storage.temp', dirname(__DIR__, 4) . '/storage_api/temp'), '/\\');
            if (!is_dir($tempDir)) {
                @mkdir($tempDir, 0775, true);
            }
            $db['database'] = $tempDir . '/crm.sqlite';
        }

        $adminLogin = trim((string)($payload['root_login'] ?? ''));
        $adminPassword = (string)($payload['root_password'] ?? '');
        $adminToken = trim((string)($payload['root_token'] ?? ''));
        $adminName = trim((string)($payload['root_name'] ?? $this->t('install/messages.default_admin_name')));
        $adminEmail = trim((string)($payload['root_email'] ?? ''));
        $locale = trim((string)($payload['default_language'] ?? 'en-gb'));

        if ($adminLogin === '' || strlen($adminLogin) < 3) {
            return ['ok' => false, 'code' => 'INSTALL_ROOT_LOGIN_REQUIRED'];
        }
        if ($adminPassword === '' || strlen($adminPassword) < 12) {
            return ['ok' => false, 'code' => 'INSTALL_ROOT_PASSWORD_WEAK'];
        }
        if ($adminToken === '' || strlen($adminToken) < 12) {
            return ['ok' => false, 'code' => 'INSTALL_ROOT_TOKEN_WEAK'];
        }
        if ($adminEmail === '' || !filter_var($adminEmail, FILTER_VALIDATE_EMAIL)) {
            return ['ok' => false, 'code' => 'INSTALL_ROOT_EMAIL_INVALID'];
        }

        $pdo = $this->connections->connect($db);
        $driver = (string)$db['driver'];
        $supportsTransactionalSetup = in_array($driver, ['sqlite', 'pgsql'], true);

        try {
            if ($supportsTransactionalSetup) {
                $pdo->beginTransaction();
            }
            $this->migrations->migrateUp($pdo, (string)$db['driver']);

            $now = gmdate('Y-m-d H:i:s');
            $roleId = null;
            $rootRole = $pdo->prepare('SELECT id FROM roles WHERE code = :code LIMIT 1');
            $rootRole->execute(['code' => 'root']);
            $existingRoleId = $rootRole->fetchColumn();
            if ($existingRoleId !== false) {
                $roleId = (int)$existingRoleId;
            } else {
                $rolePublicId = Ulid::generate('rol');
                $roleStmt = $pdo->prepare('INSERT INTO roles (public_id, code, title, is_system, created_at, updated_at) VALUES (:public_id,:code,:title,:is_system,:created_at,:updated_at)');
                $roleStmt->execute([
                    'public_id' => $rolePublicId,
                    'code' => 'root',
                    'title' => $this->t('install/messages.role_root_title', 'Root'),
                    'is_system' => 1,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
                $roleId = (int)$pdo->lastInsertId();
            }

            $users = new UserRepository($pdo);
            $existingRoot = $users->findByLogin($adminLogin);
            if ($existingRoot) {
                $rootUserId = (int)$existingRoot['id'];
                $updateRoot = $pdo->prepare('UPDATE users SET email = :email, password_hash = :password_hash, auth_token_hash = :auth_token_hash, full_name = :full_name, locale = :locale, is_active = 1, is_root = 1, updated_at = :updated_at WHERE id = :id');
                $updateRoot->execute([
                    'id' => $rootUserId,
                    'email' => $adminEmail,
                    'password_hash' => $this->hasher->hash($adminPassword),
                    'auth_token_hash' => hash('sha256', $adminToken),
                    'full_name' => $adminName,
                    'locale' => $locale,
                    'updated_at' => $now,
                ]);
            } else {
                $rootUserId = $users->create([
                    'public_id' => Ulid::generate('usr'),
                    'login' => $adminLogin,
                    'email' => $adminEmail,
                    'password_hash' => $this->hasher->hash($adminPassword),
                    'auth_token_hash' => hash('sha256', $adminToken),
                    'full_name' => $adminName,
                    'locale' => $locale,
                    'is_active' => 1,
                    'is_root' => 1,
                    'created_by_user_id' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            $linkCheck = $pdo->prepare('SELECT id FROM user_roles WHERE user_id = :user_id AND role_id = :role_id LIMIT 1');
            $linkCheck->execute(['user_id' => $rootUserId, 'role_id' => $roleId]);
            if ($linkCheck->fetchColumn() === false) {
                $link = $pdo->prepare('INSERT INTO user_roles (user_id, role_id, created_at) VALUES (:user_id,:role_id,:created_at)');
                $link->execute([
                    'user_id' => $rootUserId,
                    'role_id' => $roleId,
                    'created_at' => $now,
                ]);
            }

            $installState = $pdo->prepare('INSERT INTO install_state (installed_at, version, payload) VALUES (:installed_at,:version,:payload)');
            $installState->execute([
                'installed_at' => $now,
                'version' => 'v1',
                'payload' => json_encode(['root_login' => $adminLogin, 'locale' => $locale], JSON_UNESCAPED_UNICODE),
            ]);

            if ($supportsTransactionalSetup && $pdo->inTransaction()) {
                $pdo->commit();
            }
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }

        $this->writeDbConfig($db);
        $logsDir = $this->writeLoggingConfig();
        $this->writeInstallLock($payload, $logsDir);

        $this->logger->log('install', 'info', 'install_completed', [
            'driver' => $db['driver'],
            'admin_login' => $adminLogin,
            'locale' => $locale,
        ]);

        return ['ok' => true];
    }

    private function resolveDbConfig(array $payload): array
    {
        if (array_key_exists('driver', $payload) || array_key_exists('database', $payload)) {
            return [
                'driver' => (string)($payload['driver'] ?? 'sqlite'),
                'host' => (string)($payload['host'] ?? '127.0.0.1'),
                'port' => (int)($payload['port'] ?? 0),
                'database' => (string)($payload['database'] ?? ''),
                'username' => (string)($payload['username'] ?? ''),
                'password' => (string)($payload['password'] ?? ''),
                'charset' => (string)($payload['charset'] ?? 'utf8mb4'),
                'prefix' => (string)($payload['prefix'] ?? ''),
            ];
        }

        return [
            'driver' => (string)($payload['db_driver'] ?? 'sqlite'),
            'host' => (string)($payload['db_host'] ?? '127.0.0.1'),
            'port' => (int)($payload['db_port'] ?? 0),
            'database' => (string)($payload['db_name'] ?? ''),
            'username' => (string)($payload['db_user'] ?? ''),
            'password' => (string)($payload['db_password'] ?? ''),
            'charset' => (string)($payload['db_charset'] ?? 'utf8mb4'),
            'prefix' => (string)($payload['db_prefix'] ?? ''),
        ];
    }

    private function writeDbConfig(array $db): void
    {
        $path = (string)$this->config->get('install.config_file', '');
        if ($path === '') {
            return;
        }

        $dir = dirname($path);
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }

        $content = "<?php\nreturn " . var_export([
            'default' => $db['driver'],
            'connections' => [
                $db['driver'] => $db,
            ],
        ], true) . ";\n";

        file_put_contents($path, $content);
    }

    private function writeInstallLock(array $payload, string $logsDir): void
    {
        $path = (string)$this->config->get('install.lock_file', '');
        if ($path === '') {
            return;
        }

        $dir = dirname($path);
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }

        file_put_contents($path, json_encode([
            'installed_at' => gmdate('c'),
            'version' => 'v1',
            'default_language' => (string)($payload['default_language'] ?? 'en-gb'),
            'logs_dir' => $logsDir,
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    }

    private function writeLoggingConfig(): string
    {
        $storageBase = rtrim((string)$this->config->get('default.storage.base', dirname(__DIR__, 4) . '/storage_api'), '/\\');
        $logsDir = $storageBase . '/logs_' . bin2hex(random_bytes(8));
        if (!is_dir($logsDir)) {
            @mkdir($logsDir, 0770, true);
        }

        $configPath = trim((string)$this->config->get('install.logging_config_file', ''));
        if ($configPath === '') {
            return $logsDir;
        }

        $config = [
            'channels' => [
                'request' => $logsDir . '/request.log',
                'audit' => $logsDir . '/audit.log',
                'security' => $logsDir . '/security.log',
                'error' => $logsDir . '/error.log',
                'install' => $logsDir . '/install.log',
            ],
        ];
        $dir = dirname($configPath);
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }

        file_put_contents($configPath, "<?php\nreturn " . var_export($config, true) . ";\n");
        return $logsDir;
    }
}
