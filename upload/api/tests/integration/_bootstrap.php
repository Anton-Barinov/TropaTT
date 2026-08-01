<?php
declare(strict_types=1);

use Api\System\Library\App;

require_once __DIR__ . '/../../system/library/support/Autoloader.php';

if (trim((string)getenv('APP_ENV')) === '') {
    putenv('APP_ENV=test');
    $_ENV['APP_ENV'] = 'test';
}
if (trim((string)getenv('APP_DEBUG')) === '') {
    putenv('APP_DEBUG=1');
    $_ENV['APP_DEBUG'] = '1';
}

if (trim((string)getenv('CRM_STORAGE_BASE')) === '') {
    $storageBase = dirname(__DIR__, 2) . '/storage_test_runtime';
    putenv('CRM_STORAGE_BASE=' . $storageBase);
    $_ENV['CRM_STORAGE_BASE'] = $storageBase;
}
if (trim((string)getenv('CRM_SQLITE_DATABASE')) === '') {
    $sqlitePath = rtrim((string)getenv('CRM_STORAGE_BASE'), '/\\') . '/temp/crm.sqlite';
    putenv('CRM_SQLITE_DATABASE=' . $sqlitePath);
    $_ENV['CRM_SQLITE_DATABASE'] = $sqlitePath;
}

foreach (['', '/uploads', '/quarantine', '/logs', '/sessions', '/temp', '/cache', '/secrets'] as $suffix) {
    $dir = rtrim((string)getenv('CRM_STORAGE_BASE'), '/\\') . $suffix;
    if (!is_dir($dir)) {
        @mkdir($dir, 0777, true);
    }
}

if (trim((string)getenv('APP_KEY')) === '' && trim((string)getenv('CSRF_SECRET_KEY')) === '') {
    putenv('APP_KEY=crm-integration-test-app-key-2026');
    $_ENV['APP_KEY'] = 'crm-integration-test-app-key-2026';
}
if (trim((string)getenv('CSRF_SECRET_KEY')) === '') {
    putenv('CSRF_SECRET_KEY=crm-integration-test-csrf-secret-2026');
    $_ENV['CSRF_SECRET_KEY'] = 'crm-integration-test-csrf-secret-2026';
}

$autoloader = new Api\System\Library\Support\Autoloader(dirname(__DIR__, 2));
$autoloader->register();

$__crmTestRootPasswordUsed = null;
$__crmTestRootTokenUsed = null;
 $__crmTestRuntimeReady = false;

