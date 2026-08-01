<?php
declare(strict_types=1);

require_once __DIR__ . '/../../system/library/support/Autoloader.php';

$autoloader = new Api\System\Library\Support\Autoloader(dirname(__DIR__, 2));
$autoloader->register();

use Api\System\Library\App;
use Api\System\Library\Config;

function appSecurityAssert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function appWithSecurityConfig(string $env, string $corsAllowOrigin, string $csrfSecret = 'unit-csrf-secret'): App
{
    $app = new App(dirname(__DIR__, 2));
    $config = new Config();
    $config->merge('default', ['app' => ['env' => $env]]);
    $config->merge('security', [
        'auth' => ['csrf' => ['secret_key' => $csrfSecret]],
        'cors' => ['allow_origin' => $corsAllowOrigin],
        'webhook' => ['secret_key' => 'webhook-secret-must-not-drive-csrf'],
    ]);
    $config->merge('install', ['bootstrap_secret' => 'install-bootstrap-secret']);

    $property = new ReflectionProperty(App::class, 'config');
    $property->setValue($app, $config);

    return $app;
}

try {
    $validate = new ReflectionMethod(App::class, 'validateSecurityConfig');
    $resolveCors = new ReflectionMethod(App::class, 'resolveCorsOrigin');
    $csrfToken = new ReflectionMethod(App::class, 'csrfTokenForSession');

    $prodWildcard = appWithSecurityConfig('prod', '*');
    $thrown = false;
    try {
        $validate->invoke($prodWildcard);
    } catch (RuntimeException $e) {
        $thrown = $e->getMessage() === 'CONFIG_SECURITY_CORS_WILDCARD_PRODUCTION';
    }
    appSecurityAssert($thrown, 'Production wildcard CORS must be rejected during config validation');
    appSecurityAssert($resolveCors->invoke($prodWildcard, 'https://crm.example.com') === '', 'Production wildcard CORS must fail closed at runtime');

    $devWildcard = appWithSecurityConfig('dev', '*');
    $validate->invoke($devWildcard);
    appSecurityAssert($resolveCors->invoke($devWildcard, 'https://tool.local') === '*', 'Dev wildcard CORS must remain available');

    $prodAllowlist = appWithSecurityConfig('production', 'https://crm.example.com,https://admin.example.com');
    $validate->invoke($prodAllowlist);
    appSecurityAssert($resolveCors->invoke($prodAllowlist, 'https://crm.example.com') === 'https://crm.example.com', 'Production allowlist must echo allowed origin');
    appSecurityAssert($resolveCors->invoke($prodAllowlist, 'https://evil.example.test') === '', 'Production allowlist must reject unknown origin');

    $prodNoCsrfSecret = appWithSecurityConfig('prod', 'https://crm.example.com', '');
    $thrown = false;
    try {
        $validate->invoke($prodNoCsrfSecret);
    } catch (RuntimeException $e) {
        $thrown = $e->getMessage() === 'CONFIG_SECURITY_CSRF_SECRET_REQUIRED';
    }
    appSecurityAssert($thrown, 'Production without CSRF_SECRET_KEY/APP_KEY must be rejected during config validation');

    $prodWithAppKey = appWithSecurityConfig('production', 'https://crm.example.com', 'app-key-one');
    $validate->invoke($prodWithAppKey);
    $tokenA = (string)$csrfToken->invoke($prodWithAppKey, 'session-token');
    $tokenB = (string)$csrfToken->invoke($prodWithAppKey, 'session-token');
    appSecurityAssert($tokenA !== '' && $tokenA === $tokenB, 'Production CSRF token must be stable for same session and APP_KEY');

    $prodRotatedAppKey = appWithSecurityConfig('production', 'https://crm.example.com', 'app-key-two');
    $tokenRotated = (string)$csrfToken->invoke($prodRotatedAppKey, 'session-token');
    appSecurityAssert($tokenRotated !== $tokenA, 'Changing APP_KEY/CSRF_SECRET_KEY must invalidate old CSRF tokens');

    $devNoSecret = appWithSecurityConfig('test', '*', '');
    $validate->invoke($devNoSecret); // non-production: validation permits a missing secret
    // The config layer (config/security.php) generates random keys for
    // non-production, so an empty secret is an invalid state even in dev/test.
    // csrfTokenForSession must therefore FAIL CLOSED, never silently fall back.
    $devSecretThrown = false;
    try {
        $csrfToken->invoke($devNoSecret, 'session-token');
    } catch (RuntimeException $e) {
        $devSecretThrown = $e->getMessage() === 'CONFIG_SECURITY_CSRF_SECRET_REQUIRED';
    }
    appSecurityAssert($devSecretThrown, 'csrfTokenForSession must fail closed when CSRF secret is empty (no fallback)');

    $validateRuntime = new ReflectionMethod(App::class, 'validateProductionRuntimeConfig');
    $prodWithTestRuntime = appWithSecurityConfig('production', 'https://crm.example.com', 'app-key-one');
    $prodWithTestRuntimeConfig = new Config();
    $prodWithTestRuntimeConfig->merge('default', ['app' => ['env' => 'production']]);
    $prodWithTestRuntimeConfig->merge('security', [
        'auth' => ['csrf' => ['secret_key' => 'csrf-secret']],
        'cors' => ['allow_origin' => 'https://crm.example.com'],
    ]);
    $prodWithTestRuntimeConfig->merge('database', [
        'connections' => ['sqlite' => ['database' => '/srv/crm/api/storage_test_runtime/temp/crm.sqlite']],
    ]);
    $prodWithTestRuntimeConfig->merge('logging', [
        'channels' => ['error' => '/srv/crm/api/storage_test_runtime/logs/error.log'],
    ]);
    $property = new ReflectionProperty(App::class, 'config');
    $property->setValue($prodWithTestRuntime, $prodWithTestRuntimeConfig);
    $thrown = false;
    try {
        $validateRuntime->invoke($prodWithTestRuntime);
    } catch (RuntimeException $e) {
        $thrown = $e->getMessage() === 'CONFIG_LOCAL_TEST_RUNTIME_PRODUCTION';
    }
    appSecurityAssert($thrown, 'Production must reject database/logging paths pointing to storage_test_runtime');

    $appSource = (string)file_get_contents(dirname(__DIR__, 2) . '/system/library/app.php');
    appSecurityAssert(!str_contains($appSource, 'Access-Control-Allow-Credentials'), 'CORS must not emit credentials header');
    appSecurityAssert(str_contains($appSource, "header('Vary: Origin')"), 'CORS allowlist echo must keep Vary: Origin');
    appSecurityAssert(!str_contains($appSource, "config->get('security.webhook.secret_key', '') ?: getenv('APP_KEY')"), 'CSRF must not prefer webhook secret');

    echo "[OK] app_security_config_unit\n";
} catch (Throwable $e) {
    fwrite(STDERR, '[FAIL] app_security_config_unit: ' . $e->getMessage() . "\n");
    exit(1);
}
