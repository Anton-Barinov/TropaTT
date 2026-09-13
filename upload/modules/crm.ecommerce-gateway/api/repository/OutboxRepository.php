<?php
declare(strict_types=1);

namespace Module\Crm\EcommerceGateway\Repository;

use Api\System\Library\Support\Ulid;
use PDO;

/**
 * Transactional Outbox persistence for asynchronous webhook deliveries (E-COM-04 §3).
 */
final class OutboxRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    private function now(): string
    {
        return gmdate('Y-m-d H:i:s');
    }

    /**
     * @param array{
     *     store_id: int,
     *     external_order_id: string,
     *     crm_task_id?: ?int,
     *     crm_task_public_id?: ?string,
     *     old_status?: ?string,
     *     new_status: string,
     *     external_status: string,
     *     payload: array<string,mixed>,
     *     sync_initiator?: string,
     *     max_attempts?: int
     * } $data
     * @return array<string,mixed>
     */
    public function createEvent(array $data): array
    {
        $publicId = Ulid::generate('obx');
        $now = $this->now();
        $payloadJson = json_encode($data['payload'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        $stmt = $this->pdo->prepare(
            'INSERT INTO ecommerce_outbox_events (
                public_id, store_id, event_type, external_order_id,
                crm_task_id, crm_task_public_id, old_status, new_status, external_status,
                payload_json, status, attempts, max_attempts, next_attempt_at,
                sync_initiator, created_at, updated_at
            ) VALUES (
                :public_id, :store_id, :event_type, :external_order_id,
                :crm_task_id, :crm_task_public_id, :old_status, :new_status, :external_status,
                :payload_json, "pending", 0, :max_attempts, :next_attempt_at,
                :sync_initiator, :created_at, :updated_at
            )'
        );

        $stmt->execute([
            'public_id' => $publicId,
            'store_id' => (int)$data['store_id'],
            'event_type' => (string)($data['event_type'] ?? 'order.status_changed'),
            'external_order_id' => (string)$data['external_order_id'],
            'crm_task_id' => isset($data['crm_task_id']) ? (int)$data['crm_task_id'] : null,
            'crm_task_public_id' => $data['crm_task_public_id'] ?? null,
            'old_status' => $data['old_status'] ?? null,
            'new_status' => (string)$data['new_status'],
            'external_status' => (string)$data['external_status'],
            'payload_json' => $payloadJson,
            'max_attempts' => (int)($data['max_attempts'] ?? 8),
            'next_attempt_at' => $now,
            'sync_initiator' => (string)($data['sync_initiator'] ?? 'crm'),
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $id = (int)$this->pdo->lastInsertId();

        return [
            'id' => $id,
            'public_id' => $publicId,
            'store_id' => (int)$data['store_id'],
            'external_order_id' => (string)$data['external_order_id'],
            'crm_task_id' => $data['crm_task_id'] ?? null,
            'crm_task_public_id' => $data['crm_task_public_id'] ?? null,
            'old_status' => $data['old_status'] ?? null,
            'new_status' => (string)$data['new_status'],
            'external_status' => (string)$data['external_status'],
            'payload_json' => $payloadJson,
            'status' => 'pending',
            'attempts' => 0,
            'max_attempts' => (int)($data['max_attempts'] ?? 8),
            'next_attempt_at' => $now,
            'sync_initiator' => (string)($data['sync_initiator'] ?? 'crm'),
            'created_at' => $now,
            'updated_at' => $now,
        ];
    }

    /**
     * @return list<array<string,mixed>>
     */
    public function findPendingEvents(int $limit = 20): array
    {
        $now = $this->now();
        $limit = max(1, min(100, $limit));

        $stmt = $this->pdo->prepare(
            'SELECT * FROM ecommerce_outbox_events
             WHERE status = "pending" AND next_attempt_at <= :now
             ORDER BY id ASC
             LIMIT ' . $limit
        );
        $stmt->execute(['now' => $now]);

        /** @var list<array<string,mixed>> */
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function markDelivering(int $id): bool
    {
        $stmt = $this->pdo->prepare(
            'UPDATE ecommerce_outbox_events
             SET status = "delivering", last_attempt_at = :now, updated_at = :now
             WHERE id = :id AND status = "pending"'
        );

        return $stmt->execute([
            'id' => $id,
            'now' => $this->now(),
        ]);
    }

    public function markDelivered(int $id, int $httpCode, ?string $responseBody = null): bool
    {
        $now = $this->now();
        $stmt = $this->pdo->prepare(
            'UPDATE ecommerce_outbox_events
             SET status = "delivered",
                 delivered_at = :now,
                 last_http_code = :http_code,
                 last_response_body = :response_body,
                 last_error = NULL,
                 updated_at = :now
             WHERE id = :id'
        );

        return $stmt->execute([
            'id' => $id,
            'http_code' => $httpCode,
            'response_body' => $responseBody !== null ? mb_substr($responseBody, 0, 4000) : null,
            'now' => $now,
        ]);
    }

    public function markFailed(
        int $id,
        int $attempts,
        int $maxAttempts,
        int $delaySeconds,
        ?int $httpCode,
        ?string $errorMessage,
        ?string $responseBody = null
    ): bool {
        $now = $this->now();
        $isFinal = $attempts >= $maxAttempts;
        $nextStatus = $isFinal ? 'failed' : 'pending';
        $nextAttemptAt = gmdate('Y-m-d H:i:s', time() + max(5, $delaySeconds));

        $stmt = $this->pdo->prepare(
            'UPDATE ecommerce_outbox_events
             SET status = :status,
                 attempts = :attempts,
                 next_attempt_at = :next_attempt,
                 last_http_code = :http_code,
                 last_error = :error,
                 last_response_body = :response_body,
                 updated_at = :now
             WHERE id = :id'
        );

        return $stmt->execute([
            'id' => $id,
            'status' => $nextStatus,
            'attempts' => $attempts,
            'next_attempt' => $nextAttemptAt,
            'http_code' => $httpCode,
            'error' => $errorMessage !== null ? mb_substr($errorMessage, 0, 4000) : null,
            'response_body' => $responseBody !== null ? mb_substr($responseBody, 0, 4000) : null,
            'now' => $now,
        ]);
    }

    /**
     * Resolves the linked store_id and external_order_id for a given CRM task.
     *
     * @return array{store_id: int, external_order_id: string}|null
     */
    public function findStoreForTask(int $taskId, string $taskPublicId = ''): ?array
    {
        // 1. Check ecommerce_order_sync_log
        if ($taskId > 0) {
            $stmt = $this->pdo->prepare(
                'SELECT store_id, external_order_id FROM ecommerce_order_sync_log
                 WHERE crm_task_id = :task_id AND external_order_id IS NOT NULL AND external_order_id != ""
                 ORDER BY id DESC LIMIT 1'
            );
            $stmt->execute(['task_id' => $taskId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (is_array($row) && !empty($row['store_id']) && !empty($row['external_order_id'])) {
                return ['store_id' => (int)$row['store_id'], 'external_order_id' => (string)$row['external_order_id']];
            }
        }

        // 2. Check existing ecommerce_outbox_events
        if ($taskId > 0 || $taskPublicId !== '') {
            $stmt = $this->pdo->prepare(
                'SELECT store_id, external_order_id FROM ecommerce_outbox_events
                 WHERE (crm_task_id = :task_id OR (:public_id != "" AND crm_task_public_id = :public_id))
                 ORDER BY id DESC LIMIT 1'
            );
            $stmt->execute(['task_id' => $taskId, 'public_id' => $taskPublicId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (is_array($row) && !empty($row['store_id']) && !empty($row['external_order_id'])) {
                return ['store_id' => (int)$row['store_id'], 'external_order_id' => (string)$row['external_order_id']];
            }
        }

        // 3. Check ecommerce_idempotency snapshot
        if ($taskPublicId !== '') {
            $like = '%"task_public_id":"' . $taskPublicId . '"%';
            $stmt = $this->pdo->prepare(
                'SELECT store_id, external_id as external_order_id FROM ecommerce_idempotency
                 WHERE response_json LIKE :like
                 ORDER BY id DESC LIMIT 1'
            );
            $stmt->execute(['like' => $like]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (is_array($row) && !empty($row['store_id']) && !empty($row['external_order_id'])) {
                return ['store_id' => (int)$row['store_id'], 'external_order_id' => (string)$row['external_order_id']];
            }
        }

        // 4. Check tasks table source_id and source_url
        if ($taskId > 0) {
            $stmt = $this->pdo->prepare(
                'SELECT t.source_id, t.source_url FROM tasks t WHERE t.id = :task_id LIMIT 1'
            );
            $stmt->execute(['task_id' => $taskId]);
            $taskRow = $stmt->fetch(PDO::FETCH_ASSOC);
            if (is_array($taskRow) && !empty($taskRow['source_id'])) {
                $externalId = (string)$taskRow['source_id'];
                $sourceUrl = trim((string)($taskRow['source_url'] ?? ''));

                if ($sourceUrl !== '') {
                    $storeStmt = $this->pdo->prepare(
                        'SELECT id FROM ecommerce_stores WHERE store_url = :url AND deleted_at IS NULL LIMIT 1'
                    );
                    $storeStmt->execute(['url' => $sourceUrl]);
                    $storeId = $storeStmt->fetchColumn();
                    if ($storeId) {
                        return ['store_id' => (int)$storeId, 'external_order_id' => $externalId];
                    }
                }

                // If only 1 active store exists, match it
                $countStmt = $this->pdo->query('SELECT id FROM ecommerce_stores WHERE status = "active" AND deleted_at IS NULL LIMIT 2');
                $stores = $countStmt->fetchAll(PDO::FETCH_COLUMN);
                if (count($stores) === 1) {
                    return ['store_id' => (int)$stores[0], 'external_order_id' => $externalId];
                }
            }
        }

        return null;
    }

    /**
     * @return array<string,mixed>|null
     */
    public function getStore(int $storeId): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, public_id, name, webhook_url, webhook_secret_encrypted, api_secret_encrypted, status, locale
             FROM ecommerce_stores WHERE id = :id AND deleted_at IS NULL LIMIT 1'
        );
        $stmt->execute(['id' => $storeId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    }
}
