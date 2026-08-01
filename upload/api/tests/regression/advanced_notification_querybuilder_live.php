<?php
declare(strict_types=1);

require_once __DIR__ . '/../_live_http.php';

/**
 * @param array<string,mixed> $query
 * @param array<string,string> $headers
 * @return array{status:int,headers:array<int,string>,body:string,payload:array<string,mixed>}
 */
function liveGetWithQuery(string $route, array $query = [], array $headers = []): array
{
    $url = LIVE_API_BASE . '?route=' . rawurlencode($route);
    if ($query !== []) {
        $url .= '&' . http_build_query($query);
    }

    $headerLines = ['Accept: application/json'];
    foreach ($headers as $name => $value) {
        $headerLines[] = $name . ': ' . $value;
    }

    $context = stream_context_create([
        'http' => [
            'method' => 'GET',
            'header' => implode("\r\n", $headerLines),
            'ignore_errors' => true,
            'timeout' => 20,
        ],
        'ssl' => [
            'verify_peer' => false,
            'verify_peer_name' => false,
            'allow_self_signed' => true,
        ],
    ]);

    $body = @file_get_contents($url, false, $context);
    $responseHeaders = $http_response_header;
    if (!is_string($body)) {
        $body = '';
    }

    $status = 0;
    if (isset($responseHeaders[0]) && preg_match('/\s(\d{3})\s/', $responseHeaders[0], $m)) {
        $status = (int)$m[1];
    }

    $decoded = json_decode($body, true);
    if (!is_array($decoded)) {
        $decoded = [];
    }

    return [
        'status' => $status,
        'headers' => $responseHeaders,
        'body' => $body,
        'payload' => $decoded,
    ];
}

try {
    $root = liveLoginRoot();
    $headers = ['Authorization' => 'Bearer ' . $root['token']];
    $suffix = strtolower(gmdate('YmdHis') . '_' . bin2hex(random_bytes(2)));

    $first = liveRequest('POST', 'api/v1/notifications', [
        'title' => 'QB Notification A ' . $suffix,
        'body' => 'QB notification body A',
        'category' => 'qb_' . $suffix,
    ], $headers);
    liveAssert($first['status'] === 201, 'Notification create A must return 201');
    $firstPublicId = (string)($first['payload']['data']['notification']['public_id'] ?? '');
    liveAssert($firstPublicId !== '', 'Notification A public_id is required');

    $second = liveRequest('POST', 'api/v1/notifications', [
        'title' => 'QB Notification B ' . $suffix,
        'body' => 'QB notification body B',
        'category' => 'qb_' . $suffix,
    ], $headers);
    liveAssert($second['status'] === 201, 'Notification create B must return 201');
    $secondPublicId = (string)($second['payload']['data']['notification']['public_id'] ?? '');
    liveAssert($secondPublicId !== '', 'Notification B public_id is required');

    $listByCategory = liveGetWithQuery('api/v1/notifications', [
        'category' => 'qb_' . $suffix,
        'limit' => 50,
    ], $headers);
    liveAssert($listByCategory['status'] === 200, 'Notification list by category must return 200');
    $items = (array)($listByCategory['payload']['data']['items'] ?? []);
    liveAssert(count($items) >= 2, 'Notification list by category must contain created entries');

    $markRead = liveRequest('PATCH', 'api/v1/notifications/' . $firstPublicId . '/read', [], $headers);
    liveAssert($markRead['status'] === 200, 'Mark notification read must return 200');

    $listUnread = liveGetWithQuery('api/v1/notifications', [
        'category' => 'qb_' . $suffix,
        'is_read' => 0,
        'limit' => 50,
    ], $headers);
    liveAssert($listUnread['status'] === 200, 'Notification list unread must return 200');
    $unreadItems = (array)($listUnread['payload']['data']['items'] ?? []);
    foreach ($unreadItems as $row) {
        if (!is_array($row)) {
            continue;
        }
        liveAssert((int)($row['is_read'] ?? 0) === 0, 'Unread filter must return only unread notifications');
    }

    $counters = liveRequest('GET', 'api/v1/notifications/counters', [], $headers);
    liveAssert($counters['status'] === 200, 'Notification counters must return 200');
    $byCategory = (array)($counters['payload']['data']['counters']['by_category'] ?? []);
    liveAssert(array_key_exists('qb_' . $suffix, $byCategory), 'Counters by_category must include created category');

    $markAll = liveRequest('POST', 'api/v1/notifications/mark-all-read', [
        'category' => 'qb_' . $suffix,
    ], $headers);
    liveAssert($markAll['status'] === 200, 'Mark all read by category must return 200');

    $countersAfter = liveRequest('GET', 'api/v1/notifications/counters', [], $headers);
    liveAssert($countersAfter['status'] === 200, 'Notification counters after mark-all-read must return 200');
    $byCategoryAfter = (array)($countersAfter['payload']['data']['counters']['by_category'] ?? []);
    $unreadAfter = (int)($byCategoryAfter['qb_' . $suffix] ?? 0);
    liveAssert($unreadAfter === 0, 'Unread by category must be 0 after mark-all-read');

    echo "[OK] advanced_notification_querybuilder_live\n";
} catch (Throwable $e) {
    fwrite(STDERR, '[FAIL] advanced_notification_querybuilder_live: ' . $e->getMessage() . "\n");
    exit(1);
}
