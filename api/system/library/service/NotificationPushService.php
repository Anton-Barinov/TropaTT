<?php
declare(strict_types=1);

namespace Api\System\Library\Service;

use Api\Model\Notification\PushSubscriptionRepository;
use Api\Model\Notification\PushDispatchQueueRepository;
use Api\System\Library\Config;
use Api\System\Library\Language\LanguageManager;
use Api\System\Library\Language\TranslatableTrait;
use Api\System\Library\Logger\JsonLogger;
use Api\System\Library\Support\Ulid;

final class NotificationPushService
{
    use TranslatableTrait;

    public function __construct(
        private readonly PushSubscriptionRepository $subscriptions,
        private readonly PushDispatchQueueRepository $queue,
        private readonly JsonLogger $logger,
        private readonly Config $config,
        ?LanguageManager $lang = null
    ) {
        $this->lang = $lang ?? new LanguageManager(__DIR__ . '/../../language');
    }

    public function list(array $filters, array $actor): array
    {
        $userId = (int)($actor['id'] ?? 0);
        [$items, $total, $page, $limit] = $this->subscriptions->listByUser($userId, $filters);

        return [
            'items' => $items,
            'meta' => [
                'pagination' => [
                    'page' => $page,
                    'limit' => $limit,
                    'total' => $total,
                    'pages' => (int)ceil($total / max(1, $limit)),
                ],
            ],
        ];
    }

    public function upsert(array $input, array $actor): ?array
    {
        $userId = (int)($actor['id'] ?? 0);
        if ($userId <= 0) {
            return null;
        }

        $endpoint = trim((string)($input['endpoint'] ?? ''));
        $p256dh = trim((string)($input['p256dh'] ?? ''));
        $auth = trim((string)($input['auth'] ?? ''));
        if ($endpoint === '' || $p256dh === '' || $auth === '') {
            return null;
        }

        $deviceLabel = trim((string)($input['device_label'] ?? ''));
        $userAgent = trim((string)($input['user_agent'] ?? ''));
        $now = gmdate('Y-m-d H:i:s');

        $existing = $this->subscriptions->findByEndpointForUser($endpoint, $userId);
        if ($existing) {
            $publicId = (string)($existing['public_id'] ?? '');
            if ($publicId !== '') {
                $this->subscriptions->updateByPublicIdForUser($publicId, $userId, [
                    'p256dh' => $p256dh,
                    'auth' => $auth,
                    'device_label' => $deviceLabel !== '' ? $deviceLabel : null,
                    'user_agent' => $userAgent !== '' ? $userAgent : null,
                    'is_active' => 1,
                    'last_seen_at' => $now,
                    'updated_at' => $now,
                ]);
                $item = $this->subscriptions->findByPublicIdForUser($publicId, $userId);
                return is_array($item) ? $item : null;
            }
        }

        $publicId = Ulid::generate('psh');
        $this->subscriptions->create([
            'public_id' => $publicId,
            'user_id' => $userId,
            'endpoint' => $endpoint,
            'p256dh' => $p256dh,
            'auth' => $auth,
            'device_label' => $deviceLabel !== '' ? $deviceLabel : null,
            'user_agent' => $userAgent !== '' ? $userAgent : null,
            'is_active' => 1,
            'last_seen_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $this->logger->audit([
            'action' => 'notification_push_subscription_upsert',
            'actor_public_id' => $actor['public_id'] ?? null,
            'entity_type' => 'notification_push_subscription',
            'entity_public_id' => $publicId,
        ]);

        $created = $this->subscriptions->findByPublicIdForUser($publicId, $userId);
        return is_array($created) ? $created : null;
    }

