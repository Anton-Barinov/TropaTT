<?php
declare(strict_types=1);

require_once __DIR__ . '/../_live_http.php';

/**
 * @param array<string,mixed> $query
 * @param array<string,string> $headers
 * @return array{status:int,headers:array<int,string>,body:string,payload:array<string,mixed>}
 */
function liveRequestQuery(string $method, string $route, array $query = [], array $headers = []): array
{
    $method = strtoupper($method);
    $url = LIVE_API_BASE . '?route=' . rawurlencode($route);
    if ($query !== []) {
        $url .= '&' . http_build_query($query);
    }

    $headerLines = [
        'Accept: application/json',
    ];

    foreach ($headers as $name => $value) {
        $headerLines[] = $name . ': ' . $value;
    }

    $context = stream_context_create([
        'http' => [
            'method' => $method,
            'header' => implode("\r\n", $headerLines),
            'ignore_errors' => true,
            'timeout' => 20,
        ],
        'ssl' => [
            'verify_peer' => false,
            'verify_peer_name' => false,
            'allow_self_signed' => true,
        ],
    ]);

    $body = @file_get_contents($url, false, $context);
    $responseHeaders = $http_response_header;
    if (!is_string($body)) {
        $body = '';
    }

    $status = 0;
    if (isset($responseHeaders[0]) && preg_match('/\s(\d{3})\s/', $responseHeaders[0], $m)) {
        $status = (int) $m[1];
    }

    $decoded = json_decode($body, true);
    if (!is_array($decoded)) {
        $decoded = [];
    }

    return [
        'status' => $status,
        'headers' => $responseHeaders,
        'body' => $body,
        'payload' => $decoded,
    ];
}

/** @param mixed $value */
function assertNoCyrillicMisc(mixed $value, string $context): void
{
    if (is_string($value)) {
        liveAssert(!preg_match('/\p{Cyrillic}/u', $value), $context . ': value contains Cyrillic');
        return;
    }

    if (is_array($value)) {
        foreach ($value as $k => $v) {
            assertNoCyrillicMisc($v, $context . '.' . (string) $k);
        }
    }
}

