<?php
declare(strict_types=1);

require __DIR__ . '/_bootstrap.php';

function runOrganizationSmoke(): void
{
    $auth = loginRoot();
    $headers = authHeaders($auth['token']);
    $suffix = randomSuffix();

    $create = request('POST', '/api/v1/organizations', [
        'title' => 'Workspace ' . $suffix,
        'slug' => 'workspace-' . strtolower($suffix),
    ], $headers);
    assertTrue($create['status'] === 201, 'Organization create must be 201');
    $publicId = (string)($create['payload']['data']['organization']['public_id'] ?? '');
    assertTrue($publicId !== '', 'Organization public_id is required');

    $list = request('GET', '/api/v1/organizations', [], $headers);
    assertTrue($list['status'] === 200, 'Organization list must be 200');

    $get = request('GET', '/api/v1/organizations/' . $publicId, [], $headers);
    assertTrue($get['status'] === 200, 'Organization get must be 200');

    $members = request('GET', '/api/v1/organizations/' . $publicId . '/members', [], $headers);
    assertTrue($members['status'] === 200, 'Organization members list must be 200');

    $update = request('PATCH', '/api/v1/organizations/' . $publicId, [
        'title' => 'Workspace Renamed ' . $suffix,
    ], $headers);
    assertTrue($update['status'] === 200, 'Organization update must be 200');

    $delete = request('DELETE', '/api/v1/organizations/' . $publicId, [], $headers);
    assertTrue($delete['status'] === 200, 'Organization delete must be 200');

    $unauthorized = request('GET', '/api/v1/organizations');
    assertTrue($unauthorized['status'] === 401, 'Organization list without token must be 401');
}

runOrganizationSmoke();
echo "[OK] organization_smoke\n";
