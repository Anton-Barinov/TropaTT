<?php
declare(strict_types=1);

require __DIR__ . '/_bootstrap.php';

function runSlaSmoke(): void
{
    $auth = loginRoot();
    $headers = authHeaders($auth['token']);
    $suffix = randomSuffix();

    $create = request('POST', '/api/v1/sla/policies', [
        'title' => 'SLA ' . $suffix,
        'response_minutes' => 30,
        'resolve_minutes' => 240,
        'escalation_payload' => ['chain' => ['team-lead', 'cto']],
    ], $headers);
    assertTrue($create['status'] === 201, 'SLA create status must be 201');
    $policyPublicId = (string)($create['payload']['data']['policy']['public_id'] ?? '');
    assertTrue($policyPublicId !== '', 'SLA policy public_id is required');

    $list = request('GET', '/api/v1/sla/policies?search=' . rawurlencode($suffix), [], $headers);
    assertTrue($list['status'] === 200, 'SLA list status must be 200');

    $get = request('GET', '/api/v1/sla/policies/' . $policyPublicId, [], $headers);
    assertTrue($get['status'] === 200, 'SLA get status must be 200');

    $update = request('PATCH', '/api/v1/sla/policies/' . $policyPublicId, [
        'resolve_minutes' => 180,
    ], $headers);
    assertTrue($update['status'] === 200, 'SLA update status must be 200');

    $report = request('GET', '/api/v1/sla/report', [], $headers);
    assertTrue($report['status'] === 200, 'SLA report status must be 200');

    $aliasList = request('GET', '/api/v1/sla/list?search=' . rawurlencode($suffix), [], $headers);
    assertTrue($aliasList['status'] === 200, 'SLA alias list status must be 200');

    $aliasReport = request('GET', '/api/v1/sla/report/get', [], $headers);
    assertTrue($aliasReport['status'] === 200, 'SLA alias report status must be 200');

    $delete = request('DELETE', '/api/v1/sla/policies/' . $policyPublicId, [], $headers);
    assertTrue($delete['status'] === 200, 'SLA delete status must be 200');

    $unauthorized = request('GET', '/api/v1/sla/policies');
    assertTrue($unauthorized['status'] === 401, 'SLA list without token must return 401');

    $invalid = request('POST', '/api/v1/sla/policies', [
        'title' => 'bad',
        'response_minutes' => 0,
        'resolve_minutes' => 0,
    ], $headers);
    assertTrue($invalid['status'] === 422, 'SLA validation status must be 422');
}

runSlaSmoke();
echo "[OK] sla_smoke\n";
