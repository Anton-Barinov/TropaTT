<?php
declare(strict_types=1);

require __DIR__ . '/../_live_http.php';

function runRetentionOrganizationFeatureLive(): void
{
    $auth = liveLoginRoot();
    $headers = ['Authorization' => 'Bearer ' . $auth['token']];
    $suffix = gmdate('YmdHis') . '_' . bin2hex(random_bytes(3));

    $createOrganization = liveRequest('POST', 'api/v1/organizations', [
        'title' => 'Feature Workspace ' . $suffix,
        'slug' => 'feature-workspace-' . strtolower($suffix),
    ], $headers);
    liveAssert($createOrganization['status'] === 201, 'Organization create must return 201');
    $organizationPublicId = (string)($createOrganization['payload']['data']['organization']['public_id'] ?? '');
    liveAssert($organizationPublicId !== '', 'Organization public_id is required');

    $membersList = liveRequest('GET', 'api/v1/organizations/' . $organizationPublicId . '/members', [], $headers);
    liveAssert($membersList['status'] === 200, 'Organization members list must return 200');
    $items = $membersList['payload']['data']['items'] ?? [];
    liveAssert(is_array($items), 'Members items must be array');
    liveAssert(count($items) >= 1, 'Members list must contain at least one user');

    $setRetention = liveRequest('PATCH', 'api/v1/retention/metadata', [
        'enabled' => true,
        'request_logs_days' => 111,
        'security_logs_days' => 222,
        'recycle_bin_days' => 33,
    ], $headers);
    liveAssert($setRetention['status'] === 200, 'Retention PATCH must return 200');

    $getRetention = liveRequest('GET', 'api/v1/retention/metadata', [], $headers);
    liveAssert($getRetention['status'] === 200, 'Retention GET must return 200');
    $retention = $getRetention['payload']['data']['retention'] ?? [];
    liveAssert((int)($retention['request_logs_days'] ?? 0) === 111, 'Retention request_logs_days must persist');
    liveAssert((int)($retention['security_logs_days'] ?? 0) === 222, 'Retention security_logs_days must persist');
    liveAssert((int)($retention['recycle_bin_days'] ?? 0) === 33, 'Retention recycle_bin_days must persist');

    $deleteOrganization = liveRequest('DELETE', 'api/v1/organizations/' . $organizationPublicId, [], $headers);
    liveAssert($deleteOrganization['status'] === 200, 'Organization delete must return 200');
}

runRetentionOrganizationFeatureLive();
echo "[OK] retention_organization_feature_live\n";
