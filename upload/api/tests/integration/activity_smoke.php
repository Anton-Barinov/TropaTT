<?php
declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';

try {
    $auth = loginRoot();
    $headers = authHeaders($auth['token']);

    $feed = request('GET', '/api/v1/activity/feed?limit=5', [], $headers);
    assertTrue($feed['status'] === 200, 'Activity feed status must be 200');
    assertTrue(($feed['payload']['code'] ?? '') === 'ACTIVITY_FEED', 'Activity feed code mismatch');

    $auditFeed = request('GET', '/api/v1/activity/feed?channel=audit&limit=5', [], $headers);
    assertTrue($auditFeed['status'] === 200, 'Activity audit feed status must be 200');

    $items = $auditFeed['payload']['data']['items'] ?? [];
    if (is_array($items) && isset($items[0]['entity_type'], $items[0]['entity_public_id'])) {
        $entityType = (string)$items[0]['entity_type'];
        $entityPublicId = (string)$items[0]['entity_public_id'];

        $history = request('GET', '/api/v1/history/entity/' . $entityType . '/' . $entityPublicId . '?limit=5', [], $headers);
        assertTrue($history['status'] === 200, 'Entity history status must be 200');
        assertTrue(($history['payload']['code'] ?? '') === 'ENTITY_HISTORY', 'Entity history code mismatch');

        $historyAlias = request('GET', '/api/v1/history/entity?entity_type=' . rawurlencode($entityType) . '&public_id=' . rawurlencode($entityPublicId) . '&limit=3', [], $headers);
        assertTrue($historyAlias['status'] === 200, 'Entity history alias status must be 200');
    }

    $unauthorized = request('GET', '/api/v1/activity/feed');
    assertTrue($unauthorized['status'] === 401, 'Activity unauthorized status must be 401');

    echo "[OK] Activity smoke passed\n";
    exit(0);
} catch (Throwable $e) {
    fwrite(STDERR, '[FAIL] ' . $e->getMessage() . "\n");
    exit(1);
}
