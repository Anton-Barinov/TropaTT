<?php
declare(strict_types=1);

require __DIR__ . '/_bootstrap.php';

use Api\System\Library\Config;

try {
    $installStatus = request('GET', '/install/status');
    assertTrue($installStatus['status'] === 404, 'Install status must be disabled after setup');
    assertTrue((string)($installStatus['payload']['code'] ?? '') === 'INSTALL_DISABLED', 'Install status code must be INSTALL_DISABLED');

    $installSetup = request('POST', '/install/setup', []);
    assertTrue($installSetup['status'] === 404, 'Install setup must be disabled after setup');
    assertTrue((string)($installSetup['payload']['code'] ?? '') === 'INSTALL_DISABLED', 'Install setup code must be INSTALL_DISABLED');

    $healthUnauthorized = request('GET', '/api/v1/health/deep');
    assertTrue($healthUnauthorized['status'] === 401, 'Health deep without auth must be 401');

    $auth = loginRoot();
    $headers = authHeaders($auth['token']);

    $meViaCookie = request('GET', '/api/v1/auth/me', [], [], [], [
        'crm_api_session' => $auth['token'],
    ]);
    assertTrue($meViaCookie['status'] === 200, 'Auth me with session cookie must be 200');
    assertTrue((string)($meViaCookie['payload']['code'] ?? '') === 'AUTH_ME', 'Auth me with session cookie code must be AUTH_ME');
    $csrfToken = (string)($meViaCookie['payload']['data']['csrf_token'] ?? '');
    assertTrue($csrfToken !== '', 'Auth me with session cookie must return csrf_token');

    $csrfBlocked = request('POST', '/api/v1/projects', [
        'title' => 'CSRF blocked project ' . randomSuffix(),
    ], [], [], [
        'crm_api_session' => $auth['token'],
    ]);
    assertTrue($csrfBlocked['status'] === 403, 'Cookie-auth write without CSRF must be 403');
    assertTrue((string)($csrfBlocked['payload']['code'] ?? '') === 'CSRF_TOKEN_INVALID', 'Cookie-auth write without CSRF code must be CSRF_TOKEN_INVALID');

    $csrfBadOrigin = request('POST', '/api/v1/projects', [
        'title' => 'CSRF bad origin project ' . randomSuffix(),
    ], [
        'X-CSRF-Token' => $csrfToken,
        'Origin' => 'https://evil.example.test',
    ], [], [
        'crm_api_session' => $auth['token'],
    ]);
    assertTrue($csrfBadOrigin['status'] === 403, 'Cookie-auth write with untrusted Origin must be 403');
    assertTrue((string)($csrfBadOrigin['payload']['code'] ?? '') === 'CSRF_TOKEN_INVALID', 'Cookie-auth write with untrusted Origin code must be CSRF_TOKEN_INVALID');

    $csrfAllowed = request('POST', '/api/v1/projects', [
        'title' => 'CSRF allowed project ' . randomSuffix(),
    ], ['X-CSRF-Token' => $csrfToken], [], [
        'crm_api_session' => $auth['token'],
    ]);
    assertTrue($csrfAllowed['status'] === 201, 'Cookie-auth write with CSRF must be accepted');
    $csrfProjectId = (string)($csrfAllowed['payload']['data']['project']['public_id'] ?? '');
    if ($csrfProjectId !== '') {
        request('DELETE', '/api/v1/projects/' . $csrfProjectId, [], $headers);
    }

    $csrfAllowedHttpsLocalhost = request('POST', '/api/v1/projects', [
        'title' => 'CSRF allowed https localhost project ' . randomSuffix(),
    ], [
        'X-CSRF-Token' => $csrfToken,
        'Origin' => 'https://localhost',
    ], [], [
        'crm_api_session' => $auth['token'],
    ]);
    assertTrue($csrfAllowedHttpsLocalhost['status'] === 201, 'Cookie-auth write from https://localhost with CSRF must be accepted');
    $csrfHttpsLocalhostProjectId = (string)($csrfAllowedHttpsLocalhost['payload']['data']['project']['public_id'] ?? '');
    if ($csrfHttpsLocalhostProjectId !== '') {
        request('DELETE', '/api/v1/projects/' . $csrfHttpsLocalhostProjectId, [], $headers);
    }

    $health = request('GET', '/api/v1/health/deep', [], $headers);
    assertTrue($health['status'] === 200, 'Health deep with root auth must be 200');
    $healthCode = (string)($health['payload']['code'] ?? '');
    assertTrue(in_array($healthCode, ['HEALTH_DEEP_OK', 'HEALTH_DEEP_DEGRADED'], true), 'Health deep code mismatch');

    $system = request('GET', '/api/v1/admin/widgets/system', [], $headers);
    assertTrue($system['status'] === 200, 'Root system widget must be 200');
    $systemData = (array)($system['payload']['data']['widgets'] ?? []);
    assertTrue(!isset($systemData['app']['env']), 'System widget must not expose app env');
    assertTrue(!isset($systemData['app']['debug']), 'System widget must not expose debug flag');
    foreach ((array)($systemData['storage']['directories'] ?? []) as $directory) {
        assertTrue(is_array($directory) && !array_key_exists('path', $directory), 'System widget must not expose storage paths');
    }

    $suffix = randomSuffix();
    $role = request('POST', '/api/v1/roles', [
        'code' => 'logs_view_' . str_replace(['-', ':'], '_', $suffix),
        'title' => 'Logs view ' . $suffix,
    ], $headers);
    assertTrue($role['status'] === 201, 'Logs-view role create must be 201');
    $rolePublicId = (string)($role['payload']['data']['role']['public_id'] ?? '');
    assertTrue($rolePublicId !== '', 'Logs-view role public_id is required');
    $rolePerms = request('PUT', '/api/v1/roles/' . $rolePublicId . '/permissions', [
        'permission_codes' => ['logs.view'],
    ], $headers);
    assertTrue($rolePerms['status'] === 200, 'Logs-view role permissions must be set');
    $limitedPassword = 'LimitedPass#2026!';
    $limitedToken = 'LimitedToken#' . bin2hex(random_bytes(4));
    $limitedLogin = 'logs_view_' . bin2hex(random_bytes(4));
    $limitedUser = request('POST', '/api/v1/users', [
        'login' => $limitedLogin,
        'password' => $limitedPassword,
        'token' => $limitedToken,
        'email' => $limitedLogin . '@crm.local',
        'role_public_ids' => [$rolePublicId],
        'is_active' => 1,
    ], $headers);
    assertTrue($limitedUser['status'] === 201, 'Limited logs-view user create must be 201');
    $limitedLoginResponse = request('POST', '/api/v1/auth/login', [
        'login' => $limitedLogin,
        'password' => $limitedPassword,
        'token' => $limitedToken,
    ]);
    assertTrue($limitedLoginResponse['status'] === 200, 'Limited logs-view login must be 200');
    $limitedHeaders = authHeaders((string)($limitedLoginResponse['payload']['data']['access_token'] ?? ''));
    $limitedSystem = request('GET', '/api/v1/admin/widgets/system', [], $limitedHeaders);
    assertTrue($limitedSystem['status'] === 403, 'Non-root logs.view user must not access system widget');

    $resetKnown = request('POST', '/api/v1/security/password-reset', [
        'identifier' => (string)(getenv('CRM_TEST_ROOT_LOGIN') ?: 'root'),
    ]);
    assertTrue($resetKnown['status'] === 200, 'Password reset known user must be 200');
    assertTrue(($resetKnown['payload']['data']['accepted'] ?? false) === true, 'Password reset known user must return accepted=true');
    assertTrue(!isset($resetKnown['payload']['data']['reset_token']), 'Password reset known user must not expose reset_token');

    $resetUnknown = request('POST', '/api/v1/security/password-reset', [
        'identifier' => 'missing_' . randomSuffix(),
    ]);
    assertTrue($resetUnknown['status'] === 200, 'Password reset unknown user must be 200');
    assertTrue(($resetUnknown['payload']['data']['accepted'] ?? false) === true, 'Password reset unknown user must return accepted=true');
    assertTrue(!isset($resetUnknown['payload']['data']['reset_token']), 'Password reset unknown user must not expose reset_token');

    $sensitiveProbe = request('POST', '/api/v1/security/password-reset', [
        'identifier' => 'missing_' . randomSuffix(),
        'password' => 'ShouldNotBeLogged#1',
        'reset_token' => 'reset-token-should-not-be-logged',
        'content_base64' => base64_encode('document content should not be logged'),
        'rows' => [['secret' => 'row should not be logged']],
        'page' => 3,
    ]);
    assertTrue($sensitiveProbe['status'] === 200, 'Sensitive payload probe must be accepted');

    $requestLogs = request('GET', '/api/v1/logs/request?limit=25&method=POST&request_route=api/v1/security/password-reset', [], $headers);
    assertTrue($requestLogs['status'] === 200, 'Request logs list for sensitive probe must be 200');
    $items = (array)($requestLogs['payload']['data']['items'] ?? []);
    assertTrue(count($items) > 0, 'Request logs must include password-reset entries');

    $foundSafePayload = false;
    foreach ($items as $item) {
        $payloadJson = (string)($item['payload'] ?? '');
        if (!str_contains($payloadJson, '"page":3')) {
            continue;
        }

        $foundSafePayload = true;
        assertTrue(!str_contains($payloadJson, 'ShouldNotBeLogged#1'), 'Request log must not contain raw password');
        assertTrue(!str_contains($payloadJson, 'reset-token-should-not-be-logged'), 'Request log must not contain raw reset token');
        assertTrue(!str_contains($payloadJson, 'document content should not be logged'), 'Request log must not contain raw content');
        assertTrue(!str_contains($payloadJson, 'row should not be logged'), 'Request log must not contain raw rows');
        assertTrue(str_contains($payloadJson, '_omitted_fields_count'), 'Request log payload must include omitted fields count');
        break;
    }
    assertTrue($foundSafePayload, 'Request logs must contain sanitized sensitive probe payload');

    $upload = request('POST', '/api/v1/files', [
        'entity_type' => 'task',
        'name' => 'php-payload.txt',
        'mime_type' => 'text/plain',
        'content_base64' => base64_encode("<?php echo 'blocked';\n"),
    ], $headers);
    assertTrue($upload['status'] === 201, 'PHP payload upload must be accepted into quarantine');
    $uploadedFile = (array)($upload['payload']['data']['file'] ?? []);
    assertTrue(($uploadedFile['is_quarantined'] ?? false) === true, 'PHP payload with safe declared MIME must be quarantined');

    $config = new Config();
    $config->load(dirname(__DIR__, 2) . '/config/default.php', 'default');
    $config->load(dirname(__DIR__, 2) . '/config/install.php', 'install');
    $config->load(dirname(__DIR__, 2) . '/config/logging.local.php', 'logging');

    $lockPath = (string)$config->get('install.lock_file', '');
    assertTrue($lockPath !== '' && is_file($lockPath), 'Install lock file must exist');
    $lockRaw = file_get_contents($lockPath);
    $lockData = is_string($lockRaw) ? json_decode($lockRaw, true) : null;
    assertTrue(is_array($lockData), 'Install lock payload must be JSON object');
    $logsDir = trim((string)($lockData['logs_dir'] ?? ''));
    assertTrue($logsDir !== '' && is_dir($logsDir), 'Install lock must contain existing logs_dir');
    assertTrue((bool)preg_match('#/logs_[a-f0-9]{16}$#', $logsDir), 'logs_dir must contain randomized logs_<hash> suffix');

    $requestLog = trim((string)$config->get('logging.channels.request', ''));
    assertTrue($requestLog !== '', 'logging.local request channel must be configured');
    assertTrue(str_starts_with($requestLog, $logsDir . '/'), 'logging.local request channel must point to randomized logs_dir');

    echo "[OK] security_audit_hardening_smoke\n";
    exit(0);
} catch (Throwable $e) {
    fwrite(STDERR, '[FAIL] security_audit_hardening_smoke: ' . $e->getMessage() . "\n");
    exit(1);
}
