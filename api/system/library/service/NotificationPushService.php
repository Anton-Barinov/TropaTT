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

        $vapidPub = trim((string)$this->config->get('notifications.push.vapid_public_key', ''));
        $vapidPriv = trim((string)$this->config->get('notifications.push.vapid_private_key', ''));
        $vapidSub = trim((string)$this->config->get('notifications.push.vapid_subject', ''));
        $useDirect = $vapidPub !== '' && $vapidPriv !== '' && $vapidSub !== '';

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

                    $result = $useDirect
                        ? $this->dispatchWebPush($endpoint, (string)($subscription['p256dh'] ?? ''), (string)($subscription['auth'] ?? ''), $payload, $vapidPub, $vapidPriv, $vapidSub, $timeoutSec)
                        : $this->dispatchToGateway($gateway, $timeoutSec, [
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

                    if (in_array($result['status_code'], [401, 403, 404, 410], true)) {
                        $this->subscriptions->markInactiveByPublicIdForUser($subPublicId, $userId, 'push_http_' . $result['status_code'], $now);
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

        $vapidPub = trim((string)$this->config->get('notifications.push.vapid_public_key', ''));
        $vapidPriv = trim((string)$this->config->get('notifications.push.vapid_private_key', ''));
        $vapidSub = trim((string)$this->config->get('notifications.push.vapid_subject', ''));
        $useDirect = $vapidPub !== '' && $vapidPriv !== '' && $vapidSub !== '';

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
            if ($publicId === '' || $endpoint === '') {
                continue;
            }
            if (!$useDirect && !$gatewayConfigured) {
                continue;
            }
            $attempted++;

            $result = $useDirect
                ? $this->dispatchWebPush($endpoint, (string)($subscription['p256dh'] ?? ''), (string)($subscription['auth'] ?? ''), $payload, $vapidPub, $vapidPriv, $vapidSub, $timeoutSec)
                : $this->dispatchToGateway($gateway, $timeoutSec, [
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

            if (in_array($result['status_code'], [401, 403, 404, 410], true)) {
                $this->subscriptions->markInactiveByPublicIdForUser($publicId, $userId, 'push_http_' . $result['status_code'], $now);
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

    /**
     * Dispatch a Web Push notification directly using the Web Push Protocol (RFC 8291).
     *
     * @param array<string,mixed> $payload
     * @return array{ok:bool,status_code:int,error:string}
     */
    private function dispatchWebPush(
        string $endpoint,
        string $userPublicKeyBase64,
        string $userAuthBase64,
        array $payload,
        string $vapidPublicKey,
        string $vapidPrivateKey,
        string $vapidSubject,
        int $timeoutSec
    ): array {
        $plaintext = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (!is_string($plaintext) || $plaintext === '') {
            return ['ok' => false, 'status_code' => 0, 'error' => 'encode_failed'];
        }

        $encrypted = $this->encryptPayload($plaintext, $userPublicKeyBase64, $userAuthBase64);
        if ($encrypted === '') {
            return ['ok' => false, 'status_code' => 0, 'error' => 'encryption_failed'];
        }

        $origin = parse_url($endpoint, PHP_URL_SCHEME . '://' . parse_url($endpoint, PHP_URL_HOST));
        $jwt = $this->generateVapidJwt($vapidPrivateKey, $vapidPublicKey, $vapidSubject, $origin);
        if ($jwt === '') {
            return ['ok' => false, 'status_code' => 0, 'error' => 'vapid_jwt_failed'];
        }

        $vapidHeader = 'vapid t=' . $jwt . ', k=' . $vapidPublicKey;

        if (function_exists('curl_init')) {
            $ch = curl_init($endpoint);
            if ($ch === false) {
                return ['ok' => false, 'status_code' => 0, 'error' => 'curl_init_failed'];
            }

            curl_setopt_array($ch, [
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => $encrypted,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HEADER => true,
                CURLOPT_HTTPHEADER => [
                    'Content-Type: application/octet-stream',
                    'Content-Encoding: aes128gcm',
                    'Content-Length: ' . strlen($encrypted),
                    'TTL: 86400',
                    'Authorization: ' . $vapidHeader,
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
                'header' => "Content-Type: application/octet-stream\r\nContent-Encoding: aes128gcm\r\nContent-Length: " . strlen($encrypted) . "\r\nTTL: 86400\r\nAuthorization: {$vapidHeader}\r\n",
                'content' => $encrypted,
                'ignore_errors' => true,
                'timeout' => $timeoutSec,
            ],
        ]);
        $response = @file_get_contents($endpoint, false, $context);
        $statusCode = 0;
        $responseHdrs = [];
        if (function_exists('http_get_last_response_headers')) {
            $responseHdrs[] = 'HTTP/1.1 ' . (http_get_last_response_code() ?? '');
            foreach (http_get_last_response_headers() ?? [] as $n => $v) {
                $responseHdrs[] = $n . ': ' . $v;
            }
        } else {
            $responseHdrs = $http_response_header ?? [];
        }
        foreach ($responseHdrs as $line) {
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

    /**
     * Generate a VAPID JWT token (RFC 8292).
     */
    private function generateVapidJwt(string $privateKeyBase64, string $publicKeyBase64, string $subject, string $audience): string
    {
        $header = $this->base64UrlEncode(json_encode(['alg' => 'ES256', 'typ' => 'JWT'], JSON_UNESCAPED_SLASHES));
        $payload = $this->base64UrlEncode(json_encode([
            'aud' => $audience,
            'exp' => time() + 43200,
            'sub' => $subject,
        ], JSON_UNESCAPED_SLASHES));
        $signingInput = $header . '.' . $payload;

        $privateKeyDer = $this->base64UrlDecode($privateKeyBase64);
        $pem = "-----BEGIN EC PRIVATE KEY-----\n" . chunk_split(base64_encode("\x30\x30\x02\x01\x00\x30\x13\x06\x07\x2a\x86\x48\xce\x3d\x02\x01\x06\x08\x2a\x86\x48\xce\x3d\x03\x01\x07\x04\x22" . $privateKeyDer), 64, "\n") . "-----END EC PRIVATE KEY-----\n";

        $signingKey = openssl_pkey_get_private($pem);
        if ($signingKey === false) {
            return '';
        }

        $signature = '';
        $ok = openssl_sign($signingInput, $signature, $signingKey, OPENSSL_ALGO_SHA256);
        openssl_pkey_free($signingKey);
        if (!$ok || $signature === '') {
            return '';
        }

        $derSignature = $this->signatureDerToRaw($signature);
        return $signingInput . '.' . $this->base64UrlEncode($derSignature);
    }

    /**
     * Convert DER-encoded ECDSA signature to raw r||s format (64 bytes).
     */
    private function signatureDerToRaw(string $der): string
    {
        $offset = 0;
        if (ord($der[$offset++]) !== 0x30) {
            return '';
        }
        $offset++;

        if (ord($der[$offset++]) !== 0x02) {
            return '';
        }
        $rLen = ord($der[$offset++]);
        $r = substr($der, $offset, $rLen);
        $offset += $rLen;

        if (ord($der[$offset++]) !== 0x02) {
            return '';
        }
        $sLen = ord($der[$offset++]);
        $s = substr($der, $offset, $sLen);

        $r = ltrim($r, "\x00");
        $s = ltrim($s, "\x00");

        $r = str_pad($r, 32, "\x00", STR_PAD_LEFT);
        $s = str_pad($s, 32, "\x00", STR_PAD_LEFT);

        return $r . $s;
    }

    /**
     * Encrypt a push payload using Web Push Content Encoding: aes128gcm (RFC 8291).
     *
     * @return string binary encrypted payload, or '' on failure
     */
    private function encryptPayload(string $plaintext, string $userPublicKeyBase64, string $userAuthBase64): string
    {
        $userPublicKey = $this->base64UrlDecode($userPublicKeyBase64);
        if (strlen($userPublicKey) !== 65 || $userPublicKey[0] !== "\x04") {
            return '';
        }
        $userAuthSecret = $this->base64UrlDecode($userAuthBase64);
        if (strlen($userAuthSecret) !== 16) {
            return '';
        }

        $localKey = openssl_pkey_new([
            'curve_name' => 'prime256v1',
            'private_key_type' => OPENSSL_KEYTYPE_EC,
        ]);
        if ($localKey === false) {
            return '';
        }
        $localDetails = openssl_pkey_get_details($localKey);
        if (!isset($localDetails['ec']['x'], $localDetails['ec']['y'], $localDetails['ec']['d'])) {
            return '';
        }
        $localPublicKey = "\x04" . $localDetails['ec']['x'] . $localDetails['ec']['y'];
        $localPrivateKey = $localDetails['ec']['d'];

        $ecdhKey = openssl_pkey_new([
            'curve_name' => 'prime256v1',
            'private_key_type' => OPENSSL_KEYTYPE_EC,
        ]);
        $localEcKey = openssl_pkey_get_private($this->buildEcPem($localPrivateKey));
        $peerEcKey = openssl_pkey_get_public($userPublicKey);
        if ($localEcKey === false || $peerEcKey === false) {
            return '';
        }

        $sharedSecret = openssl_pkey_derive($localEcKey, $peerEcKey);
        openssl_pkey_free($localEcKey);
        openssl_pkey_free($peerEcKey);
        if (!is_string($sharedSecret) || strlen($sharedSecret) < 32) {
            return '';
        }

        $prk = $this->hkdf($sharedSecret, $userAuthSecret, "WebPush: info\x00" . $userPublicKey . $localPublicKey, 32);

        $keyInfo = "Content-Encoding: aes128gcm\x00";
        $key = $this->hkdf($prk, str_repeat("\x00", 12), $keyInfo, 16);
        $nonceInfo = "Content-Encoding: nonce\x00";
        $nonce = $this->hkdf($prk, str_repeat("\x00", 12), $nonceInfo, 12);

        $salt = random_bytes(16);
        $rs = 4096;
        $rsBytes = pack('N', $rs);
        $idLength = 6;
        $header = $salt . $rsBytes . chr($idLength) . 'aes128gcm';

        $recordHeader = $header;
        $keyId = $header;

        $tag = '';
        $ciphertext = openssl_encrypt(
            $plaintext,
            'aes-128-gcm',
            $key,
            OPENSSL_RAW_DATA,
            $nonce,
            $tag,
            $recordHeader,
            16
        );
        if ($ciphertext === false) {
            return '';
        }

        return $header . $ciphertext . $tag;
    }

    /**
     * Build a PEM string for an EC private key from raw 32-byte scalar.
     */
    private function buildEcPem(string $privateKeyDer): string
    {
        $der = "\x30\x30\x02\x01\x00\x30\x13\x06\x07\x2a\x86\x48\xce\x3d\x02\x01\x06\x08\x2a\x86\x48\xce\x3d\x03\x01\x07\x04\x22" . $privateKeyDer;
        return "-----BEGIN EC PRIVATE KEY-----\n" . chunk_split(base64_encode($der), 64, "\n") . "-----END EC PRIVATE KEY-----\n";
    }

    /**
     * HKDF-SHA256 key derivation.
     */
    private function hkdf(string $ikm, string $salt, string $info, int $length): string
    {
        $prk = hash_hmac('sha256', $ikm, $salt, true);
        $t = '';
        $okm = '';
        $i = 1;
        while (strlen($okm) < $length) {
            $t = hash_hmac('sha256', $t . $info . chr($i), $prk, true);
            $okm .= $t;
            $i++;
        }
        return substr($okm, 0, $length);
    }

    private function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    private function base64UrlDecode(string $data): string
    {
        $data = strtr($data, '-_', '+/');
        $mod = strlen($data) % 4;
        if ($mod > 0) {
            $data .= str_repeat('=', 4 - $mod);
        }
        return base64_decode($data, true) ?: '';
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
        $responseHdrs = [];
        if (function_exists('http_get_last_response_headers')) {
            $responseHdrs[] = 'HTTP/1.1 ' . (http_get_last_response_code() ?? '');
            foreach (http_get_last_response_headers() ?? [] as $n => $v) {
                $responseHdrs[] = $n . ': ' . $v;
            }
        } else {
            $responseHdrs = $http_response_header ?? [];
        }
        foreach ($responseHdrs as $line) {
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