function assertTrue(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function testRootPasswordUsed(): string
{
    global $__crmTestRootPasswordUsed;

    if (is_string($__crmTestRootPasswordUsed) && $__crmTestRootPasswordUsed !== '') {
        return $__crmTestRootPasswordUsed;
    }

    $env = trim((string)getenv('CRM_TEST_ROOT_PASSWORD'));
    if ($env !== '') {
        return $env;
    }

    return 'RootPass#2026!';
}

/**
 * @param array<string,mixed> $post
 * @param array<string,string> $headers
 * @param array<string,mixed> $files
 * @param array<string,string> $cookies
 * @return array{status:int,payload:array<string,mixed>}
 */
function request(string $method, string $uri, array $post = [], array $headers = [], array $files = [], array $cookies = []): array
{
    $attempts = 4;
    $last = ['status' => 0, 'payload' => []];
    for ($i = 1; $i <= $attempts; $i++) {
        $_GET = [];
        $_POST = $post;
        $_FILES = $files;
        $_COOKIE = $cookies;

        if (str_contains($uri, '?')) {
            [, $query] = explode('?', $uri, 2);
            parse_str($query, $_GET);
        }

        $_SERVER = [
            'REQUEST_METHOD' => strtoupper($method),
            'REQUEST_URI' => $uri,
            'REMOTE_ADDR' => '127.0.0.1',
            'HTTP_USER_AGENT' => 'crm-api-integration-test/1.0',
        ];

        foreach ($headers as $name => $value) {
            $serverKey = 'HTTP_' . strtoupper(str_replace('-', '_', $name));
            $_SERVER[$serverKey] = $value;
        }

        $app = new App(dirname(__DIR__, 2));
        $response = $app->run();
        $last = [
            'status' => $response->status(),
            'payload' => $response->payload(),
        ];

        $code = (string)($last['payload']['code'] ?? '');
        $exceptionList = (array)(($last['payload']['errors'] ?? [])['exception'] ?? []);
        $exceptionText = strtolower(implode(' ', array_map(static fn($v): string => (string)$v, $exceptionList)));
        $isLocked = ((int)$last['status'] === 500)
            && $code === 'INTERNAL_ERROR'
            && str_contains($exceptionText, 'database is locked');

        if (!$isLocked) {
            return $last;
        }

        usleep(200000);
    }

    return $last;
}

/**
 * @return array{token:string,user_public_id:string,csrf_token:string}
 */
function loginRoot(): array
{
    global $__crmTestRootPasswordUsed;
    global $__crmTestRootTokenUsed;
    ensureTestRuntimeReady();

    // Test-runtime helper: clear local auth rate-limit cache to avoid false negatives
    // during long sequential audit runs with many loginRoot() calls.
    $storageBases = [];
    $envStorageBase = trim((string)getenv('CRM_STORAGE_BASE'));
    if ($envStorageBase !== '') {
        $storageBases[] = $envStorageBase;
    }
    $storageBases[] = dirname(__DIR__, 3) . '/../storage_api';
    $storageBases[] = dirname(__DIR__, 3) . '/storage';
    foreach (array_unique($storageBases) as $storageBase) {
        $authRateLimitFile = rtrim($storageBase, '/\\') . '/cache/auth_login_rate_limit.json';
        if (is_file($authRateLimitFile)) {
            @unlink($authRateLimitFile);
        }
    }
    clearTestDatabaseRateLimits();

    $loginName = trim((string)getenv('CRM_TEST_ROOT_LOGIN'));
    if ($loginName === '') {
        $loginName = 'root';
    }

    $passwordCandidates = [];
    $envPassword = trim((string)getenv('CRM_TEST_ROOT_PASSWORD'));
    if ($envPassword !== '') {
        $passwordCandidates[] = $envPassword;
    }
    $passwordCandidates[] = 'RootPass#2026!';
    $passwordCandidates[] = 'TropaRoot#2026!';
    $passwordCandidates = array_values(array_unique(array_filter($passwordCandidates, static fn(string $v): bool => $v !== '')));

    $tokenCandidates = [];
    if (is_string($__crmTestRootTokenUsed) && $__crmTestRootTokenUsed !== '') {
        $tokenCandidates[] = $__crmTestRootTokenUsed;
    }
    $envToken = trim((string)getenv('CRM_TEST_ROOT_TOKEN'));
    if ($envToken !== '') {
        $tokenCandidates[] = $envToken;
    }
    $tokenCandidates[] = 'RootToken#2026!';
    $tokenCandidates[] = '';
    $tokenCandidates = array_values(array_unique($tokenCandidates));

    $last = ['status' => 0, 'payload' => []];
    foreach ($passwordCandidates as $password) {
        foreach ($tokenCandidates as $tokenCandidate) {
            $payload = [
                'login' => $loginName,
                'password' => $password,
            ];
            if ($tokenCandidate !== '') {
                $payload['token'] = $tokenCandidate;
            }
            $login = ['status' => 0, 'payload' => []];
            for ($attempt = 1; $attempt <= 4; $attempt++) {
                $login = request('POST', '/api/v1/auth/login', $payload);
                $last = $login;
                $isLocked = false;
                if ((int)$login['status'] === 500 && (string)($login['payload']['code'] ?? '') === 'INTERNAL_ERROR') {
                    $errors = (array)($login['payload']['errors'] ?? []);
                    $exceptionList = (array)($errors['exception'] ?? []);
                    $exceptionText = strtolower(implode(' ', array_map(static fn($v): string => (string)$v, $exceptionList)));
                    $isLocked = str_contains($exceptionText, 'database is locked');
                }
                if (!$isLocked) {
                    break;
                }
                usleep(200000);
            }
            if ($login['status'] !== 200 || ($login['payload']['success'] ?? false) !== true) {
                continue;
            }

            $token = (string)($login['payload']['data']['access_token'] ?? '');
            $csrfToken = (string)($login['payload']['data']['csrf_token'] ?? '');
            $userPublicId = (string)($login['payload']['data']['user']['public_id'] ?? '');
            assertTrue($token !== '', 'Access token is required');
            assertTrue($csrfToken !== '', 'CSRF token is required');
            assertTrue($userPublicId !== '', 'User public_id is required');

            $__crmTestRootPasswordUsed = $password;
            $__crmTestRootTokenUsed = $tokenCandidate;

            return ['token' => $token, 'user_public_id' => $userPublicId, 'csrf_token' => $csrfToken];
        }
    }

    $code = (string)($last['payload']['code'] ?? 'UNKNOWN');
    $message = (string)($last['payload']['message'] ?? 'unknown');
    throw new RuntimeException('Login status must be 200, got status=' . (int)$last['status'] . ' code=' . $code . ' message=' . $message);
}

function ensureTestRuntimeReady(): void
{
    global $__crmTestRuntimeReady;
    if ($__crmTestRuntimeReady === true) {
        return;
    }

    $status = request('GET', '/install/status');
    $statusCode = (string)($status['payload']['code'] ?? '');
    $installed = (bool)($status['payload']['data']['installed'] ?? false);
    if ($status['status'] === 404 && $statusCode === 'INSTALL_DISABLED') {
        $installed = true;
    }
    if (!$installed) {
        $setup = request('POST', '/install/setup', [
            'root_login' => 'root',
            'root_password' => 'TropaRoot#2026!',
            'root_token' => 'RootToken#2026!',
            'root_name' => 'Root Administrator',
            'root_email' => 'root@tropa.local',
            'default_language' => 'ru-ru',
        ]);
        $code = (string)($setup['payload']['code'] ?? '');
        if (!in_array($setup['status'], [200, 409], true) && $code !== 'ALREADY_INSTALLED') {
            throw new RuntimeException('Install setup failed in test bootstrap: status=' . (int)$setup['status'] . ' code=' . $code);
        }
    }

    clearTestDatabaseRateLimits();

    $migrationLogin = request('POST', '/api/v1/auth/login', [
        'login' => 'root',
        'password' => 'TropaRoot#2026!',
        'token' => 'RootToken#2026!',
    ]);
    if (($migrationLogin['status'] ?? 0) === 200) {
        $migrationToken = (string)($migrationLogin['payload']['data']['access_token'] ?? '');
        if ($migrationToken !== '') {
            request('POST', '/internal/migration/up', [], authHeaders($migrationToken));
        }
    }

    if (trim((string)getenv('CRM_TEST_SEED_LOGIN_USERS')) === '1') {
        ob_start();
        require_once dirname(__DIR__, 2) . '/scripts/seed_web_login_test_users.php';
        ob_end_clean();
    }
    $__crmTestRuntimeReady = true;
}

function clearTestDatabaseRateLimits(): void
{
    $sqlitePath = trim((string)getenv('CRM_SQLITE_DATABASE'));
    if ($sqlitePath === '' || !is_file($sqlitePath)) {
        return;
    }

    try {
        $pdo = new PDO('sqlite:' . $sqlitePath);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $exists = $pdo->query("SELECT name FROM sqlite_master WHERE type = 'table' AND name = 'rate_limits'")->fetchColumn();
        if ($exists === 'rate_limits') {
            $pdo->exec('DELETE FROM rate_limits');
        }
    }catch (Throwable $e) {
                error_log('[TestBootstrap] ' . $e->getMessage());
                // Test helper only: do not mask the actual API assertion with cleanup noise.
    }
}

/**
 * @return array<string,string>
 */
function authHeaders(string $token): array
{
    return ['Authorization' => 'Bearer ' . $token];
}

function randomSuffix(): string
{
    return gmdate('YmdHis') . '_' . bin2hex(random_bytes(3));
}
