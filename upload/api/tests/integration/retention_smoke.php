<?php
declare(strict_types=1);

require __DIR__ . '/_bootstrap.php';

function runRetentionSmoke(): void
{
    $auth = loginRoot();
    $headers = authHeaders($auth['token']);

    $get = request('GET', '/api/v1/retention/metadata', [], $headers);
    assertTrue($get['status'] === 200, 'Retention metadata get must be 200');

    $set = request('PATCH', '/api/v1/retention/metadata', [
        'enabled' => true,
        'request_logs_days' => 120,
        'security_logs_days' => 400,
        'recycle_bin_days' => 45,
    ], $headers);
    assertTrue($set['status'] === 200, 'Retention metadata update must be 200');

    $getAfter = request('GET', '/api/v1/retention/metadata', [], $headers);
    assertTrue($getAfter['status'] === 200, 'Retention metadata get after update must be 200');

    $unauthorized = request('GET', '/api/v1/retention/metadata');
    assertTrue($unauthorized['status'] === 401, 'Retention metadata without token must be 401');
}

runRetentionSmoke();
echo "[OK] retention_smoke\n";
