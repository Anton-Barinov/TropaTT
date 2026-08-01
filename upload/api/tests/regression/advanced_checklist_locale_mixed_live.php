<?php
declare(strict_types=1);

require_once __DIR__ . '/../_live_http.php';

/** @param mixed $value */
function assertNoCyrillicChecklist(mixed $value, string $context): void
{
    if (is_string($value)) {
        liveAssert(!preg_match('/\p{Cyrillic}/u', $value), $context . ': value contains Cyrillic');
        return;
    }

    if (is_array($value)) {
        foreach ($value as $k => $v) {
            assertNoCyrillicChecklist($v, $context . '.' . (string)$k);
        }
    }
}

try {
    $root = liveLoginRoot();
    $rootHeaders = ['Authorization' => 'Bearer ' . $root['token']];
    $suffix = strtolower(gmdate('YmdHis') . '_' . bin2hex(random_bytes(3)));

    $roleCreate = liveRequest('POST', 'api/v1/roles', [
        'code' => 'chk_locale_' . $suffix,
        'title' => 'Checklist Locale ' . $suffix,
    ], $rootHeaders);
    liveAssert($roleCreate['status'] === 201, 'Role create must return 201');
    $rolePublicId = (string)($roleCreate['payload']['data']['role']['public_id'] ?? '');
    liveAssert($rolePublicId !== '', 'Role public_id is required');

    $setPerms = liveRequest('PUT', 'api/v1/roles/' . $rolePublicId . '/permissions', [
        'permission_codes' => ['project.manage', 'task.manage'],
    ], $rootHeaders);
    liveAssert($setPerms['status'] === 200, 'Role permissions set must return 200');

    $login = 'chk_locale_' . $suffix;
    $token = 'chk-locale-token-' . $suffix;
    $userCreate = liveRequest('POST', 'api/v1/users', [
        'login' => $login,
        'password' => 'ChkLocale123!',
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
        'password' => 'ChkLocale123!',
        'token' => $token,
    ]);
    liveAssert($userLogin['status'] === 200, 'User login must return 200');
    $userToken = (string)($userLogin['payload']['data']['access_token'] ?? '');
    liveAssert($userToken !== '', 'User token is required');

    $headers = [
        'Authorization' => 'Bearer ' . $userToken,
        'X-Locale' => 'ru-ru',
    ];

    $project = liveRequest('POST', 'api/v1/projects', [
        'title' => 'Checklist Locale Project ' . $suffix,
    ], $headers);
    liveAssert($project['status'] === 201, 'Project create must return 201');
    $projectPublicId = (string)($project['payload']['data']['project']['public_id'] ?? '');
    liveAssert($projectPublicId !== '', 'Project public_id is required');

    $task = liveRequest('POST', 'api/v1/tasks', [
        'title' => 'Checklist Locale Task ' . $suffix,
        'project_public_id' => $projectPublicId,
        'status' => 'new',
        'priority' => 'normal',
    ], $headers);
    liveAssert($task['status'] === 201, 'Task create must return 201');
    $taskPublicId = (string)($task['payload']['data']['task']['public_id'] ?? '');
    liveAssert($taskPublicId !== '', 'Task public_id is required');

    $checklistValidation = liveRequest('POST', 'api/v1/tasks/' . $taskPublicId . '/checklists', [], $headers);
    liveAssert($checklistValidation['status'] === 422, 'Checklist validation must return 422');
    liveAssert((string)($checklistValidation['payload']['message'] ?? '') === 'Validation error', 'Checklist validation message mismatch');
    assertNoCyrillicChecklist($checklistValidation['payload']['errors'] ?? [], 'checklist.validation.errors');

    $checklistCreate = liveRequest('POST', 'api/v1/tasks/' . $taskPublicId . '/checklists', [
        'title' => 'Checklist ' . $suffix,
    ], $headers);
    liveAssert($checklistCreate['status'] === 201, 'Checklist create must return 201');
    liveAssert((string)($checklistCreate['payload']['message'] ?? '') === 'Checklist created', 'Checklist create message mismatch');
    $checklistPublicId = (string)($checklistCreate['payload']['data']['checklist']['public_id'] ?? '');
    liveAssert($checklistPublicId !== '', 'Checklist public_id is required');

    $checklistList = liveRequest('GET', 'api/v1/tasks/' . $taskPublicId . '/checklists', [], $headers);
    liveAssert($checklistList['status'] === 200, 'Checklist list must return 200');
    liveAssert((string)($checklistList['payload']['message'] ?? '') === 'Checklist list', 'Checklist list message mismatch');

    $checklistDetail = liveRequest('GET', 'api/v1/checklists/' . $checklistPublicId, [], $headers);
    liveAssert($checklistDetail['status'] === 200, 'Checklist detail must return 200');
    liveAssert((string)($checklistDetail['payload']['message'] ?? '') === 'Checklist details', 'Checklist detail message mismatch');

    $itemValidation = liveRequest('POST', 'api/v1/checklists/' . $checklistPublicId . '/items', [], $headers);
    liveAssert($itemValidation['status'] === 422, 'Checklist item validation must return 422');
    liveAssert((string)($itemValidation['payload']['message'] ?? '') === 'Validation error', 'Checklist item validation message mismatch');
    assertNoCyrillicChecklist($itemValidation['payload']['errors'] ?? [], 'checklist.item.validation.errors');

    $itemCreate = liveRequest('POST', 'api/v1/checklists/' . $checklistPublicId . '/items', [
        'title' => 'Checklist item ' . $suffix,
    ], $headers);
    liveAssert($itemCreate['status'] === 201, 'Checklist item create must return 201');
    liveAssert((string)($itemCreate['payload']['message'] ?? '') === 'Checklist item created', 'Checklist item create message mismatch');
    $itemPublicId = (string)($itemCreate['payload']['data']['item']['public_id'] ?? '');
    liveAssert($itemPublicId !== '', 'Checklist item public_id is required');

    $itemNotFound = liveRequest('GET', 'api/v1/checklist-items/itm_missing_' . $suffix, [], $headers);
    liveAssert($itemNotFound['status'] === 404, 'Checklist item not found must return 404');
    liveAssert((string)($itemNotFound['payload']['message'] ?? '') === 'Checklist item not found', 'Checklist item not found message mismatch');

    $checklistNotFound = liveRequest('GET', 'api/v1/checklists/chk_missing_' . $suffix, [], $headers);
    liveAssert($checklistNotFound['status'] === 404, 'Checklist not found must return 404');
    liveAssert((string)($checklistNotFound['payload']['message'] ?? '') === 'Checklist not found', 'Checklist not found message mismatch');

    liveRequest('DELETE', 'api/v1/checklist-items/' . $itemPublicId, [], $headers);
    liveRequest('DELETE', 'api/v1/checklists/' . $checklistPublicId, [], $headers);
    liveRequest('DELETE', 'api/v1/users/' . $userPublicId, [], $rootHeaders);
    liveRequest('DELETE', 'api/v1/roles/' . $rolePublicId, [], $rootHeaders);

    echo "[OK] advanced_checklist_locale_mixed_live\n";
} catch (Throwable $e) {
    fwrite(STDERR, '[FAIL] advanced_checklist_locale_mixed_live: ' . $e->getMessage() . "\n");
    exit(1);
}
