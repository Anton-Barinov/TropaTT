<?php
declare(strict_types=1);

require __DIR__ . '/_bootstrap.php';

try {
    $root = dirname(__DIR__, 3);
    $eventsController = (string)file_get_contents($root . '/api/controller/events/EventsController.php');
    $routes = (string)file_get_contents($root . '/api/config/routes.php');
    $app = (string)file_get_contents($root . '/api/system/library/app.php');
    $notificationService = (string)file_get_contents($root . '/api/system/library/service/NotificationService.php');
    $notificationRepository = (string)file_get_contents($root . '/api/model/notification/NotificationRepository.php');
    $realtime = (string)file_get_contents($root . '/web/assets/js/notifications-realtime.js');
    $spec = (string)file_get_contents($root . '/web/docs/notifications-realtime-technical-spec-2026-05-02.md');

    assertTrue(str_contains($routes, "'/api/v1/events/stream'"), 'SSE route must exist');
    assertTrue(str_contains($routes, "'sse' => true"), 'SSE route must be marked as sse');
    assertTrue(str_contains($app, 'Content-Type: text/event-stream'), 'App must emit text/event-stream');
    assertTrue(str_contains($eventsController, "header('Last-Event-ID'"), 'EventsController must read Last-Event-ID');
    assertTrue(str_contains($eventsController, "input('after_id'"), 'EventsController must support after_id resume');
    assertTrue(str_contains($eventsController, 'streamItemsAfterId'), 'EventsController must stream missed notifications by id');
    assertTrue(str_contains($eventsController, 'latestInternalIdByUser'), 'EventsController must skip historical backlog on fresh stream');
    assertTrue(str_contains($eventsController, 'id: ' . "' . " . '$lastId') || str_contains($eventsController, "echo 'id: ' . \$lastId"), 'SSE events must emit monotonic id');
    assertTrue(str_contains($eventsController, 'stream.rotate'), 'SSE stream must rotate for reconnect');
    assertTrue(str_contains($eventsController, 'event: ping'), 'SSE stream must send heartbeat');
    assertTrue(str_contains($notificationService, 'streamItemsAfterId'), 'NotificationService must expose streamItemsAfterId');
    assertTrue(str_contains($notificationRepository, 'listForUserAfterId'), 'NotificationRepository must expose listForUserAfterId');
    assertTrue(str_contains($notificationRepository, "where('n.id', '>', \$afterId)"), 'NotificationRepository must resume strictly after last id');
    assertTrue(str_contains($notificationRepository, "orderBy('n.id', 'ASC')"), 'NotificationRepository must preserve delivery order');

    foreach ([
        'recentNotificationIds',
        'registerSeenNotification',
        'lastEventId = Math.max(lastEventId, eventId)',
        "url.searchParams.set('after_id', String(lastEventId))",
        'ensurePollingFallback',
        'new EventSource',
        'notification.created',
        'notification.updated',
        'stream.rotate',
    ] as $needle) {
        assertTrue(str_contains($realtime, $needle), 'Realtime client missing contract: ' . $needle);
    }

    assertTrue(str_contains($spec, 'разрешён упрощенный режим: опора на `notifications.id/public_id + created_at`'), 'Spec must document notifications table as accepted event log');
    assertTrue(str_contains($spec, 'Last-Event-ID'), 'Spec must document Last-Event-ID');
    assertTrue(str_contains($spec, 'polling'), 'Spec must document polling fallback');

    fwrite(STDOUT, "[OK] c04_realtime_resume_smoke\n");
} catch (Throwable $e) {
    fwrite(STDERR, '[FAIL] c04_realtime_resume_smoke: ' . $e->getMessage() . "\n");
    exit(1);
}
