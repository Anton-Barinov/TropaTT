<?php
declare(strict_types=1);

require __DIR__ . '/_bootstrap.php';

function runRecurringSmoke(): void
{
    $auth = loginRoot();
    $headers = authHeaders($auth['token']);
    $suffix = randomSuffix();

    $create = request('POST', '/api/v1/recurring', [
        'entity_type' => 'task',
        'entity_public_id' => 'tsk_demo_' . $suffix,
        'rrule' => 'FREQ=DAILY;INTERVAL=1',
        'is_active' => 1,
    ], $headers);
    assertTrue($create['status'] === 201, 'Recurring create status must be 201');
    $rulePublicId = (string)($create['payload']['data']['rule']['public_id'] ?? '');
    assertTrue($rulePublicId !== '', 'Recurring public_id is required');

    $get = request('GET', '/api/v1/recurring/' . $rulePublicId, [], $headers);
    assertTrue($get['status'] === 200, 'Recurring get status must be 200');

    $list = request('GET', '/api/v1/recurring?entity_type=task&search=' . rawurlencode($suffix) . '&limit=5', [], $headers);
    assertTrue($list['status'] === 200, 'Recurring list status must be 200');

    $pause = request('POST', '/api/v1/recurring/' . $rulePublicId . '/pause', [], $headers);
    assertTrue($pause['status'] === 200, 'Recurring pause status must be 200');
    assertTrue(($pause['payload']['data']['rule']['is_active'] ?? true) === false, 'Recurring should be inactive after pause');

    $resume = request('POST', '/api/v1/recurring/' . $rulePublicId . '/resume', [], $headers);
    assertTrue($resume['status'] === 200, 'Recurring resume status must be 200');
    assertTrue(($resume['payload']['data']['rule']['is_active'] ?? false) === true, 'Recurring should be active after resume');

    $update = request('PATCH', '/api/v1/recurring/' . $rulePublicId, [
        'rrule' => 'FREQ=WEEKLY;INTERVAL=1',
    ], $headers);
    assertTrue($update['status'] === 200, 'Recurring update status must be 200');

    $alias = request('GET', '/api/v1/recurring/list?limit=5&search=' . rawurlencode($suffix), [], $headers);
    assertTrue($alias['status'] === 200, 'Recurring alias list status must be 200');

    $delete = request('DELETE', '/api/v1/recurring/' . $rulePublicId, [], $headers);
    assertTrue($delete['status'] === 200, 'Recurring delete status must be 200');

    $unauthorized = request('GET', '/api/v1/recurring');
    assertTrue($unauthorized['status'] === 401, 'Recurring route without token must return 401');

    $invalid = request('POST', '/api/v1/recurring', [
        'entity_type' => 'invalid',
        'entity_public_id' => 'x',
        'rrule' => 'FREQ=DAILY',
    ], $headers);
    assertTrue($invalid['status'] === 422, 'Recurring invalid entity_type must return 422');
}

runRecurringSmoke();
echo "[OK] recurring_smoke\n";
