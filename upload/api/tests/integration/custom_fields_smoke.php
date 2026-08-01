<?php
declare(strict_types=1);

require __DIR__ . '/_bootstrap.php';

function runCustomFieldsSmoke(): void
{
    $auth = loginRoot();
    $headers = authHeaders($auth['token']);
    $suffix = randomSuffix();

    $create = request('POST', '/api/v1/custom-fields', [
        'scope' => 'task',
        'code' => 'smoke_' . strtolower(substr($suffix, -8)),
        'title' => 'Smoke Field ' . $suffix,
        'type' => 'text',
        'options' => [],
        'is_required' => 0,
    ], $headers);
    assertTrue($create['status'] === 201, 'Custom field create status must be 201');
    $fieldPublicId = (string)($create['payload']['data']['field']['public_id'] ?? '');
    assertTrue($fieldPublicId !== '', 'Custom field public_id is required');

    $list = request('GET', '/api/v1/custom-fields?scope=task&limit=5', [], $headers);
    assertTrue($list['status'] === 200, 'Custom field list status must be 200');

    $get = request('GET', '/api/v1/custom-fields/' . $fieldPublicId, [], $headers);
    assertTrue($get['status'] === 200, 'Custom field get status must be 200');

    $update = request('PATCH', '/api/v1/custom-fields/' . $fieldPublicId, [
        'title' => 'Smoke Field Updated ' . $suffix,
        'is_required' => 1,
    ], $headers);
    assertTrue($update['status'] === 200, 'Custom field update status must be 200');
    assertTrue(($update['payload']['data']['field']['is_required'] ?? false) === true, 'is_required must be true');

    $entityType = 'task';
    $entityPublicId = 'tsk_custom_' . strtolower(substr($suffix, -8));
    $setValues = request('POST', '/api/v1/custom-fields/values', [
        'entity_type' => $entityType,
        'entity_public_id' => $entityPublicId,
        'values' => [
            $fieldPublicId => 'Value ' . $suffix,
        ],
    ], $headers);
    assertTrue($setValues['status'] === 200, 'Custom field values save status must be 200');

    $getValues = request(
        'GET',
        '/api/v1/custom-fields/values?entity_type=' . rawurlencode($entityType) . '&entity_public_id=' . rawurlencode($entityPublicId),
        [],
        $headers
    );
    assertTrue($getValues['status'] === 200, 'Custom field values get status must be 200');
    $items = (array)($getValues['payload']['data']['items'] ?? []);
    assertTrue(count($items) >= 1, 'Custom field values items should not be empty');

    $aliasList = request('GET', '/api/v1/custom-field/list?scope=task&limit=5', [], $headers);
    assertTrue($aliasList['status'] === 200, 'Custom field alias list status must be 200');

    $delete = request('DELETE', '/api/v1/custom-fields/' . $fieldPublicId, [], $headers);
    assertTrue($delete['status'] === 200, 'Custom field delete status must be 200');

    $unauthorized = request('GET', '/api/v1/custom-fields');
    assertTrue($unauthorized['status'] === 401, 'Custom fields without token must return 401');
}

runCustomFieldsSmoke();
echo "[OK] custom_fields_smoke\n";
