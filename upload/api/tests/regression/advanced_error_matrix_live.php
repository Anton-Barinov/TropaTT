<?php
declare(strict_types=1);

require_once __DIR__ . '/../_live_http.php';

try {
    $unauthorizedCases = [
        ['method' => 'GET', 'route' => 'api/v1/workflow/rules'],
        ['method' => 'GET', 'route' => 'api/v1/sla/policies'],
        ['method' => 'GET', 'route' => 'api/v1/approvals'],
        ['method' => 'GET', 'route' => 'api/v1/recycle-bin'],
        ['method' => 'GET', 'route' => 'api/v1/import/jobs'],
        ['method' => 'GET', 'route' => 'api/v1/export/jobs'],
    ];

    foreach ($unauthorizedCases as $case) {
        $response = liveRequest((string)$case['method'], (string)$case['route']);
        liveAssert($response['status'] === 401, 'Route must require auth: ' . $case['method'] . ' ' . $case['route']);
        liveAssert((string)($response['payload']['code'] ?? '') === 'UNAUTHORIZED', 'UNAUTHORIZED code expected for ' . $case['route']);
    }

    $root = liveLoginRoot();
    $headers = ['Authorization' => 'Bearer ' . $root['token']];
    $missingId = 'missing_' . strtolower(gmdate('YmdHis')) . '_' . bin2hex(random_bytes(2));

    $missingCases = [
        ['method' => 'GET', 'route' => 'api/v1/workflow/rules/wfr_' . $missingId, 'code' => 'WORKFLOW_RULE_NOT_FOUND'],
        ['method' => 'PATCH', 'route' => 'api/v1/workflow/rules/wfr_' . $missingId, 'code' => 'WORKFLOW_RULE_NOT_FOUND', 'payload' => ['title' => 'X']],
        ['method' => 'DELETE', 'route' => 'api/v1/workflow/rules/wfr_' . $missingId, 'code' => 'WORKFLOW_RULE_NOT_FOUND'],
        ['method' => 'POST', 'route' => 'api/v1/workflow/rules/wfr_' . $missingId . '/run-test', 'code' => 'WORKFLOW_RULE_NOT_FOUND', 'payload' => ['simulate_error' => 0]],

        ['method' => 'GET', 'route' => 'api/v1/sla/policies/sla_' . $missingId, 'code' => 'SLA_POLICY_NOT_FOUND'],
        ['method' => 'PATCH', 'route' => 'api/v1/sla/policies/sla_' . $missingId, 'code' => 'SLA_POLICY_NOT_FOUND', 'payload' => ['title' => 'X']],
        ['method' => 'DELETE', 'route' => 'api/v1/sla/policies/sla_' . $missingId, 'code' => 'SLA_POLICY_NOT_FOUND'],

        ['method' => 'GET', 'route' => 'api/v1/approvals/apr_' . $missingId, 'code' => 'APPROVAL_NOT_FOUND'],
        ['method' => 'POST', 'route' => 'api/v1/approvals/apr_' . $missingId . '/approve', 'code' => 'APPROVAL_NOT_FOUND', 'payload' => ['comment' => 'x']],
        ['method' => 'POST', 'route' => 'api/v1/approvals/apr_' . $missingId . '/reject', 'code' => 'APPROVAL_NOT_FOUND', 'payload' => ['comment' => 'x']],

        ['method' => 'PATCH', 'route' => 'api/v1/feature-flags/ff_' . $missingId, 'code' => 'FEATURE_FLAG_NOT_FOUND', 'payload' => ['enabled' => false]],
        ['method' => 'GET', 'route' => 'api/v1/import/jobs/imp_' . $missingId, 'code' => 'IMPORT_JOB_NOT_FOUND'],
        ['method' => 'GET', 'route' => 'api/v1/export/jobs/exp_' . $missingId, 'code' => 'EXPORT_JOB_NOT_FOUND'],
    ];

    foreach ($missingCases as $case) {
        $response = liveRequest(
            (string)$case['method'],
            (string)$case['route'],
            (array)($case['payload'] ?? []),
            $headers
        );
        liveAssert($response['status'] === 404, 'Missing entity must return 404: ' . $case['method'] . ' ' . $case['route']);
        liveAssert((string)($response['payload']['code'] ?? '') === (string)$case['code'], 'Error code mismatch for ' . $case['route']);
    }

    echo "[OK] advanced_error_matrix_live\n";
} catch (Throwable $e) {
    fwrite(STDERR, '[FAIL] advanced_error_matrix_live: ' . $e->getMessage() . "\n");
    exit(1);
}

