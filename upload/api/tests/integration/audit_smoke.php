<?php
declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';

try {
    $auth = loginRoot();
    $headers = authHeaders($auth['token']);

    $list = request('GET', '/api/v1/audit/list?limit=5', [], $headers);
    assertTrue($list['status'] === 200, 'Audit list status must be 200');
    assertTrue(($list['payload']['code'] ?? '') === 'AUDIT_LIST', 'Audit list code mismatch');

    $items = $list['payload']['data']['items'] ?? [];
    assertTrue(is_array($items), 'Audit list items must be array');

    $actor = (string)($items[0]['actor_public_id'] ?? $auth['user_public_id']);
    $entityType = (string)($items[0]['entity_type'] ?? 'worklog');
    $entityPublicId = (string)($items[0]['entity_public_id'] ?? '');

    $byUser = request('GET', '/api/v1/audit/user/' . $actor . '?limit=5', [], $headers);
    assertTrue($byUser['status'] === 200, 'Audit by user status must be 200');
    assertTrue(($byUser['payload']['code'] ?? '') === 'AUDIT_USER', 'Audit by user code mismatch');

    if ($entityPublicId !== '') {
        $byEntity = request('GET', '/api/v1/audit/entity/' . $entityType . '/' . $entityPublicId . '?limit=5', [], $headers);
        assertTrue($byEntity['status'] === 200, 'Audit by entity status must be 200');
        assertTrue(($byEntity['payload']['code'] ?? '') === 'AUDIT_ENTITY', 'Audit by entity code mismatch');
    }

    $unauthorized = request('GET', '/api/v1/audit/list');
    assertTrue($unauthorized['status'] === 401, 'Audit unauthorized status must be 401');

    echo "[OK] Audit smoke passed\n";
    exit(0);
} catch (Throwable $e) {
    fwrite(STDERR, '[FAIL] ' . $e->getMessage() . "\n");
    exit(1);
}
