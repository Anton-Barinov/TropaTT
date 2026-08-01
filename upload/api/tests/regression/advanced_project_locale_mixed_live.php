<?php
declare(strict_types=1);

require_once __DIR__ . '/../_live_http.php';

/** @param mixed $value */
function assertNoCyrillicProject(mixed $value, string $context): void
{
    if (is_string($value)) {
        liveAssert(!preg_match('/\p{Cyrillic}/u', $value), $context . ': value contains Cyrillic');
        return;
    }

    if (is_array($value)) {
        foreach ($value as $k => $v) {
            assertNoCyrillicProject($v, $context . '.' . (string)$k);
        }
    }
}

try {
    $root = liveLoginRoot();
    $rootHeaders = ['Authorization' => 'Bearer ' . $root['token']];
    $suffix = strtolower(gmdate('YmdHis') . '_' . bin2hex(random_bytes(3)));

    $roleCreate = liveRequest('POST', 'api/v1/roles', [
        'code' => 'project_locale_' . $suffix,
        'title' => 'Project Locale ' . $suffix,
    ], $rootHeaders);
    liveAssert($roleCreate['status'] === 201, 'Role create must return 201');
    $rolePublicId = (string)($roleCreate['payload']['data']['role']['public_id'] ?? '');
    liveAssert($rolePublicId !== '', 'Role public_id is required');

    $setPerms = liveRequest('PUT', 'api/v1/roles/' . $rolePublicId . '/permissions', [
        'permission_codes' => ['project.manage'],
    ], $rootHeaders);
    liveAssert($setPerms['status'] === 200, 'Role permissions set must return 200');

    $login = 'project_locale_' . $suffix;
    $token = 'project-locale-token-' . $suffix;
    $userCreate = liveRequest('POST', 'api/v1/users', [
        'login' => $login,
        'password' => 'ProjectLocale123!',
        'token' => $token,
        'email' => $login . '@crm.local',
        'locale' => 'en-gb',
        'role_public_ids' => [$rolePublicId],
    ], $rootHeaders);
    liveAssert($userCreate['status'] === 201, 'User create must return 201');
    $userPublicId = (string)($userCreate['payload']['data']['user']['public_id'] ?? '');
    liveAssert($userPublicId !== '', 'User public_id is required');

    $userLogin = liveRequest('POST', 'api/v1/auth/login', [
        'login' => $login,
        'password' => 'ProjectLocale123!',
        'token' => $token,
    ]);
    liveAssert($userLogin['status'] === 200, 'User login must return 200');
    $userToken = (string)($userLogin['payload']['data']['access_token'] ?? '');
    liveAssert($userToken !== '', 'User token is required');

    $headers = [
        'Authorization' => 'Bearer ' . $userToken,
        'X-Locale' => 'ru-ru',
    ];

    $createValidation = liveRequest('POST', 'api/v1/projects', [], $headers);
    liveAssert($createValidation['status'] === 422, 'Project create validation must return 422');
    liveAssert((string)($createValidation['payload']['message'] ?? '') === 'Validation error', 'Project create validation message mismatch');

    $create = liveRequest('POST', 'api/v1/projects', [
        'title' => 'Project locale ' . $suffix,
    ], $headers);
    liveAssert($create['status'] === 201, 'Project create must return 201');
    liveAssert((string)($create['payload']['message'] ?? '') === 'Project created successfully', 'Project create message mismatch');
    $projectPublicId = (string)($create['payload']['data']['project']['public_id'] ?? '');
    liveAssert($projectPublicId !== '', 'Project public_id is required');

    $list = liveRequest('GET', 'api/v1/projects', ['limit' => 5], $headers);
    liveAssert($list['status'] === 200, 'Project list must return 200');
    liveAssert((string)($list['payload']['message'] ?? '') === 'Projects list', 'Project list message mismatch');

    $get = liveRequest('GET', 'api/v1/projects/' . $projectPublicId, [], $headers);
    liveAssert($get['status'] === 200, 'Project get must return 200');
    liveAssert((string)($get['payload']['message'] ?? '') === 'Project details', 'Project get message mismatch');

    $update = liveRequest('PATCH', 'api/v1/projects/' . $projectPublicId, [
        'title' => 'Project locale updated ' . $suffix,
    ], $headers);
    liveAssert($update['status'] === 200, 'Project update must return 200');
    liveAssert((string)($update['payload']['message'] ?? '') === 'Project updated', 'Project update message mismatch');

    $updateValidation = liveRequest('PATCH', 'api/v1/projects/' . $projectPublicId, [
        'title' => str_repeat('x', 256),
    ], $headers);
    liveAssert($updateValidation['status'] === 422, 'Project update validation must return 422');
    liveAssert((string)($updateValidation['payload']['message'] ?? '') === 'Validation error', 'Project update validation message mismatch');

    $timeline = liveRequest('GET', 'api/v1/projects/' . $projectPublicId . '/timeline', [], $headers);
    liveAssert($timeline['status'] === 200, 'Project timeline must return 200');
    liveAssert((string)($timeline['payload']['message'] ?? '') === 'Project timeline', 'Project timeline message mismatch');

    $summary = liveRequest('GET', 'api/v1/projects/' . $projectPublicId . '/summary', [], $headers);
    liveAssert($summary['status'] === 200, 'Project summary must return 200');
    liveAssert((string)($summary['payload']['message'] ?? '') === 'Project summary', 'Project summary message mismatch');

    $milestonesSummary = liveRequest('GET', 'api/v1/projects/' . $projectPublicId . '/milestones-summary', [], $headers);
    liveAssert($milestonesSummary['status'] === 200, 'Project milestones summary must return 200');
    liveAssert((string)($milestonesSummary['payload']['message'] ?? '') === 'Project milestones summary', 'Project milestones summary message mismatch');

    $risksSummary = liveRequest('GET', 'api/v1/projects/' . $projectPublicId . '/risks', [], $headers);
    liveAssert($risksSummary['status'] === 200, 'Project risks summary must return 200');
    liveAssert((string)($risksSummary['payload']['message'] ?? '') === 'Project risks summary', 'Project risks summary message mismatch');

    $workloadSummary = liveRequest('GET', 'api/v1/projects/' . $projectPublicId . '/workload', [], $headers);
    liveAssert($workloadSummary['status'] === 200, 'Project workload summary must return 200');
    liveAssert((string)($workloadSummary['payload']['message'] ?? '') === 'Project workload summary', 'Project workload summary message mismatch');

    $delete = liveRequest('DELETE', 'api/v1/projects/' . $projectPublicId, [], $headers);
    liveAssert($delete['status'] === 200, 'Project delete must return 200');
    liveAssert((string)($delete['payload']['message'] ?? '') === 'Project archived', 'Project delete message mismatch');

    $getAfterDelete = liveRequest('GET', 'api/v1/projects/' . $projectPublicId, [], $headers);
    liveAssert($getAfterDelete['status'] === 200, 'Project get-after-delete must return 200');
    liveAssert((string)($getAfterDelete['payload']['message'] ?? '') === 'Project details', 'Project get-after-delete message mismatch');

    assertNoCyrillicProject($createValidation['payload']['errors'] ?? [], 'project.create.validation.errors');
    assertNoCyrillicProject($updateValidation['payload']['errors'] ?? [], 'project.update.validation.errors');

    liveRequest('DELETE', 'api/v1/users/' . $userPublicId, [], $rootHeaders);
    liveRequest('DELETE', 'api/v1/roles/' . $rolePublicId, [], $rootHeaders);

    echo "[OK] advanced_project_locale_mixed_live\n";
} catch (Throwable $e) {
    fwrite(STDERR, '[FAIL] advanced_project_locale_mixed_live: ' . $e->getMessage() . "\n");
    exit(1);
}
