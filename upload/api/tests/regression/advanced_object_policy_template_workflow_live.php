<?php
declare(strict_types=1);

require_once __DIR__ . '/../_live_http.php';

try {
    $root = liveLoginRoot();
    $rootHeaders = ['Authorization' => 'Bearer ' . $root['token']];
    $suffix = strtolower(gmdate('YmdHis') . '_' . bin2hex(random_bytes(3)));

    $roleCreate = liveRequest('POST', 'api/v1/roles', [
        'code' => 'obj_twf_' . $suffix,
        'title' => 'Object Template Workflow ' . $suffix,
    ], $rootHeaders);
    liveAssert($roleCreate['status'] === 201, 'Role create must return 201');
    $rolePublicId = (string)($roleCreate['payload']['data']['role']['public_id'] ?? '');
    liveAssert($rolePublicId !== '', 'Role public_id is required');

    $setPerms = liveRequest('PUT', 'api/v1/roles/' . $rolePublicId . '/permissions', [
        'permission_codes' => ['task.manage', 'project.manage', 'settings.manage'],
    ], $rootHeaders);
    liveAssert($setPerms['status'] === 200, 'Role permissions set must return 200');

    $ownerLogin = 'obj_twf_owner_' . $suffix;
    $ownerTokenFactor = 'obj-twf-owner-token-' . $suffix;
    $ownerCreate = liveRequest('POST', 'api/v1/users', [
        'login' => $ownerLogin,
        'password' => 'ObjTwfOwner123!',
        'token' => $ownerTokenFactor,
        'email' => $ownerLogin . '@crm.local',
        'role_public_ids' => [$rolePublicId],
    ], $rootHeaders);
    liveAssert($ownerCreate['status'] === 201, 'Owner user create must return 201');
    $ownerUserPublicId = (string)($ownerCreate['payload']['data']['user']['public_id'] ?? '');

    $viewerLogin = 'obj_twf_viewer_' . $suffix;
    $viewerTokenFactor = 'obj-twf-viewer-token-' . $suffix;
    $viewerCreate = liveRequest('POST', 'api/v1/users', [
        'login' => $viewerLogin,
        'password' => 'ObjTwfViewer123!',
        'token' => $viewerTokenFactor,
        'email' => $viewerLogin . '@crm.local',
        'role_public_ids' => [$rolePublicId],
    ], $rootHeaders);
    liveAssert($viewerCreate['status'] === 201, 'Viewer user create must return 201');
    $viewerUserPublicId = (string)($viewerCreate['payload']['data']['user']['public_id'] ?? '');

    $ownerLoginResp = liveRequest('POST', 'api/v1/auth/login', [
        'login' => $ownerLogin,
        'password' => 'ObjTwfOwner123!',
        'token' => $ownerTokenFactor,
    ]);
    liveAssert($ownerLoginResp['status'] === 200, 'Owner login must return 200');
    $ownerHeaders = ['Authorization' => 'Bearer ' . (string)($ownerLoginResp['payload']['data']['access_token'] ?? '')];

    $viewerLoginResp = liveRequest('POST', 'api/v1/auth/login', [
        'login' => $viewerLogin,
        'password' => 'ObjTwfViewer123!',
        'token' => $viewerTokenFactor,
    ]);
    liveAssert($viewerLoginResp['status'] === 200, 'Viewer login must return 200');
    $viewerHeaders = ['Authorization' => 'Bearer ' . (string)($viewerLoginResp['payload']['data']['access_token'] ?? '')];

    $ownerTaskTemplate = liveRequest('POST', 'api/v1/template/tasks', [
        'title' => 'Owner Task Template ' . $suffix,
        'payload' => ['checklist' => ['step1']],
    ], $ownerHeaders);
    liveAssert($ownerTaskTemplate['status'] === 201, 'Owner task template create must return 201');
    $ownerTaskTemplateId = (string)($ownerTaskTemplate['payload']['data']['template']['public_id'] ?? '');

    $ownerProjectTemplate = liveRequest('POST', 'api/v1/template/projects', [
        'title' => 'Owner Project Template ' . $suffix,
        'payload' => ['phases' => ['phase1']],
    ], $ownerHeaders);
    liveAssert($ownerProjectTemplate['status'] === 201, 'Owner project template create must return 201');
    $ownerProjectTemplateId = (string)($ownerProjectTemplate['payload']['data']['template']['public_id'] ?? '');

    $ownerWorkflowRule = liveRequest('POST', 'api/v1/workflow/rules', [
        'title' => 'Owner Workflow Rule ' . $suffix,
        'trigger_code' => 'task_created',
        'action_code' => 'send_notification',
        'payload' => ['channel' => 'in_app'],
    ], $ownerHeaders);
    liveAssert($ownerWorkflowRule['status'] === 201, 'Owner workflow rule create must return 201');
    $ownerWorkflowRuleId = (string)($ownerWorkflowRule['payload']['data']['rule']['public_id'] ?? '');

    liveAssert(liveRequest('GET', 'api/v1/template/tasks/' . $ownerTaskTemplateId, [], $viewerHeaders)['status'] === 404, 'Viewer must not access owner task template');
    liveAssert(liveRequest('GET', 'api/v1/template/projects/' . $ownerProjectTemplateId, [], $viewerHeaders)['status'] === 404, 'Viewer must not access owner project template');
    liveAssert(liveRequest('GET', 'api/v1/workflow/rules/' . $ownerWorkflowRuleId, [], $viewerHeaders)['status'] === 404, 'Viewer must not access owner workflow rule');

    liveAssert(liveRequest('PATCH', 'api/v1/template/tasks/' . $ownerTaskTemplateId, ['title' => 'Forbidden'], $viewerHeaders)['status'] === 404, 'Viewer must not update owner task template');
    liveAssert(liveRequest('PATCH', 'api/v1/template/projects/' . $ownerProjectTemplateId, ['title' => 'Forbidden'], $viewerHeaders)['status'] === 404, 'Viewer must not update owner project template');
    liveAssert(liveRequest('PATCH', 'api/v1/workflow/rules/' . $ownerWorkflowRuleId, ['title' => 'Forbidden'], $viewerHeaders)['status'] === 404, 'Viewer must not update owner workflow rule');

    liveAssert(liveRequest('DELETE', 'api/v1/template/tasks/' . $ownerTaskTemplateId, [], $viewerHeaders)['status'] === 404, 'Viewer must not delete owner task template');
    liveAssert(liveRequest('DELETE', 'api/v1/template/projects/' . $ownerProjectTemplateId, [], $viewerHeaders)['status'] === 404, 'Viewer must not delete owner project template');
    liveAssert(liveRequest('DELETE', 'api/v1/workflow/rules/' . $ownerWorkflowRuleId, [], $viewerHeaders)['status'] === 404, 'Viewer must not delete owner workflow rule');

    $viewerTaskList = liveRequest('GET', 'api/v1/template/tasks', [], $viewerHeaders);
    liveAssert($viewerTaskList['status'] === 200, 'Viewer task templates list must return 200');
    foreach (($viewerTaskList['payload']['data']['items'] ?? []) as $item) {
        liveAssert((string)($item['public_id'] ?? '') !== $ownerTaskTemplateId, 'Viewer list must not include owner task template');
    }

    $viewerProjectList = liveRequest('GET', 'api/v1/template/projects', [], $viewerHeaders);
    liveAssert($viewerProjectList['status'] === 200, 'Viewer project templates list must return 200');
    foreach (($viewerProjectList['payload']['data']['items'] ?? []) as $item) {
        liveAssert((string)($item['public_id'] ?? '') !== $ownerProjectTemplateId, 'Viewer list must not include owner project template');
    }

    $viewerWorkflowList = liveRequest('GET', 'api/v1/workflow/rules', [], $viewerHeaders);
    liveAssert($viewerWorkflowList['status'] === 200, 'Viewer workflow list must return 200');
    foreach (($viewerWorkflowList['payload']['data']['items'] ?? []) as $item) {
        liveAssert((string)($item['public_id'] ?? '') !== $ownerWorkflowRuleId, 'Viewer list must not include owner workflow rule');
    }

    liveAssert(liveRequest('GET', 'api/v1/template/tasks/' . $ownerTaskTemplateId, [], $rootHeaders)['status'] === 200, 'Root must access owner task template');
    liveAssert(liveRequest('GET', 'api/v1/workflow/rules/' . $ownerWorkflowRuleId, [], $rootHeaders)['status'] === 200, 'Root must access owner workflow rule');

    liveRequest('DELETE', 'api/v1/template/tasks/' . $ownerTaskTemplateId, [], $ownerHeaders);
    liveRequest('DELETE', 'api/v1/template/projects/' . $ownerProjectTemplateId, [], $ownerHeaders);
    liveRequest('DELETE', 'api/v1/workflow/rules/' . $ownerWorkflowRuleId, [], $ownerHeaders);

    liveRequest('DELETE', 'api/v1/users/' . $ownerUserPublicId, [], $rootHeaders);
    liveRequest('DELETE', 'api/v1/users/' . $viewerUserPublicId, [], $rootHeaders);
    liveRequest('DELETE', 'api/v1/roles/' . $rolePublicId, [], $rootHeaders);

    echo "[OK] advanced_object_policy_template_workflow_live\n";
} catch (Throwable $e) {
    fwrite(STDERR, '[FAIL] advanced_object_policy_template_workflow_live: ' . $e->getMessage() . "\n");
    exit(1);
}
