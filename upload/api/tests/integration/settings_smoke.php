<?php
declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';

try {
    $auth = loginRoot();
    $headers = authHeaders($auth['token']);

    $set = request('PUT', '/api/v1/settings/ui.compact', [
        'scope' => 'system',
        'value' => ['enabled' => true],
    ], $headers);
    assertTrue($set['status'] === 200, 'Settings set status must be 200');
    assertTrue(($set['payload']['code'] ?? '') === 'SETTING_SET', 'Settings set code mismatch');

    $get = request('GET', '/api/v1/settings/ui.compact?scope=system', [], $headers);
    assertTrue($get['status'] === 200, 'Settings get status must be 200');
    assertTrue(($get['payload']['code'] ?? '') === 'SETTING_GET', 'Settings get code mismatch');

    $list = request('GET', '/api/v1/settings?scope=system&search=ui.&limit=10', [], $headers);
    assertTrue($list['status'] === 200, 'Settings list status must be 200');
    assertTrue(($list['payload']['code'] ?? '') === 'SETTING_LIST', 'Settings list code mismatch');

    $aliasSet = request('POST', '/api/v1/setting/set', [
        'scope' => 'system',
        'name' => 'ui.sidebar',
        'value' => 'expanded',
    ], $headers);
    assertTrue($aliasSet['status'] === 200, 'Setting alias set status must be 200');

    $aliasGet = request('GET', '/api/v1/setting/get/ui.sidebar?scope=system', [], $headers);
    assertTrue($aliasGet['status'] === 200, 'Setting alias get status must be 200');

    $unauthorized = request('GET', '/api/v1/settings');
    assertTrue($unauthorized['status'] === 401, 'Settings unauthorized status must be 401');

    echo "[OK] Settings smoke passed\n";
    exit(0);
} catch (Throwable $e) {
    fwrite(STDERR, '[FAIL] ' . $e->getMessage() . "\n");
    exit(1);
}
