<?php
declare(strict_types=1);

require __DIR__ . '/_bootstrap.php';

try {
    $config = new \Api\System\Library\Config();
    foreach (['default', 'database', 'install'] as $name) {
        $config->load(dirname(__DIR__, 2) . '/config/' . $name . '.php', $name);
    }
    $config->load(dirname(__DIR__, 2) . '/config/database.local.php', 'database');

    $connectionManager = new \Api\System\Library\Database\ConnectionManager($config);
    $pdo = $connectionManager->connect();

    $count = static function (PDO $pdo, string $table): int {
        return (int)$pdo->query('SELECT COUNT(*) FROM ' . $table)->fetchColumn();
    };

    $beforeRequest = $count($pdo, 'request_logs');
    $beforeSecurity = $count($pdo, 'security_logs');
    $beforeAudit = $count($pdo, 'audit_logs');

    $root = loginRoot();
    $rootHeaders = authHeaders($root['token']);

    $roleCreate = request('POST', '/api/v1/roles', [
        'code' => 'log_' . randomSuffix(),
        'title' => 'Logs Test Role',
    ], $rootHeaders);
    assertTrue($roleCreate['status'] === 201, 'Role create for logs smoke must be 201');
    $rolePublicId = (string)($roleCreate['payload']['data']['role']['public_id'] ?? '');

    $userLogin = 'logs_user_' . randomSuffix();
    $userToken = 'logs-token-' . randomSuffix();
    $userCreate = request('POST', '/api/v1/users', [
        'login' => $userLogin,
        'password' => 'LogsPass123!',
        'token' => $userToken,
        'email' => $userLogin . '@crm.local',
        'role_public_ids' => [$rolePublicId],
    ], $rootHeaders);
    assertTrue($userCreate['status'] === 201, 'User create for logs smoke must be 201');
    $userPublicId = (string)($userCreate['payload']['data']['user']['public_id'] ?? '');

    $userLoginRes = request('POST', '/api/v1/auth/login', [
        'login' => $userLogin,
        'password' => 'LogsPass123!',
        'token' => $userToken,
    ]);
    assertTrue($userLoginRes['status'] === 200, 'Non-root login must be 200');
    $userHeaders = authHeaders((string)$userLoginRes['payload']['data']['access_token']);

    $forbiddenLogs = request('GET', '/api/v1/logs/request', [], $userHeaders);
    assertTrue($forbiddenLogs['status'] === 403, 'Non-root must be forbidden for logs');

    $requestLogs = request('GET', '/api/v1/logs/request', [], $rootHeaders);
    $securityLogs = request('GET', '/api/v1/logs/security', [], $rootHeaders);
    $auditLogs = request('GET', '/api/v1/logs/audit', [], $rootHeaders);

    assertTrue($requestLogs['status'] === 200, 'Root request logs status must be 200');
    assertTrue($securityLogs['status'] === 200, 'Root security logs status must be 200');
    assertTrue($auditLogs['status'] === 200, 'Root audit logs status must be 200');

    request('DELETE', '/api/v1/users/' . $userPublicId, [], $rootHeaders);
    request('DELETE', '/api/v1/roles/' . $rolePublicId, [], $rootHeaders);

    $afterRequest = $count($pdo, 'request_logs');
    $afterSecurity = $count($pdo, 'security_logs');
    $afterAudit = $count($pdo, 'audit_logs');

    assertTrue($afterRequest > $beforeRequest, 'Request logs table must grow');
    assertTrue($afterSecurity >= $beforeSecurity, 'Security logs table must be readable');
    assertTrue($afterAudit > $beforeAudit, 'Audit logs table must grow');

    echo "Logs smoke: OK\n";
    echo "request_logs_delta=" . ($afterRequest - $beforeRequest) . "\n";
    echo "security_logs_delta=" . ($afterSecurity - $beforeSecurity) . "\n";
    echo "audit_logs_delta=" . ($afterAudit - $beforeAudit) . "\n";
} catch (Throwable $e) {
    fwrite(STDERR, "Logs smoke FAILED: " . $e->getMessage() . "\n");
    exit(1);
}
