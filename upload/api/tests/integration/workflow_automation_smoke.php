<?php
declare(strict_types=1);

require __DIR__ . '/_bootstrap.php';

function runWorkflowAutomationSmoke(): void
{
    $auth = loginRoot();
    $headers = authHeaders($auth['token']);
    $suffix = randomSuffix();

    $create = request('POST', '/api/v1/workflow/rules', [
        'title' => 'WF ' . $suffix,
        'trigger_code' => 'task_created',
        'action_code' => 'send_notification',
        'payload' => ['channel' => 'in_app', 'template' => 'task-created'],
        'is_enabled' => 1,
    ], $headers);
    assertTrue($create['status'] === 201, 'Workflow create status must be 201');
    $rulePublicId = (string)($create['payload']['data']['rule']['public_id'] ?? '');
    assertTrue($rulePublicId !== '', 'Workflow public_id is required');

    $list = request('GET', '/api/v1/workflow/rules?search=' . rawurlencode($suffix), [], $headers);
    assertTrue($list['status'] === 200, 'Workflow list status must be 200');

    $get = request('GET', '/api/v1/workflow/rules/' . $rulePublicId, [], $headers);
    assertTrue($get['status'] === 200, 'Workflow get status must be 200');

    $update = request('PATCH', '/api/v1/workflow/rules/' . $rulePublicId, [
        'action_code' => 'create_comment',
        'is_enabled' => 0,
        'payload' => ['message' => 'Follow-up required'],
    ], $headers);
    assertTrue($update['status'] === 200, 'Workflow update status must be 200');
    assertTrue(($update['payload']['data']['rule']['is_enabled'] ?? true) === false, 'Workflow rule must be disabled');

    $runSuccess = request('POST', '/api/v1/workflow/rules/' . $rulePublicId . '/run-test', [], $headers);
    assertTrue($runSuccess['status'] === 201, 'Workflow run-test success status must be 201');

    $runFailed = request('POST', '/api/v1/workflow/rules/' . $rulePublicId . '/run-test', [
        'simulate_error' => 1,
        'error_message' => 'forced test error',
    ], $headers);
    assertTrue($runFailed['status'] === 201, 'Workflow run-test failed status must be 201');

    $runs = request('GET', '/api/v1/workflow/runs?rule_public_id=' . rawurlencode($rulePublicId), [], $headers);
    assertTrue($runs['status'] === 200, 'Workflow runs status must be 200');
    assertTrue(((int)($runs['payload']['meta']['pagination']['total'] ?? 0)) >= 2, 'Workflow runs total must be >= 2');

    $aliasList = request('GET', '/api/v1/workflow/rule/list?search=' . rawurlencode($suffix), [], $headers);
    assertTrue($aliasList['status'] === 200, 'Workflow alias list status must be 200');

    $aliasLogs = request('GET', '/api/v1/workflow/rule/logs?rule_public_id=' . rawurlencode($rulePublicId), [], $headers);
    assertTrue($aliasLogs['status'] === 200, 'Workflow alias logs status must be 200');

    $delete = request('DELETE', '/api/v1/workflow/rules/' . $rulePublicId, [], $headers);
    assertTrue($delete['status'] === 200, 'Workflow delete status must be 200');

    $unauthorized = request('GET', '/api/v1/workflow/rules');
    assertTrue($unauthorized['status'] === 401, 'Workflow list without token must return 401');

    $invalid = request('POST', '/api/v1/workflow/rules', [
        'title' => 'bad',
        'trigger_code' => 'invalid_trigger',
        'action_code' => 'invalid_action',
    ], $headers);
    assertTrue($invalid['status'] === 422, 'Workflow invalid enum status must be 422');
}

runWorkflowAutomationSmoke();
echo "[OK] workflow_automation_smoke\n";
