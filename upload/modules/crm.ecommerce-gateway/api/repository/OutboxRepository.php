<?php
declare(strict_types=1);

namespace Module\Crm\EcommerceGateway\Repository;

use Api\System\Library\Support\Ulid;
use PDO;

/**
 * Transactional Outbox persistence for asynchronous webhook deliveries (E-COM-04 §3, E-COM-10).
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
    public function findPendingEvents(int $limit = 20, bool $forUpdate = false): array
    {
        $now = $this->now();
        $limit = max(1, min(100, $limit));

        $sql = 'SELECT * FROM ecommerce_outbox_events
                WHERE status = "pending" AND next_attempt_at <= :now
                ORDER BY id ASC
                LIMIT ' . $limit;

        if ($forUpdate) {
            $driver = (string)$this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
            if ($driver === 'mysql') {
                $sql .= ' FOR UPDATE SKIP LOCKED';
            }
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['now' => $now]);

        /** @var list<array<string,mixed>> */
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Atomically claims pending events using MySQL UPDATE ... LIMIT or transaction lock (E-COM-13 §3).
     * Returns claimed events marked as 'delivering'.
     *
     * @return list<array<string,mixed>>
     */
    public function claimPendingEvents(int $limit = 20): array
    {
        $limit = max(1, min(100, $limit));
        $now = $this->now();
        $driver = (string)$this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME);

        if ($driver === 'mysql') {
            // Under MySQL: select for update skip locked in transaction or batch update
            try {
                $this->pdo->beginTransaction();
                $stmt = $this->pdo->prepare(
                    'SELECT id FROM ecommerce_outbox_events
                     WHERE status = "pending" AND next_attempt_at <= :now
                     ORDER BY id ASC
                     LIMIT ' . $limit . ' FOR UPDATE SKIP LOCKED'
                );
                $stmt->execute(['now' => $now]);
                $ids = $stmt->fetchAll(PDO::FETCH_COLUMN);

                if (empty($ids)) {
                    $this->pdo->commit();
                    return [];
                }

                $placeholders = implode(',', array_fill(0, count($ids), '?'));
                $updateStmt = $this->pdo->prepare(
                    "UPDATE ecommerce_outbox_events
                     SET status = 'delivering', last_attempt_at = ?, updated_at = ?
                     WHERE id IN ({$placeholders})"
                );
                $params = array_merge([$now, $now], $ids);
                $updateStmt->execute($params);

                $fetchStmt = $this->pdo->prepare(
                    "SELECT * FROM ecommerce_outbox_events WHERE id IN ({$placeholders}) ORDER BY id ASC"
                );
                $fetchStmt->execute($ids);
                /** @var list<array<string,mixed>> $events */
                $events = $fetchStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

                $this->pdo->commit();
                return $events;
            } catch (\Throwable $e) {
                if ($this->pdo->inTransaction()) {
                    $this->pdo->rollBack();
                }
                // Fallback to findPendingEvents + markDelivering
            }
        }

        // Fallback for SQLite / other drivers
        $events = $this->findPendingEvents($limit);
        $claimed = [];
        foreach ($events as $event) {
            if ($this->markDelivering((int)$event['id'])) {
                $event['status'] = 'delivering';
                $claimed[] = $event;
            }
        }

        return $claimed;
    }

    /**
     * Acquires a named MySQL advisory lock via GET_LOCK(:name, :timeout) (E-COM-13 §3).
     * For non-MySQL drivers, falls back to true (single-node assumption).
     */
    public function acquireLock(string $lockName = 'ecom_outbox_lock', int $timeoutSeconds = 0): bool
    {
        $driver = (string)$this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        if ($driver !== 'mysql') {
            return true;
        }

        try {
            $stmt = $this->pdo->prepare('SELECT GET_LOCK(:name, :timeout)');
            $stmt->execute([
                'name' => $lockName,
                'timeout' => max(0, $timeoutSeconds),
            ]);
            return (int)$stmt->fetchColumn() === 1;
        } catch (\Throwable $e) {
            error_log('[OutboxRepository] acquireLock error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Releases a named MySQL advisory lock via RELEASE_LOCK(:name) (E-COM-13 §3).
     */
    public function releaseLock(string $lockName = 'ecom_outbox_lock'): bool
    {
        $driver = (string)$this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        if ($driver !== 'mysql') {
            return true;
        }

        try {
            $stmt = $this->pdo->prepare('SELECT RELEASE_LOCK(:name)');
            $stmt->execute(['name' => $lockName]);
            return (int)$stmt->fetchColumn() === 1;
        } catch (\Throwable $e) {
            error_log('[OutboxRepository] releaseLock error: ' . $e->getMessage());
            return false;
        }
    }

    public function markDelivering(int $id): bool
    {
        $now = $this->now();
        $stmt = $this->pdo->prepare(
            'UPDATE ecommerce_outbox_events
             SET status = "delivering", last_attempt_at = :last_attempt_at, updated_at = :updated_at
             WHERE id = :id AND status IN ("pending", "delivering")'
        );

        return $stmt->execute([
            'id' => $id,
            'last_attempt_at' => $now,
            'updated_at' => $now,
        ]);
    }

    /**
     * Resets a claimed event back to pending immediately (e.g., when execution time guard interrupts batch).
     */
    public function markPending(int $id): bool
    {
        $stmt = $this->pdo->prepare(
            'UPDATE ecommerce_outbox_events
             SET status = "pending", updated_at = :now
             WHERE id = :id AND status = "delivering"'
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
                 delivered_at = :delivered_at,
                 last_http_code = :http_code,
                 last_response_body = :response_body,
                 last_error = NULL,
                 updated_at = :updated_at
             WHERE id = :id'
        );

        return $stmt->execute([
            'id' => $id,
            'http_code' => $httpCode,
            'response_body' => $responseBody !== null ? mb_substr($responseBody, 0, 4000) : null,
            'delivered_at' => $now,
            'updated_at' => $now,
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
     * Replays Dead Letter Queue events back to pending.
     *
     * @param int $storeId Store ID to replay for (0 for all stores)
     * @param int|null $eventId Optional specific outbox event ID
     * @return int Number of events reset to pending
     */
    public function replayDeadLetter(int $storeId = 0, ?int $eventId = null): int
    {
        $now = $this->now();
        $conditions = ['status IN ("failed", "dead")'];
        $params = ['next_attempt_at' => $now, 'updated_at' => $now];

        if ($storeId > 0) {
            $conditions[] = 'store_id = :store_id';
            $params['store_id'] = $storeId;
        }

        if ($eventId !== null && $eventId > 0) {
            $conditions[] = 'id = :event_id';
            $params['event_id'] = $eventId;
        }

        $sql = 'UPDATE ecommerce_outbox_events
                SET status = "pending", attempts = 0, next_attempt_at = :next_attempt_at, updated_at = :updated_at
                WHERE ' . implode(' AND ', $conditions);

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return $stmt->rowCount();
    }

    /**
     * Returns dead letter queue events (status IN ('failed', 'dead')).
     *
     * @return list<array<string,mixed>>
     */
    public function getDeadLetterEvents(int $storeId = 0, int $limit = 50, int $offset = 0): array
    {
        $limit = max(1, min(200, $limit));
        $offset = max(0, $offset);

        $sql = 'SELECT * FROM ecommerce_outbox_events WHERE status IN ("failed", "dead")';
        $params = [];

        if ($storeId > 0) {
            $sql .= ' AND store_id = :store_id';
            $params['store_id'] = $storeId;
        }

        $sql .= ' ORDER BY id DESC LIMIT ' . $limit . ' OFFSET ' . $offset;

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        /** @var list<array<string,mixed>> */
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Automatically archives stale dead-letter queue records older than $days (E-COM-10 §3).
     *
     * @param int $days Age threshold in days (default 30)
     * @return int Number of records deleted/archived
     */
    public function archiveStaleDeadLetterEvents(int $days = 30): int
    {
        $cutoff = gmdate('Y-m-d H:i:s', time() - max(1, $days) * 86400);

        $stmt = $this->pdo->prepare(
            'DELETE FROM ecommerce_outbox_events
             WHERE status IN ("failed", "dead") AND updated_at < :cutoff'
        );
        $stmt->execute(['cutoff' => $cutoff]);

        return $stmt->rowCount();
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
                 WHERE (crm_task_id = :task_id OR (:has_public_id != "" AND crm_task_public_id = :public_id))
                 ORDER BY id DESC LIMIT 1'
            );
            $stmt->execute(['task_id' => $taskId, 'has_public_id' => $taskPublicId, 'public_id' => $taskPublicId]);
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
            'SELECT id, public_id, name, store_url, api_key, webhook_url, webhook_secret_encrypted, api_secret_encrypted, status, locale
             FROM ecommerce_stores WHERE id = :id AND deleted_at IS NULL LIMIT 1'
        );
        $stmt->execute(['id' => $storeId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    }
}
