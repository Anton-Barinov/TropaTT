<?php
declare(strict_types=1);

namespace Api\Controller\Events;

use Api\Controller\Common\BaseController;
use Api\System\Library\Service\NotificationPushService;
use Api\System\Library\Service\NotificationService;
use Api\System\Library\Service\ReminderService;

final class EventsController extends BaseController
{
    public function stream(): array
    {
        $auth = $this->user();
        if (!$auth || !isset($auth['user']) || !is_array($auth['user'])) {
            return [
                'event' => 'error',
                'data' => [
                    'type' => 'error',
                    'code' => 'UNAUTHORIZED',
                    'message' => $this->t('common/messages.unauthorized', 'Unauthorized'),
                ],
            ];
        }

        $actor = $auth['user'];
        $userId = (int)($actor['id'] ?? 0);

        /** @var NotificationService $notifications */
        $notifications = $this->container->get('service.notification');
        $request = $this->request();
        $requestedAfterId = max(0, (int)$request->input('after_id', 0));
        $headerAfterId = max(0, (int)$this->parseLastEventId((string)$request->header('Last-Event-ID', '')));
        $streamAfterId = max($requestedAfterId, $headerAfterId);
        if ($streamAfterId <= 0) {
            $streamAfterId = $notifications->latestInternalIdByUser($userId);
        }

        return [
            'stream' => function () use ($notifications, $userId, $streamAfterId, $request, $actor): void {
                while (ob_get_level() > 0) {
                    @ob_end_clean();
                }

                header('Content-Type: text/event-stream; charset=utf-8');
                header('Cache-Control: no-cache, no-store, must-revalidate');
                header('Connection: keep-alive');
                header('X-Accel-Buffering: no');

                @set_time_limit(0);
                @ignore_user_abort(false);

                $lastId = $streamAfterId;
                $startedAt = time();
                // Keep SSE bursts short for small PHP-FPM pools: the browser reconnects,
                // while stale streams release workers quickly during page navigation.
                $maxDuration = 15;
                $heartbeatInterval = 5;
                $nextHeartbeatAt = time() + $heartbeatInterval;
                $nextDomainScanAt = time();
                $lastStateHash = $notifications->stateHashByUser($userId);

                /** @var ReminderService $reminders */
                $reminders = $this->container->get('service.reminder');

                echo 'event: stream.ready' . "\n";
                echo 'data: ' . json_encode([
                    'type' => 'stream.ready',
                    'request_id' => $request->requestId,
                    'timestamp' => gmdate('c'),
                ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n\n";
                @ob_flush();
                @flush();

                while (!connection_aborted()) {
                    if ((time() - $startedAt) >= $maxDuration) {
                        echo 'event: stream.rotate' . "\n";
                        echo 'data: ' . json_encode([
                            'type' => 'stream.rotate',
                            'message' => 'reconnect',
                            'timestamp' => gmdate('c'),
                        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n\n";
                        @ob_flush();
                        @flush();
                        break;
                    }

                    if (time() >= $nextDomainScanAt) {
                        $nextDomainScanAt = time() + 15;
                        $reminders->dispatchDueNotificationsForUser($actor, gmdate('Y-m-d H:i:s'));
                        $notifications->dispatchOverdueSignalsForUser($userId, $actor);

                        if ($this->container->has('service.notification_push')) {
                            /** @var NotificationPushService $push */
                            $push = $this->container->get('service.notification_push');
                            $push->runQueued(5);
                        }
                    }

                    $newItems = $notifications->streamItemsAfterId($userId, $lastId, 100);
                    if ($newItems !== []) {
                        foreach ($newItems as $item) {
                            $itemId = (int)($item['id'] ?? 0);
                            if ($itemId > $lastId) {
                                $lastId = $itemId;
                            }
                            unset($item['id']);

                            echo 'id: ' . $lastId . "\n";
                            echo 'event: notification.created' . "\n";
                            echo 'data: ' . json_encode([
                                'type' => 'notification.created',
                                'notification' => $item,
                                'counters' => $notifications->counters(['id' => $userId]),
                                'meta' => [
                                    'timestamp' => gmdate('c'),
                                ],
                            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n\n";
                            @ob_flush();
                            @flush();
                        }
                        $lastStateHash = $notifications->stateHashByUser($userId);
                    }

                    $currentHash = $notifications->stateHashByUser($userId);
                    if ($currentHash !== $lastStateHash) {
                        $lastStateHash = $currentHash;
                        echo 'event: notification.updated' . "\n";
                        echo 'data: ' . json_encode([
                            'type' => 'notification.updated',
                            'event' => 'notification.updated',
                            'counters' => $notifications->counters(['id' => $userId]),
                            'meta' => [
                                'timestamp' => gmdate('c'),
                                'state_changed' => true,
                            ],
                        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n\n";
                        // Backward compatibility for clients that still listen to notification.state.
                        echo 'event: notification.state' . "\n";
                        echo 'data: ' . json_encode([
                            'type' => 'notification.state',
                            'event' => 'notification.state',
                            'counters' => $notifications->counters(['id' => $userId]),
                            'meta' => [
                                'timestamp' => gmdate('c'),
                                'state_changed' => true,
                            ],
                        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n\n";
                        @ob_flush();
                        @flush();
                    }

                    if (time() >= $nextHeartbeatAt) {
                        $nextHeartbeatAt = time() + $heartbeatInterval;
                        echo 'event: ping' . "\n";
                        echo 'data: ' . json_encode([
                            'type' => 'ping',
                            'timestamp' => gmdate('c'),
                        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n\n";
                        @ob_flush();
                        @flush();
                    }

                    usleep(1200000);
                }
            },
        ];
    }

    private function parseLastEventId(string $raw): int
    {
        $value = trim($raw);
        if ($value === '') {
            return 0;
        }

        if (preg_match('/(\d+)/', $value, $matches) !== 1) {
            return 0;
        }

        return (int)($matches[1] ?? 0);
    }
}
