<?php
declare(strict_types=1);

require __DIR__ . '/_bootstrap.php';

function runPhpSnippetLifecycle(string $snippet): string
{
    $cmd = 'php -r ' . escapeshellarg($snippet);
    $out = shell_exec($cmd);
    if (!is_string($out) || $out === '') {
        throw new RuntimeException('php snippet returned empty output');
    }
    return $out;
}

try {
    $projectRoot = dirname(__DIR__, 3);
    $webRoutes = require $projectRoot . '/web/config/routes.php';
    assertTrue(is_array($webRoutes), 'web routes must be array');
    assertTrue(array_key_exists('password-reset-request', $webRoutes), 'password-reset-request route is required');
    assertTrue(array_key_exists('password-reset-confirm', $webRoutes), 'password-reset-confirm route is required');
    assertTrue(array_key_exists('invitation-accept', $webRoutes), 'invitation-accept route is required');

    $requestTpl = file_get_contents($projectRoot . '/web/view/template/page/password_reset_request.php');
    $confirmTpl = file_get_contents($projectRoot . '/web/view/template/page/password_reset_confirm.php');
    $inviteTpl = file_get_contents($projectRoot . '/web/view/template/page/invitation_accept.php');
    assertTrue(is_string($requestTpl) && str_contains($requestTpl, 'data-page="password-reset-request"'), 'password reset request template marker missing');
    assertTrue(is_string($confirmTpl) && str_contains($confirmTpl, 'data-page="password-reset-confirm"'), 'password reset confirm template marker missing');
    assertTrue(is_string($inviteTpl) && str_contains($inviteTpl, 'data-page="invitation-accept"'), 'invitation accept template marker missing');

    $webIndex = $projectRoot . '/web/index.php';
    $publicProbe = <<<'PHP_SNIPPET'
register_shutdown_function(static function (): void {
    echo 'CODE=' . http_response_code() . PHP_EOL;
});
$_POST = [];
$_COOKIE = [];
$_FILES = [];
$_SERVER = [
    'REQUEST_METHOD' => 'GET',
    'REQUEST_URI' => '/web/index.php?route=password-reset-request',
    'SCRIPT_NAME' => '/web/index.php',
    'HTTP_HOST' => 'crm.local',
    'REMOTE_ADDR' => '127.0.0.1',
];
$_GET = ['route' => 'password-reset-request'];
ob_start();
require '__WEB_INDEX__';
$html = (string)ob_get_clean();
echo (strpos($html, 'data-page="password-reset-request"') !== false ? 'PUBLIC_OK' : 'PUBLIC_FAIL') . PHP_EOL;
PHP_SNIPPET;
    $publicProbe = str_replace('__WEB_INDEX__', addslashes($webIndex), $publicProbe);
    $publicOut = runPhpSnippetLifecycle($publicProbe);
    assertTrue(str_contains($publicOut, 'PUBLIC_OK'), 'password-reset-request page must render without auth');

    $invalidReset = request('POST', '/api/v1/security/password-reset/confirm', [
        'reset_token' => 'invalid-token',
        'new_password' => 'StrongPass123!',
    ]);
    assertTrue($invalidReset['status'] === 404, 'Invalid reset token must return 404');

    $invalidInvite = request('POST', '/api/v1/security/invitations/accept', [
        'invitation_token' => 'invalid-token',
        'login' => 'invite_' . randomSuffix(),
        'full_name' => 'Invalid Invite',
        'password' => 'StrongPass123!',
    ]);
    assertTrue($invalidInvite['status'] === 404, 'Invalid invitation token must return 404');

    $auth = loginRoot();
    $headers = authHeaders((string)$auth['token']);
    $tfaStatus = request('GET', '/api/v1/security/2fa/status', [], $headers);
    assertTrue($tfaStatus['status'] === 200, '2FA status endpoint must be available for profile UI');

    fwrite(STDOUT, "[OK] web_security_account_lifecycle_smoke\n");
} catch (Throwable $e) {
    fwrite(STDERR, '[FAIL] web_security_account_lifecycle_smoke: ' . $e->getMessage() . "\n");
    exit(1);
}
