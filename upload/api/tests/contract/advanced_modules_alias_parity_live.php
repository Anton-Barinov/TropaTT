<?php
declare(strict_types=1);

require_once __DIR__ . '/../_live_http.php';

try {
    $root = liveLoginRoot();
    $headers = ['Authorization' => 'Bearer ' . $root['token']];

    $pairs = [
        ['canonical' => 'api/v1/template/tasks', 'alias' => 'api/v1/template/task/list', 'code' => 'TASK_TEMPLATE_LIST'],
        ['canonical' => 'api/v1/template/projects', 'alias' => 'api/v1/template/project/list', 'code' => 'PROJECT_TEMPLATE_LIST'],
        ['canonical' => 'api/v1/recurring', 'alias' => 'api/v1/recurring/list', 'code' => 'RECURRING_LIST'],
        ['canonical' => 'api/v1/custom-fields', 'alias' => 'api/v1/custom-field/list', 'code' => 'CUSTOM_FIELD_LIST'],
        ['canonical' => 'api/v1/workflow/rules', 'alias' => 'api/v1/workflow/rule/list', 'code' => 'WORKFLOW_RULE_LIST'],
        ['canonical' => 'api/v1/sla/policies', 'alias' => 'api/v1/sla/list', 'code' => 'SLA_POLICY_LIST'],
        ['canonical' => 'api/v1/sla/report', 'alias' => 'api/v1/sla/report/get', 'code' => 'SLA_REPORT'],
        ['canonical' => 'api/v1/approvals', 'alias' => 'api/v1/approval/list', 'code' => 'APPROVAL_LIST'],
        ['canonical' => 'api/v1/recycle-bin', 'alias' => 'api/v1/recycle-bin/list', 'code' => 'RECYCLE_BIN_LIST'],
    ];

    foreach ($pairs as $pair) {
        $canonical = liveRequest('GET', $pair['canonical'], [], $headers);
        $alias = liveRequest('GET', $pair['alias'], [], $headers);

        liveAssert($canonical['status'] === 200, 'Canonical route must return 200: ' . $pair['canonical']);
        liveAssert($alias['status'] === 200, 'Alias route must return 200: ' . $pair['alias']);
        liveAssert((string)($canonical['payload']['code'] ?? '') === (string)$pair['code'], 'Canonical code mismatch for ' . $pair['canonical']);
        liveAssert((string)($alias['payload']['code'] ?? '') === (string)$pair['code'], 'Alias code mismatch for ' . $pair['alias']);
    }

    echo "[OK] advanced_modules_alias_parity_live\n";
} catch (Throwable $e) {
    fwrite(STDERR, '[FAIL] advanced_modules_alias_parity_live: ' . $e->getMessage() . "\n");
    exit(1);
}
