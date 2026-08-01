<?php
declare(strict_types=1);

require __DIR__ . '/_bootstrap.php';

function runPhpSnippet(string $snippet): string
{
    $cmd = 'php -r ' . escapeshellarg($snippet) . ' 2>&1';
    $out = shell_exec($cmd);
    if (!is_string($out)) {
        throw new RuntimeException('php snippet returned no output');
    }

    return $out;
}

try {
    $root = loginRoot();
    $token = (string)($root['token'] ?? '');
    assertTrue($token !== '', 'Root token is required');

    $projectRoot = dirname(__DIR__, 3);
    $webIndex = $projectRoot . '/web/index.php';
    $storageBase = $projectRoot . '/storage_api';
    $snippet = <<<'PHP'
putenv('APP_ENV=local');
$_ENV['APP_ENV'] = 'local';
putenv('APP_DEBUG=1');
$_ENV['APP_DEBUG'] = '1';
putenv('CRM_STORAGE_BASE=__STORAGE_BASE__');
$_ENV['CRM_STORAGE_BASE'] = '__STORAGE_BASE__';
putenv('DB_CONNECTION=mysql');
$_ENV['DB_CONNECTION'] = 'mysql';
putenv('APP_KEY=crm-integration-test-app-key-2026');
$_ENV['APP_KEY'] = 'crm-integration-test-app-key-2026';
putenv('CSRF_SECRET_KEY=crm-integration-test-csrf-secret-2026');
$_ENV['CSRF_SECRET_KEY'] = 'crm-integration-test-csrf-secret-2026';
register_shutdown_function(static function (): void {
    echo 'CODE=' . http_response_code() . PHP_EOL;
});
$_GET = ['route' => 'admin-ai'];
$_POST = [];
$_COOKIE = ['crm_api_session' => '__TOKEN__'];
$_FILES = [];
$_SERVER = [
    'REQUEST_METHOD' => 'GET',
    'REQUEST_URI' => '/web/index.php?route=admin-ai',
    'SCRIPT_NAME' => '/web/index.php',
    'HTTP_HOST' => 'crm.local',
    'REMOTE_ADDR' => '127.0.0.1',
];
ob_start();
require '__WEB_INDEX__';
ob_end_clean();
PHP;

    $snippet = str_replace('__WEB_INDEX__', addslashes($webIndex), $snippet);
    $snippet = str_replace('__STORAGE_BASE__', addslashes($storageBase), $snippet);
    $snippet = str_replace('__TOKEN__', addslashes($token), $snippet);
    $out = runPhpSnippet($snippet);
    assertTrue(str_contains($out, 'CODE=200'), 'Root must return HTTP 200 for admin-ai web page');

    fwrite(STDOUT, "[OK] web_admin_ai_root_access_smoke\n");
} catch (Throwable $e) {
    fwrite(STDERR, "[FAIL] web_admin_ai_root_access_smoke: " . $e->getMessage() . "\n");
    exit(1);
}
