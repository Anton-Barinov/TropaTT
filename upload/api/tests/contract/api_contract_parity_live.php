<?php
declare(strict_types=1);

require __DIR__ . '/../_live_http.php';

function runContractParityLive(): void
{
    $auth = liveLoginRoot();
    $headers = ['Authorization' => 'Bearer ' . $auth['token']];

    $pairs = [
        ['canonical' => 'api/v1/feature-flags', 'alias' => 'api/v1/feature-flags/list', 'expected_code' => 'FEATURE_FLAG_LIST'],
        ['canonical' => 'api/v1/organizations', 'alias' => 'api/v1/organization/list', 'expected_code' => 'ORGANIZATION_LIST'],
        ['canonical' => 'api/v1/retention/metadata', 'alias' => 'api/v1/retention/get', 'expected_code' => 'RETENTION_METADATA'],
    ];

    foreach ($pairs as $pair) {
        $canonical = liveRequest('GET', $pair['canonical'], [], $headers);
        $alias = liveRequest('GET', $pair['alias'], [], $headers);

        liveAssert($canonical['status'] === 200, 'Canonical route must return 200: ' . $pair['canonical']);
        liveAssert($alias['status'] === 200, 'Alias route must return 200: ' . $pair['alias']);

        liveAssert(($canonical['payload']['success'] ?? false) === true, 'Canonical success must be true');
        liveAssert(($alias['payload']['success'] ?? false) === true, 'Alias success must be true');

        liveAssert((string)($canonical['payload']['code'] ?? '') === $pair['expected_code'], 'Canonical code mismatch for ' . $pair['canonical']);
        liveAssert((string)($alias['payload']['code'] ?? '') === $pair['expected_code'], 'Alias code mismatch for ' . $pair['alias']);

        liveAssert(isset($canonical['payload']['meta']['request_id']), 'Canonical meta.request_id is required');
        liveAssert(isset($canonical['payload']['meta']['correlation_id']), 'Canonical meta.correlation_id is required');
    }

    $projects = liveRequest('GET', 'api/v1/projects', [], $headers);
    liveAssert($projects['status'] === 200, 'Projects list must return 200');
    liveAssert(!str_contains($projects['body'], '"id":'), 'Public contract must not expose plain numeric id field');
}

runContractParityLive();
echo "[OK] api_contract_parity_live\n";