try {
    $root = liveLoginRoot();
    $rootHeaders = ['Authorization' => 'Bearer ' . $root['token']];
    $suffix = strtolower(gmdate('YmdHis') . '_' . bin2hex(random_bytes(3)));

    $login = 'misc_locale_' . $suffix;
    $tokenFactor = 'misc-locale-token-' . $suffix;
    $userCreate = liveRequest('POST', 'api/v1/users', [
        'login' => $login,
        'password' => 'MiscLocale123!',
        'token' => $tokenFactor,
        'email' => $login . '@crm.local',
        'locale' => 'en-gb',
        'is_root' => 1,
    ], $rootHeaders);
    liveAssert($userCreate['status'] === 201, 'Misc root user create must return 201');
    $miscRootPublicId = (string) ($userCreate['payload']['data']['user']['public_id'] ?? '');
    liveAssert($miscRootPublicId !== '', 'Misc root user public_id is required');

    $userLogin = liveRequest('POST', 'api/v1/auth/login', [
        'login' => $login,
        'password' => 'MiscLocale123!',
        'token' => $tokenFactor,
    ]);
    liveAssert($userLogin['status'] === 200, 'Misc root user login must return 200');
    $miscRootToken = (string) ($userLogin['payload']['data']['access_token'] ?? '');
    liveAssert($miscRootToken !== '', 'Misc root user token is required');

    $headers = [
        'Authorization' => 'Bearer ' . $miscRootToken,
        'X-Locale' => 'ru-ru',
    ];

    $healthDefault = liveRequest('GET', 'api/v1/health/status');
    liveAssert($healthDefault['status'] === 200, 'Health status without locale must return 200');
    liveAssert((string) ($healthDefault['payload']['message'] ?? '') === 'Service is available', 'Health default message mismatch');

    $healthInvalid = liveRequest('GET', 'api/v1/health/status', [], ['X-Locale' => 'zz-zz']);
    liveAssert($healthInvalid['status'] === 200, 'Health status with invalid locale must return 200');
    liveAssert((string) ($healthInvalid['payload']['message'] ?? '') === 'Service is available', 'Health invalid locale message mismatch');

    $docsOpenapi = liveRequest('GET', 'api/v1/docs/openapi', [], $headers);
    liveAssert($docsOpenapi['status'] === 200, 'Docs openapi must return 200');
    liveAssert((string) ($docsOpenapi['payload']['message'] ?? '') === 'OpenAPI specification', 'Docs openapi message mismatch');

    $docsSchema = liveRequest('GET', 'api/v1/docs/schema', [], $headers);
    liveAssert($docsSchema['status'] === 200, 'Docs schema must return 200');
    liveAssert((string) ($docsSchema['payload']['message'] ?? '') === 'JSON Schema', 'Docs schema message mismatch');

    $opsSystem = liveRequest('GET', 'api/v1/ops/system', [], $headers);
    liveAssert($opsSystem['status'] === 200, 'Ops system must return 200');
    liveAssert((string) ($opsSystem['payload']['message'] ?? '') === 'System OPS status', 'Ops system message mismatch');

    $widgetsSummary = liveRequest('GET', 'api/v1/admin/widgets/summary', [], $headers);
    liveAssert($widgetsSummary['status'] === 200, 'Admin widgets summary must return 200');
    liveAssert((string) ($widgetsSummary['payload']['message'] ?? '') === 'Admin widgets summary', 'Admin widgets summary message mismatch');

    $widgetsSystem = liveRequest('GET', 'api/v1/admin/widgets/system', [], $headers);
    liveAssert($widgetsSystem['status'] === 200, 'Admin widgets system must return 200');
    liveAssert((string) ($widgetsSystem['payload']['message'] ?? '') === 'Admin system widgets', 'Admin widgets system message mismatch');

    $roleMatrixGet = liveRequest('GET', 'api/v1/admin/role-matrix', [], $headers);
    liveAssert($roleMatrixGet['status'] === 200, 'Role matrix get must return 200');
    liveAssert((string) ($roleMatrixGet['payload']['message'] ?? '') === 'Role and permission matrix', 'Role matrix get message mismatch');

    $roleMatrixInvalid = liveRequest('PATCH', 'api/v1/admin/role-matrix', [
        'roles' => 'bad',
    ], $headers);
    liveAssert($roleMatrixInvalid['status'] === 422, 'Role matrix invalid payload must return 422');
    liveAssert((string) ($roleMatrixInvalid['payload']['message'] ?? '') === 'Validation error', 'Role matrix invalid message mismatch');

    $requestLogs = liveRequest('GET', 'api/v1/logs/request', [], $headers);
    liveAssert($requestLogs['status'] === 200, 'Request logs must return 200');
    liveAssert((string) ($requestLogs['payload']['message'] ?? '') === 'Request logs', 'Request logs message mismatch');

    $securityLogs = liveRequest('GET', 'api/v1/logs/security', [], $headers);
    liveAssert($securityLogs['status'] === 200, 'Security logs must return 200');
    liveAssert((string) ($securityLogs['payload']['message'] ?? '') === 'Security logs', 'Security logs message mismatch');

    $auditLogs = liveRequest('GET', 'api/v1/logs/audit', [], $headers);
    liveAssert($auditLogs['status'] === 200, 'Audit logs must return 200');
    liveAssert((string) ($auditLogs['payload']['message'] ?? '') === 'Audit logs', 'Audit logs message mismatch');

    $userValidation = liveRequest('POST', 'api/v1/users', [], $headers);
    liveAssert($userValidation['status'] === 422, 'User create validation must return 422');
    liveAssert((string) ($userValidation['payload']['message'] ?? '') === 'Validation error', 'User create validation message mismatch');

    $managedLogin = 'misc_target_' . $suffix;
    $managedCreate = liveRequest('POST', 'api/v1/users', [
        'login' => $managedLogin,
        'password' => 'MiscTarget123!',
        'token' => 'misc-target-token-' . $suffix,
        'email' => $managedLogin . '@crm.local',
    ], $headers);
    liveAssert($managedCreate['status'] === 201, 'Managed user create must return 201');
    liveAssert((string) ($managedCreate['payload']['message'] ?? '') === 'User created', 'Managed user create message mismatch');
    $managedUserPublicId = (string) ($managedCreate['payload']['data']['user']['public_id'] ?? '');
    liveAssert($managedUserPublicId !== '', 'Managed user public_id is required');

    $userList = liveRequest('GET', 'api/v1/users', [], $headers);
    liveAssert($userList['status'] === 200, 'User list must return 200');
    liveAssert((string) ($userList['payload']['message'] ?? '') === 'User list', 'User list message mismatch');

    $userGet = liveRequest('GET', 'api/v1/users/' . $managedUserPublicId, [], $headers);
    liveAssert($userGet['status'] === 200, 'User get must return 200');
    liveAssert((string) ($userGet['payload']['message'] ?? '') === 'User details', 'User get message mismatch');

    $userUpdate = liveRequest('PATCH', 'api/v1/users/' . $managedUserPublicId, [
        'full_name' => 'Misc Target Updated',
    ], $headers);
    liveAssert($userUpdate['status'] === 200, 'User update must return 200');
    liveAssert((string) ($userUpdate['payload']['message'] ?? '') === 'User updated', 'User update message mismatch');

    $userTokens = liveRequest('GET', 'api/v1/users/' . $managedUserPublicId . '/tokens', [], $headers);
    liveAssert($userTokens['status'] === 200, 'User tokens must return 200');
    liveAssert((string) ($userTokens['payload']['message'] ?? '') === 'User token data', 'User tokens message mismatch');

    $userRotate = liveRequest('POST', 'api/v1/users/' . $managedUserPublicId . '/tokens/rotate', [], $headers);
    liveAssert($userRotate['status'] === 200, 'User token rotate must return 200');
    liveAssert((string) ($userRotate['payload']['message'] ?? '') === 'User token rotated', 'User token rotate message mismatch');

    $userActivity = liveRequest('GET', 'api/v1/users/' . $managedUserPublicId . '/activity', [], $headers);
    liveAssert($userActivity['status'] === 200, 'User activity must return 200');
    liveAssert((string) ($userActivity['payload']['message'] ?? '') === 'User activity', 'User activity message mismatch');

    $userRevoke = liveRequest('DELETE', 'api/v1/users/' . $managedUserPublicId . '/tokens', [], $headers);
    liveAssert($userRevoke['status'] === 200, 'User token revoke must return 200');
    liveAssert((string) ($userRevoke['payload']['message'] ?? '') === 'User token revoked', 'User token revoke message mismatch');

    $organizationCreate = liveRequest('POST', 'api/v1/organizations', [
        'title' => 'Misc Org ' . $suffix,
        'slug' => 'misc-org-' . $suffix,
    ], $headers);
    liveAssert($organizationCreate['status'] === 201, 'Organization create must return 201');
    liveAssert((string) ($organizationCreate['payload']['message'] ?? '') === 'Organization created', 'Organization create message mismatch');
    $organizationPublicId = (string) ($organizationCreate['payload']['data']['organization']['public_id'] ?? '');
    liveAssert($organizationPublicId !== '', 'Organization public_id is required');

    $organizationList = liveRequest('GET', 'api/v1/organizations', [], $headers);
    liveAssert($organizationList['status'] === 200, 'Organization list must return 200');
    liveAssert((string) ($organizationList['payload']['message'] ?? '') === 'Organization list', 'Organization list message mismatch');

    $organizationGet = liveRequest('GET', 'api/v1/organizations/' . $organizationPublicId, [], $headers);
    liveAssert($organizationGet['status'] === 200, 'Organization get must return 200');
    liveAssert((string) ($organizationGet['payload']['message'] ?? '') === 'Organization details', 'Organization get message mismatch');

    $organizationUpdate = liveRequest('PATCH', 'api/v1/organizations/' . $organizationPublicId, [
        'title' => 'Misc Org Updated ' . $suffix,
    ], $headers);
    liveAssert($organizationUpdate['status'] === 200, 'Organization update must return 200');
    liveAssert((string) ($organizationUpdate['payload']['message'] ?? '') === 'Organization updated', 'Organization update message mismatch');

    $membersList = liveRequest('GET', 'api/v1/organizations/' . $organizationPublicId . '/members', [], $headers);
    liveAssert($membersList['status'] === 200, 'Organization members list must return 200');
    liveAssert((string) ($membersList['payload']['message'] ?? '') === 'Organization member list', 'Organization members list message mismatch');

    $memberInvalid = liveRequest('POST', 'api/v1/organizations/' . $organizationPublicId . '/members', [
        'user_public_id' => $managedUserPublicId,
        'role_code' => 'wrong',
    ], $headers);
    liveAssert($memberInvalid['status'] === 422, 'Organization invalid role must return 422');
    liveAssert((string) ($memberInvalid['payload']['message'] ?? '') === 'Validation error', 'Organization invalid role message mismatch');

    $memberAdd = liveRequest('POST', 'api/v1/organizations/' . $organizationPublicId . '/members', [
        'user_public_id' => $managedUserPublicId,
        'role_code' => 'member',
    ], $headers);
    liveAssert($memberAdd['status'] === 200, 'Organization member add must return 200');
    liveAssert((string) ($memberAdd['payload']['message'] ?? '') === 'Organization member added', 'Organization member add message mismatch');

    $memberRemove = liveRequest('DELETE', 'api/v1/organizations/' . $organizationPublicId . '/members/' . $managedUserPublicId, [], $headers);
    liveAssert($memberRemove['status'] === 200, 'Organization member remove must return 200');
    liveAssert((string) ($memberRemove['payload']['message'] ?? '') === 'Organization member removed', 'Organization member remove message mismatch');

    $templateValidation = liveRequest('POST', 'api/v1/template/tasks', [
        'payload' => 'bad',
    ], $headers);
    liveAssert($templateValidation['status'] === 422, 'Task template validation must return 422');
    liveAssert((string) ($templateValidation['payload']['message'] ?? '') === 'Validation error', 'Task template validation message mismatch');

    $templateCreate = liveRequest('POST', 'api/v1/template/tasks', [
        'title' => 'Misc Task Template ' . $suffix,
        'payload' => ['stage' => 'one'],
    ], $headers);
    liveAssert($templateCreate['status'] === 201, 'Task template create must return 201');
    liveAssert((string) ($templateCreate['payload']['message'] ?? '') === 'Task template created', 'Task template create message mismatch');
    $templatePublicId = (string) ($templateCreate['payload']['data']['template']['public_id'] ?? '');
    liveAssert($templatePublicId !== '', 'Task template public_id is required');

    $templateList = liveRequest('GET', 'api/v1/template/tasks', [], $headers);
    liveAssert($templateList['status'] === 200, 'Task template list must return 200');
    liveAssert((string) ($templateList['payload']['message'] ?? '') === 'Task template list', 'Task template list message mismatch');

    $templateGet = liveRequest('GET', 'api/v1/template/tasks/' . $templatePublicId, [], $headers);
    liveAssert($templateGet['status'] === 200, 'Task template get must return 200');
    liveAssert((string) ($templateGet['payload']['message'] ?? '') === 'Task template', 'Task template get message mismatch');

    $templateUpdate = liveRequest('PATCH', 'api/v1/template/tasks/' . $templatePublicId, [
        'title' => 'Misc Task Template Updated ' . $suffix,
    ], $headers);
    liveAssert($templateUpdate['status'] === 200, 'Task template update must return 200');
    liveAssert((string) ($templateUpdate['payload']['message'] ?? '') === 'Task template updated', 'Task template update message mismatch');

    $projectCreate = liveRequest('POST', 'api/v1/projects', [
        'title' => 'Misc Project ' . $suffix,
    ], $headers);
    liveAssert($projectCreate['status'] === 201, 'Project create for milestone must return 201');
    $projectPublicId = (string) ($projectCreate['payload']['data']['project']['public_id'] ?? '');
    liveAssert($projectPublicId !== '', 'Project public_id is required');

    $taskA = liveRequest('POST', 'api/v1/tasks', [
        'title' => 'Misc Task A ' . $suffix,
        'status' => 'new',
        'priority' => 'normal',
        'project_public_id' => $projectPublicId,
    ], $headers);
    liveAssert($taskA['status'] === 201, 'Task A create must return 201');
    $taskAPublicId = (string) ($taskA['payload']['data']['task']['public_id'] ?? '');
    liveAssert($taskAPublicId !== '', 'Task A public_id is required');

    $taskB = liveRequest('POST', 'api/v1/tasks', [
        'title' => 'Misc Task B ' . $suffix,
        'status' => 'new',
        'priority' => 'normal',
        'project_public_id' => $projectPublicId,
    ], $headers);
    liveAssert($taskB['status'] === 201, 'Task B create must return 201');
    $taskBPublicId = (string) ($taskB['payload']['data']['task']['public_id'] ?? '');
    liveAssert($taskBPublicId !== '', 'Task B public_id is required');

    $milestoneValidation = liveRequest('POST', 'api/v1/milestones', [], $headers);
    liveAssert($milestoneValidation['status'] === 422, 'Milestone validation must return 422');
    liveAssert((string) ($milestoneValidation['payload']['message'] ?? '') === 'Validation error', 'Milestone validation message mismatch');

    $milestoneCreate = liveRequest('POST', 'api/v1/milestones', [
        'project_public_id' => $projectPublicId,
        'title' => 'Misc Milestone ' . $suffix,
    ], $headers);
    liveAssert($milestoneCreate['status'] === 201, 'Milestone create must return 201');
    liveAssert((string) ($milestoneCreate['payload']['message'] ?? '') === 'Milestone created', 'Milestone create message mismatch');
    $milestonePublicId = (string) ($milestoneCreate['payload']['data']['milestone']['public_id'] ?? '');
    liveAssert($milestonePublicId !== '', 'Milestone public_id is required');

    $milestoneList = liveRequestQuery('GET', 'api/v1/milestones', [
        'project_public_id' => $projectPublicId,
    ], $headers);
    liveAssert($milestoneList['status'] === 200, 'Milestone list must return 200');
    liveAssert((string) ($milestoneList['payload']['message'] ?? '') === 'Milestone list', 'Milestone list message mismatch');

    $milestoneGet = liveRequest('GET', 'api/v1/milestones/' . $milestonePublicId, [], $headers);
    liveAssert($milestoneGet['status'] === 200, 'Milestone get must return 200');
    liveAssert((string) ($milestoneGet['payload']['message'] ?? '') === 'Milestone details', 'Milestone get message mismatch');

    $milestoneUpdate = liveRequest('PATCH', 'api/v1/milestones/' . $milestonePublicId, [
        'title' => 'Misc Milestone Updated ' . $suffix,
    ], $headers);
    liveAssert($milestoneUpdate['status'] === 200, 'Milestone update must return 200');
    liveAssert((string) ($milestoneUpdate['payload']['message'] ?? '') === 'Milestone updated', 'Milestone update message mismatch');

    $dependencyValidation = liveRequest('POST', 'api/v1/dependencies', [], $headers);
    liveAssert($dependencyValidation['status'] === 422, 'Dependency validation must return 422');
    liveAssert((string) ($dependencyValidation['payload']['message'] ?? '') === 'Validation error', 'Dependency validation message mismatch');

    $dependencyInvalid = liveRequest('POST', 'api/v1/dependencies', [
        'task_public_id' => $taskAPublicId,
        'depends_on_task_public_id' => $taskBPublicId,
        'dependency_type' => 'BAD',
    ], $headers);
    liveAssert($dependencyInvalid['status'] === 422, 'Dependency invalid type must return 422');
    liveAssert((string) ($dependencyInvalid['payload']['message'] ?? '') === 'Invalid dependency type', 'Dependency invalid type message mismatch');

    $dependencyCreate = liveRequest('POST', 'api/v1/dependencies', [
        'task_public_id' => $taskAPublicId,
        'depends_on_task_public_id' => $taskBPublicId,
        'dependency_type' => 'FS',
    ], $headers);
    liveAssert($dependencyCreate['status'] === 201, 'Dependency create must return 201');
    liveAssert((string) ($dependencyCreate['payload']['message'] ?? '') === 'Dependency created', 'Dependency create message mismatch');
    $dependencyPublicId = (string) ($dependencyCreate['payload']['data']['dependency']['public_id'] ?? '');
    liveAssert($dependencyPublicId !== '', 'Dependency public_id is required');

    $dependencyList = liveRequest('GET', 'api/v1/dependencies', [], $headers);
    liveAssert($dependencyList['status'] === 200, 'Dependency list must return 200');
    liveAssert((string) ($dependencyList['payload']['message'] ?? '') === 'Dependency list', 'Dependency list message mismatch');

    $dependencyDelete = liveRequest('DELETE', 'api/v1/dependencies/' . $dependencyPublicId, [], $headers);
    liveAssert($dependencyDelete['status'] === 200, 'Dependency delete must return 200');
    liveAssert((string) ($dependencyDelete['payload']['message'] ?? '') === 'Dependency deleted', 'Dependency delete message mismatch');

    $templateDelete = liveRequest('DELETE', 'api/v1/template/tasks/' . $templatePublicId, [], $headers);
    liveAssert($templateDelete['status'] === 200, 'Task template delete must return 200');
    liveAssert((string) ($templateDelete['payload']['message'] ?? '') === 'Task template deleted', 'Task template delete message mismatch');

    $milestoneDelete = liveRequest('DELETE', 'api/v1/milestones/' . $milestonePublicId, [], $headers);
    liveAssert($milestoneDelete['status'] === 200, 'Milestone delete must return 200');
    liveAssert((string) ($milestoneDelete['payload']['message'] ?? '') === 'Milestone deleted', 'Milestone delete message mismatch');

    $organizationDelete = liveRequest('DELETE', 'api/v1/organizations/' . $organizationPublicId, [], $headers);
    liveAssert($organizationDelete['status'] === 200, 'Organization delete must return 200');
    liveAssert((string) ($organizationDelete['payload']['message'] ?? '') === 'Organization deleted', 'Organization delete message mismatch');

    $userDelete = liveRequest('DELETE', 'api/v1/users/' . $managedUserPublicId, [], $headers);
    liveAssert($userDelete['status'] === 200, 'Managed user delete must return 200');
    liveAssert((string) ($userDelete['payload']['message'] ?? '') === 'User deactivated', 'Managed user delete message mismatch');

    assertNoCyrillicMisc($roleMatrixInvalid['payload']['errors'] ?? [], 'admin.role_matrix.errors');
    assertNoCyrillicMisc($userValidation['payload']['errors'] ?? [], 'user.validation.errors');
    assertNoCyrillicMisc($memberInvalid['payload']['errors'] ?? [], 'organization.member_invalid.errors');
    assertNoCyrillicMisc($templateValidation['payload']['errors'] ?? [], 'template.validation.errors');
    assertNoCyrillicMisc($milestoneValidation['payload']['errors'] ?? [], 'milestone.validation.errors');
    assertNoCyrillicMisc($dependencyValidation['payload']['errors'] ?? [], 'dependency.validation.errors');
    assertNoCyrillicMisc($dependencyInvalid['payload']['errors'] ?? [], 'dependency.invalid_type.errors');

    liveRequest('DELETE', 'api/v1/tasks/' . $taskAPublicId, [], $headers);
    liveRequest('DELETE', 'api/v1/tasks/' . $taskBPublicId, [], $headers);
    liveRequest('DELETE', 'api/v1/projects/' . $projectPublicId, [], $headers);

    liveRequest('PATCH', 'api/v1/users/' . $miscRootPublicId, [
        'is_root' => 0,
    ], $rootHeaders);
    liveRequest('DELETE', 'api/v1/users/' . $miscRootPublicId, [], $rootHeaders);

    echo "[OK] advanced_misc_locale_mixed_live\n";
} catch (Throwable $e) {
    fwrite(STDERR, '[FAIL] advanced_misc_locale_mixed_live: ' . $e->getMessage() . "\n");
    exit(1);
}
