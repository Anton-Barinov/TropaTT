<?php
declare(strict_types=1);

require __DIR__ . '/_bootstrap.php';

function runRecycleBinSmoke(): void
{
    $auth = loginRoot();
    $headers = authHeaders($auth['token']);
    $suffix = randomSuffix();

    $create = request('POST', '/api/v1/files', [
        'name' => 'recycle_' . $suffix . '.txt',
        'mime_type' => 'text/plain',
        'content_base64' => base64_encode('recycle bin payload ' . $suffix),
    ], $headers);
    assertTrue($create['status'] === 201, 'File create status must be 201');
    $filePublicId = (string)($create['payload']['data']['file']['public_id'] ?? '');
    assertTrue($filePublicId !== '', 'File public_id is required');

    $delete = request('DELETE', '/api/v1/files/' . $filePublicId, [], $headers);
    assertTrue($delete['status'] === 200, 'File delete status must be 200');

    $list = request('GET', '/api/v1/recycle-bin?entity_type=file&entity_public_id=' . rawurlencode($filePublicId), [], $headers);
    assertTrue($list['status'] === 200, 'Recycle bin list status must be 200');
    $items = (array)($list['payload']['data']['items'] ?? []);
    assertTrue($items !== [], 'Recycle bin item must exist');
    $binPublicId = (string)($items[0]['public_id'] ?? '');
    assertTrue($binPublicId !== '', 'Recycle bin public_id is required');

    $restore = request('POST', '/api/v1/recycle-bin/' . $binPublicId . '/restore', [], $headers);
    assertTrue($restore['status'] === 200, 'Recycle bin restore status must be 200');

    $getRestored = request('GET', '/api/v1/files/' . $filePublicId, [], $headers);
    assertTrue($getRestored['status'] === 200, 'Restored file get status must be 200');

    $deleteAgain = request('DELETE', '/api/v1/files/' . $filePublicId, [], $headers);
    assertTrue($deleteAgain['status'] === 200, 'File delete again status must be 200');

    $listAgain = request('GET', '/api/v1/recycle-bin/list?entity_type=file&entity_public_id=' . rawurlencode($filePublicId), [], $headers);
    assertTrue($listAgain['status'] === 200, 'Recycle bin alias list status must be 200');
    $itemsAgain = (array)($listAgain['payload']['data']['items'] ?? []);
    assertTrue($itemsAgain !== [], 'Recycle bin second item must exist');
    $binPublicId2 = (string)($itemsAgain[0]['public_id'] ?? '');
    assertTrue($binPublicId2 !== '', 'Recycle bin second public_id is required');

    $purge = request('DELETE', '/api/v1/recycle-bin/' . $binPublicId2 . '/purge', [], $headers);
    assertTrue($purge['status'] === 200, 'Recycle bin purge status must be 200');

    $getPurged = request('GET', '/api/v1/files/' . $filePublicId, [], $headers);
    assertTrue($getPurged['status'] === 404, 'Purged file must be unavailable');

    $unauthorized = request('GET', '/api/v1/recycle-bin');
    assertTrue($unauthorized['status'] === 401, 'Recycle bin list without token must return 401');
}

runRecycleBinSmoke();
echo "[OK] recycle_bin_smoke\n";
