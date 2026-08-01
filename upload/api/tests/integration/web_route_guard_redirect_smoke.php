<?php
declare(strict_types=1);

require __DIR__ . '/_bootstrap.php';

function guardFail(string $message): void
{
    throw new RuntimeException($message);
}

function runPhpSnippet(string $snippet): string
{
    $cmd = 'php -r ' . escapeshellarg($snippet);
    $out = shell_exec($cmd);
    if (!is_string($out) || $out === '') {
        guardFail('php snippet returned empty output');
    }
    return $out;
}

try {
    $projectRoot = dirname(__DIR__, 3);
    $webIndex = $projectRoot . '/web/index.php';

    $redirectProbe = <<<'PHP'
register_shutdown_function(static function (): void {
    echo 'CODE=' . http_response_code() . PHP_EOL;
    foreach (headers_list() as $h) {
        echo 'HDR=' . $h . PHP_EOL;
    }
});
$_GET = ['route' => 'dashboard'];
$_POST = [];
$_COOKIE = [];
$_FILES = [];
$_SERVER = [
    'REQUEST_METHOD' => 'GET',
    'REQUEST_URI' => '/web/index.php?route=dashboard',
    'SCRIPT_NAME' => '/web/index.php',
    'HTTP_HOST' => 'crm.local',
    'REMOTE_ADDR' => '127.0.0.1',
];
require '__WEB_INDEX__';
PHP;
    $redirectProbe = str_replace('__WEB_INDEX__', addslashes($webIndex), $redirectProbe);
    $redirectOut = runPhpSnippet($redirectProbe);
    if (!str_contains($redirectOut, 'CODE=302')) {
        guardFail('Expected HTTP 302 for unauthenticated protected route');
    }

    $webIndexSource = file_get_contents($webIndex);
    if (!is_string($webIndexSource) || !str_contains($webIndexSource, '?route=login&redirect=')) {
        guardFail('Expected safe login redirect contract in web/index.php');
    }

    $loginProbe = <<<'PHP'
$_GET = ['route' => 'login'];
$_POST = [];
$_COOKIE = [];
$_FILES = [];
$_SERVER = [
    'REQUEST_METHOD' => 'GET',
    'REQUEST_URI' => '/web/index.php?route=login',
    'SCRIPT_NAME' => '/web/index.php',
    'HTTP_HOST' => 'crm.local',
    'REMOTE_ADDR' => '127.0.0.1',
];
ob_start();
require '__WEB_INDEX__';
$html = (string)ob_get_clean();
echo (strpos($html, 'data-page="login"') !== false ? 'LOGIN_OK' : 'LOGIN_FAIL') . PHP_EOL;
PHP;
    $loginProbe = str_replace('__WEB_INDEX__', addslashes($webIndex), $loginProbe);
    $loginOut = runPhpSnippet($loginProbe);
    if (!str_contains($loginOut, 'LOGIN_OK')) {
        guardFail('Expected login route to render without auth guard');
    }

    fwrite(STDOUT, "[OK] web_route_guard_redirect_smoke\n");
} catch (Throwable $e) {
    fwrite(STDERR, "[FAIL] web_route_guard_redirect_smoke: " . $e->getMessage() . "\n");
    exit(1);
}
