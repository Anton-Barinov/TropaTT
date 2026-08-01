<?php
declare(strict_types=1);

require __DIR__ . '/_bootstrap.php';

function runFeatureFlagsSmoke(): void
{
    $auth = loginRoot();
    $headers = authHeaders($auth['token']);

    $list = request('GET', '/api/v1/feature-flags', [], $headers);
    assertTrue($list['status'] === 200, 'Feature flags list must be 200');
    $items = (array)($list['payload']['data']['items'] ?? []);
    assertTrue(count($items) >= 1, 'Feature flags list must return at least one item');

    $first = (array)$items[0];
    $publicId = (string)($first['public_id'] ?? '');
    assertTrue($publicId !== '', 'Feature flag public_id is required');
    $current = (bool)($first['is_enabled'] ?? false);

    $update = request('PATCH', '/api/v1/feature-flags/' . $publicId, [
        'is_enabled' => $current ? 0 : 1,
        'payload' => ['smoke' => true],
    ], $headers);
    assertTrue($update['status'] === 200, 'Feature flag update must be 200');
    $updated = (array)($update['payload']['data']['feature_flag'] ?? []);
    assertTrue(($updated['is_enabled'] ?? $current) !== $current, 'Feature flag is_enabled must be toggled');

    $alias = request('GET', '/api/v1/feature-flags/list', [], $headers);
    assertTrue($alias['status'] === 200, 'Feature flags alias list must be 200');

    $unauthorized = request('GET', '/api/v1/feature-flags');
    assertTrue($unauthorized['status'] === 401, 'Feature flags list without token must be 401');
}

runFeatureFlagsSmoke();
echo "[OK] feature_flags_smoke\n";