    public function delete(string $publicId, array $actor): bool
    {
        $userId = (int)($actor['id'] ?? 0);
        if ($userId <= 0) {
            return false;
        }

        $deleted = $this->subscriptions->deleteByPublicIdForUser($publicId, $userId);
        if ($deleted) {
            $this->logger->audit([
                'action' => 'notification_push_subscription_delete',
                'actor_public_id' => $actor['public_id'] ?? null,
                'entity_type' => 'notification_push_subscription',
                'entity_public_id' => $publicId,
            ]);
        }

        return $deleted;
    }

    public function notifyUserNewNotification(int $userId, array $notification): void
    {
        if ($userId <= 0) {
            return;
        }

        $active = $this->subscriptions->activeByUser($userId);
        if ($active === []) {
            return;
        }

        $payload = $this->buildPushPayload($notification);
        $now = gmdate('Y-m-d H:i:s');
        $queuePublicId = Ulid::generate('npq');
        $this->queue->create([
            'public_id' => $queuePublicId,
            'user_id' => $userId,
            'notification_public_id' => (string)($notification['public_id'] ?? ''),
            'payload_json' => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'status' => 'queued',
            'attempts' => 0,
            'next_run_at' => $now,
            'locked_at' => null,
            'last_error' => null,
            'dead_letter' => 0,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $this->logger->audit([
            'action' => 'notification_push_dispatch_enqueued',
            'actor_public_id' => $notification['actor_public_id'] ?? null,
            'entity_type' => 'notification',
            'entity_public_id' => $notification['public_id'] ?? null,
            'target_user_id' => $userId,
            'subscriptions_count' => count($active),
            'queue_public_id' => $queuePublicId,
            'action_code' => $notification['action_code'] ?? null,
        ]);
    }

    /** @return array{processed:int,completed:int,retried:int,dead_lettered:int,failed:int,errors:array<int,array<string,string>>} */
    public function runQueued(int $limit = 20): array
    {
        $limit = max(1, min(200, $limit));
        $processed = 0;
        $completed = 0;
        $retried = 0;
        $deadLettered = 0;
        $failed = 0;
        $errors = [];
        $maxAttempts = max(1, (int)$this->config->get('notifications.push.retry_attempts', 3));
        $backoffSec = max(5, (int)$this->config->get('notifications.push.retry_backoff_sec', 30));
        $gateway = trim((string)$this->config->get('notifications.push.gateway_url', ''));
        $timeoutSec = max(1, (int)$this->config->get('notifications.push.timeout_sec', 5));
        $maxSubscriptions = max(1, (int)$this->config->get('notifications.push.max_subscriptions_per_dispatch', 100));

        for ($i = 0; $i < $limit; $i++) {
            $now = gmdate('Y-m-d H:i:s');
            $job = $this->queue->claimNextRunnable($now);
            if (!is_array($job)) {
                break;
            }
            $processed++;
            $jobPublicId = (string)($job['public_id'] ?? '');
            $userId = (int)($job['user_id'] ?? 0);
            $payload = json_decode((string)($job['payload_json'] ?? ''), true);
            if (!is_array($payload)) {
                $payload = [];
            }

            try {
                if ($gateway === '') {
                    throw new \RuntimeException('PUSH_GATEWAY_NOT_CONFIGURED');
                }

                $active = $this->subscriptions->activeByUser($userId);
                $attempted = 0;
                foreach ($active as $subscription) {
                    if ($attempted >= $maxSubscriptions) {
                        break;
                    }
                    $attempted++;
                    $subPublicId = (string)($subscription['public_id'] ?? '');
                    $endpoint = trim((string)($subscription['endpoint'] ?? ''));
                    if ($subPublicId === '' || $endpoint === '') {
                        continue;
                    }

                    $result = $this->dispatchToGateway($gateway, $timeoutSec, [
                        'subscription' => [
                            'public_id' => $subPublicId,
                            'endpoint' => $endpoint,
                            'p256dh' => (string)($subscription['p256dh'] ?? ''),
                            'auth' => (string)($subscription['auth'] ?? ''),
                            'device_label' => $subscription['device_label'] ?? null,
                            'user_agent' => $subscription['user_agent'] ?? null,
                        ],
                        'notification' => $payload,
                    ]);

                    if (in_array($result['status_code'], [404, 410], true)) {
                        $this->subscriptions->markInactiveByPublicIdForUser($subPublicId, $userId, 'gateway_http_' . $result['status_code'], $now);
                    } elseif ($result['status_code'] >= 200 && $result['status_code'] < 300) {
                        $this->subscriptions->touchDeliverySuccessByPublicIdForUser($subPublicId, $userId, $now);
                    }
                }

                $this->queue->updateByPublicId($jobPublicId, [
                    'status' => 'completed',
                    'locked_at' => null,
                    'next_run_at' => null,
                    'last_error' => null,
                    'updated_at' => gmdate('Y-m-d H:i:s'),
                ]);
                $completed++;
            } catch (\Throwable $e) {
                $attempts = (int)($job['attempts'] ?? 0) + 1;
                $isDead = $attempts >= $maxAttempts;
                $this->queue->updateByPublicId($jobPublicId, [
                    'attempts' => $attempts,
                    'status' => $isDead ? 'dead_letter' : 'retry',
                    'dead_letter' => $isDead ? 1 : 0,
                    'next_run_at' => $isDead ? null : gmdate('Y-m-d H:i:s', time() + $backoffSec * $attempts),
                    'locked_at' => null,
                    'last_error' => $e->getMessage(),
                    'updated_at' => gmdate('Y-m-d H:i:s'),
                ]);
                if ($isDead) {
                    $deadLettered++;
                } else {
                    $retried++;
                }
                $failed++;
                $errors[] = ['public_id' => $jobPublicId, 'error' => $e->getMessage()];
            }
        }

        return [
            'processed' => $processed,
            'completed' => $completed,
            'retried' => $retried,
            'dead_lettered' => $deadLettered,
            'failed' => $failed,
            'errors' => $errors,
        ];
    }

    /** @return array{attempted:int,delivered:int,deactivated:int,gateway_configured:bool} */
    public function sendTestToUser(int $userId, array $actor): array
    {
        if ($userId <= 0) {
            return ['attempted' => 0, 'delivered' => 0, 'deactivated' => 0, 'gateway_configured' => false];
        }

        $active = $this->subscriptions->activeByUser($userId);
        $gateway = trim((string)$this->config->get('notifications.push.gateway_url', ''));
        $gatewayConfigured = $gateway !== '';

        $attempted = 0;
        $delivered = 0;
        $deactivated = 0;
        $timeoutSec = max(1, (int)$this->config->get('notifications.push.timeout_sec', 5));
        $maxSubscriptions = max(1, (int)$this->config->get('notifications.push.max_subscriptions_per_dispatch', 100));
        $now = gmdate('Y-m-d H:i:s');
        $payload = [
            'title' => $this->t('notification/messages.test_push_title'),
            'body' => $this->t('notification/messages.test_push_body'),
            'link' => 'index.php?route=notifications',
            'notification_public_id' => '',
            'category' => 'system',
            'created_at' => gmdate('c'),
        ];

        foreach ($active as $subscription) {
            if ($attempted >= $maxSubscriptions) {
                break;
            }
            $publicId = (string)($subscription['public_id'] ?? '');
            $endpoint = trim((string)($subscription['endpoint'] ?? ''));
            if ($publicId === '' || $endpoint === '' || !$gatewayConfigured) {
                continue;
            }
            $attempted++;
            $result = $this->dispatchToGateway($gateway, $timeoutSec, [
                'subscription' => [
                    'public_id' => $publicId,
                    'endpoint' => $endpoint,
                    'p256dh' => (string)($subscription['p256dh'] ?? ''),
                    'auth' => (string)($subscription['auth'] ?? ''),
                    'device_label' => $subscription['device_label'] ?? null,
                    'user_agent' => $subscription['user_agent'] ?? null,
                ],
                'notification' => $payload,
            ]);

            if (in_array($result['status_code'], [404, 410], true)) {
                $this->subscriptions->markInactiveByPublicIdForUser(
                    $publicId,
                    $userId,
                    'gateway_http_' . $result['status_code'],
                    $now
                );
                $deactivated++;
            } elseif ($result['status_code'] >= 200 && $result['status_code'] < 300) {
                $this->subscriptions->touchDeliverySuccessByPublicIdForUser($publicId, $userId, $now);
                $delivered++;
            }
        }

        $this->logger->audit([
            'action' => 'notification_push_test_dispatch',
            'actor_public_id' => $actor['public_id'] ?? null,
            'entity_type' => 'notification_push_subscription',
            'entity_public_id' => null,
            'target_user_id' => $userId,
            'attempted' => $attempted,
            'delivered' => $delivered,
            'deactivated' => $deactivated,
            'gateway_configured' => $gatewayConfigured,
        ]);

        return [
            'attempted' => $attempted,
            'delivered' => $delivered,
            'deactivated' => $deactivated,
            'gateway_configured' => $gatewayConfigured,
        ];
    }

    /** @return array{title:string,body:string,link:string,notification_public_id:string,category:string,created_at:string} */
    private function buildPushPayload(array $notification): array
    {
        return [
            'title' => trim((string)($notification['title'] ?? $this->t('notification/messages.new_notification'))) ?: $this->t('notification/messages.new_notification'),
            'body' => trim((string)($notification['body'] ?? '')),
            'link' => trim((string)($notification['link'] ?? 'index.php?route=notifications')) ?: 'index.php?route=notifications',
            'notification_public_id' => (string)($notification['public_id'] ?? ''),
            'category' => (string)($notification['category'] ?? ''),
            'created_at' => gmdate('c'),
        ];
    }

    /** @param array<string,mixed> $payload
     *  @return array{ok:bool,status_code:int,error:string}
     */
    private function dispatchToGateway(string $gatewayUrl, int $timeoutSec, array $payload): array
    {
        $encoded = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (!is_string($encoded) || $encoded === '') {
            return ['ok' => false, 'status_code' => 0, 'error' => 'encode_failed'];
        }

        if (function_exists('curl_init')) {
            $ch = curl_init($gatewayUrl);
            if ($ch === false) {
                return ['ok' => false, 'status_code' => 0, 'error' => 'curl_init_failed'];
            }

            curl_setopt_array($ch, [
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => $encoded,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HEADER => true,
                CURLOPT_HTTPHEADER => [
                    'Content-Type: application/json',
                    'Accept: application/json',
                ],
                CURLOPT_TIMEOUT => $timeoutSec,
                CURLOPT_CONNECTTIMEOUT => $timeoutSec,
            ]);

            $response = curl_exec($ch);
            $errno = curl_errno($ch);
            $status = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);

            if ($response === false || $errno !== 0) {
                return ['ok' => false, 'status_code' => $status, 'error' => 'curl_transport_error'];
            }

            return [
                'ok' => $status >= 200 && $status < 300,
                'status_code' => $status,
                'error' => '',
            ];
        }

        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => "Content-Type: application/json\r\nAccept: application/json\r\n",
                'content' => $encoded,
                'ignore_errors' => true,
                'timeout' => $timeoutSec,
            ],
        ]);
        $response = @file_get_contents($gatewayUrl, false, $context);
        $statusCode = 0;
        foreach (($http_response_header ?? []) as $line) {
            if (preg_match('/\s(\d{3})\s/', (string)$line, $m) === 1) {
                $statusCode = (int)$m[1];
                break;
            }
        }
        if ($response === false && $statusCode === 0) {
            return ['ok' => false, 'status_code' => 0, 'error' => 'stream_transport_error'];
        }

        return [
            'ok' => $statusCode >= 200 && $statusCode < 300,
            'status_code' => $statusCode,
            'error' => '',
        ];
    }
}
