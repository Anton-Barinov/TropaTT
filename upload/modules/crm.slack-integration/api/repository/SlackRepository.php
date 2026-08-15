<?php
declare(strict_types=1);

namespace Module\Crm\SlackIntegration\Repository;

use PDO;

final class SlackRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    private function publicId(string $prefix): string
    {
        return $prefix . '_' . bin2hex(random_bytes(10));
    }

    private function now(): string
    {
        return gmdate('Y-m-d H:i:s');
    }

    // ── Connections ──

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listConnections(): array
    {
        $stmt = $this->pdo->query('SELECT id, public_id, name, channel, last_status, last_message, created_by_user_id, created_at, updated_at FROM module_slack_connections ORDER BY created_at DESC');
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getConnection(string $publicId): ?array
    {
        $stmt = $this->pdo->prepare('SELECT id, public_id, name, channel, webhook_url_encrypted, last_status, last_message, created_by_user_id, created_at, updated_at FROM module_slack_connections WHERE public_id = :public_id LIMIT 1');
        $stmt->execute(['public_id' => $publicId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return is_array($row) ? $row : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getConnectionById(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT id, public_id, name, channel, webhook_url_encrypted, last_status, last_message, created_by_user_id, created_at, updated_at FROM module_slack_connections WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return is_array($row) ? $row : null;
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function createConnection(array $data): array
    {
        $publicId = $this->publicId('slk');
        $now = $this->now();
        $stmt = $this->pdo->prepare('INSERT INTO module_slack_connections (public_id, name, channel, webhook_url_encrypted, created_by_user_id, created_at, updated_at) VALUES (:public_id, :name, :channel, :webhook, :created_by_user_id, :created_at, :updated_at)');
        $stmt->execute([
            'public_id' => $publicId,
            'name' => $data['name'],
            'channel' => $data['channel'] ?? null,
            'webhook' => $data['webhook_url_encrypted'],
            'created_by_user_id' => $data['created_by_user_id'] ?? null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        return $this->getConnection($publicId) ?? ['public_id' => $publicId];
    }

    /**
     * @param array<string, mixed> $data
     */
    public function updateConnection(string $publicId, array $data): void
    {
        $sets = [];
        $params = ['public_id' => $publicId];
        foreach (['name', 'channel', 'webhook_url_encrypted'] as $field) {
            if (array_key_exists($field, $data)) {
                $sets[] = "$field = :$field";
                $params[$field] = $data[$field];
            }
        }
        if ($sets === []) {
            return;
        }
        $params['updated_at'] = $this->now();
        $sets[] = 'updated_at = :updated_at';
        $this->pdo->prepare('UPDATE module_slack_connections SET ' . implode(', ', $sets) . ' WHERE public_id = :public_id')->execute($params);
    }

    public function updateConnectionLastCheck(string $publicId, string $status, string $message): void
    {
        $stmt = $this->pdo->prepare('UPDATE module_slack_connections SET last_status = :status, last_message = :message, updated_at = :updated_at WHERE public_id = :public_id');
        $stmt->execute([
            'status' => $status,
            'message' => mb_substr($message, 0, 500),
            'updated_at' => $this->now(),
            'public_id' => $publicId,
        ]);
    }

    public function deleteConnection(string $publicId): void
    {
        $this->pdo->prepare('DELETE FROM module_slack_connections WHERE public_id = :public_id')->execute(['public_id' => $publicId]);
    }

    // ── Rules ──

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listRules(): array
    {
        $stmt = $this->pdo->query('SELECT r.id, r.public_id, r.connection_id, r.event_code, r.text_template, r.is_enabled, r.created_by_user_id, r.created_at, r.updated_at, c.name AS connection_name FROM module_slack_rules r LEFT JOIN module_slack_connections c ON c.id = r.connection_id ORDER BY r.created_at DESC');
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getRule(string $publicId): ?array
    {
        $stmt = $this->pdo->prepare('SELECT id, public_id, connection_id, event_code, text_template, is_enabled, created_by_user_id, created_at, updated_at FROM module_slack_rules WHERE public_id = :public_id LIMIT 1');
        $stmt->execute(['public_id' => $publicId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return is_array($row) ? $row : null;
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function createRule(array $data): array
    {
        $publicId = $this->publicId('slr');
        $now = $this->now();
        $stmt = $this->pdo->prepare('INSERT INTO module_slack_rules (public_id, connection_id, event_code, text_template, is_enabled, created_by_user_id, created_at, updated_at) VALUES (:public_id, :connection_id, :event_code, :text_template, :is_enabled, :created_by_user_id, :created_at, :updated_at)');
        $stmt->execute([
            'public_id' => $publicId,
            'connection_id' => $data['connection_id'],
            'event_code' => $data['event_code'],
            'text_template' => $data['text_template'],
            'is_enabled' => (int)($data['is_enabled'] ?? 1),
            'created_by_user_id' => $data['created_by_user_id'] ?? null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        return $this->getRule($publicId) ?? ['public_id' => $publicId];
    }

    public function deleteRule(string $publicId): void
    {
        $this->pdo->prepare('DELETE FROM module_slack_rules WHERE public_id = :public_id')->execute(['public_id' => $publicId]);
    }

    // ── Deliveries ──

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function enqueueDelivery(array $data): array
    {
        $publicId = $this->publicId('sld');
        $now = $this->now();
        $stmt = $this->pdo->prepare('INSERT INTO module_slack_deliveries (public_id, connection_id, rule_id, event_code, payload_json, status, attempts, next_run_at, created_at, updated_at) VALUES (:public_id, :connection_id, :rule_id, :event_code, :payload_json, :status, :attempts, :next_run_at, :created_at, :updated_at)');
        $stmt->execute([
            'public_id' => $publicId,
            'connection_id' => $data['connection_id'] ?? null,
            'rule_id' => $data['rule_id'] ?? null,
            'event_code' => $data['event_code'] ?? null,
            'payload_json' => isset($data['payload_json']) ? (is_string($data['payload_json']) ? $data['payload_json'] : json_encode($data['payload_json'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) : null,
            'status' => 'queued',
            'attempts' => 0,
            'next_run_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        return $this->getDelivery($publicId) ?? ['public_id' => $publicId];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getDelivery(string $publicId): ?array
    {
        $stmt = $this->pdo->prepare('SELECT id, public_id, connection_id, rule_id, event_code, payload_json, status, attempts, response_code, last_error, next_run_at, sent_at, created_at, updated_at FROM module_slack_deliveries WHERE public_id = :public_id LIMIT 1');
        $stmt->execute(['public_id' => $publicId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return is_array($row) ? $row : null;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listDeliveries(int $limit = 50): array
    {
        $limit = max(1, min(200, $limit));
        $stmt = $this->pdo->query('SELECT d.id, d.public_id, d.connection_id, d.rule_id, d.event_code, d.status, d.attempts, d.response_code, d.last_error, d.sent_at, d.created_at, c.name AS connection_name FROM module_slack_deliveries d LEFT JOIN module_slack_connections c ON c.id = d.connection_id ORDER BY d.created_at DESC LIMIT ' . $limit);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Claim the next runnable delivery with an exclusive lease.
     *
     * @return array<string, mixed>|null
     */
    public function claimNextDelivery(): ?array
    {
        $now = $this->now();
        $leaseToken = bin2hex(random_bytes(16));
        $this->pdo->beginTransaction();
        try {
            $stmt = $this->pdo->prepare("SELECT id FROM module_slack_deliveries WHERE status = 'queued' AND (next_run_at IS NULL OR next_run_at <= :now) AND (locked_at IS NULL OR locked_at <= :expired) ORDER BY next_run_at ASC LIMIT 1 FOR UPDATE");
            $stmt->execute(['now' => $now, 'expired' => gmdate('Y-m-d H:i:s', time() - 300)]);
            $id = $stmt->fetchColumn();
            if ($id === false) {
                $this->pdo->commit();
                return null;
            }
            $upd = $this->pdo->prepare('UPDATE module_slack_deliveries SET locked_at = :locked_at, status = :status WHERE id = :id');
            $upd->execute(['locked_at' => $now, 'status' => 'sending', 'id' => (int)$id]);
            $this->pdo->commit();
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }

        $stmt = $this->pdo->prepare('SELECT id, public_id, connection_id, rule_id, event_code, payload_json, status, attempts, response_code, last_error, next_run_at, sent_at, created_at, updated_at FROM module_slack_deliveries WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => (int)$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!is_array($row)) {
            return null;
        }
        $row['_lease_token'] = $leaseToken;
        return $row;
    }

    public function markDelivered(string $publicId, int $responseCode): void
    {
        $stmt = $this->pdo->prepare("UPDATE module_slack_deliveries SET status = 'sent', response_code = :code, attempts = attempts + 1, locked_at = NULL, last_error = NULL, sent_at = :sent_at, updated_at = :updated_at WHERE public_id = :public_id");
        $stmt->execute([
            'code' => $responseCode,
            'sent_at' => $this->now(),
            'updated_at' => $this->now(),
            'public_id' => $publicId,
        ]);
    }

    public function markFailed(string $publicId, int $responseCode, string $error, int $maxRetries, int $backoffSeconds): void
    {
        $delivery = $this->getDelivery($publicId);
        if (!$delivery) {
            return;
        }
        $attempts = (int)($delivery['attempts'] ?? 0) + 1;
        if ($attempts >= $maxRetries) {
            $stmt = $this->pdo->prepare("UPDATE module_slack_deliveries SET status = 'failed', response_code = :code, attempts = :attempts, locked_at = NULL, last_error = :error, updated_at = :updated_at WHERE public_id = :public_id");
            $stmt->execute([
                'code' => $responseCode,
                'attempts' => $attempts,
                'error' => mb_substr($error, 0, 500),
                'updated_at' => $this->now(),
                'public_id' => $publicId,
            ]);
            return;
        }
        $next = gmdate('Y-m-d H:i:s', time() + max(1, $backoffSeconds) * $attempts);
        $stmt = $this->pdo->prepare("UPDATE module_slack_deliveries SET status = 'queued', response_code = :code, attempts = :attempts, locked_at = NULL, last_error = :error, next_run_at = :next, updated_at = :updated_at WHERE public_id = :public_id");
        $stmt->execute([
            'code' => $responseCode,
            'attempts' => $attempts,
            'error' => mb_substr($error, 0, 500),
            'next' => $next,
            'updated_at' => $this->now(),
            'public_id' => $publicId,
        ]);
    }

    public function releaseDelivery(string $publicId): void
    {
        $stmt = $this->pdo->prepare("UPDATE module_slack_deliveries SET status = 'queued', locked_at = NULL, updated_at = :updated_at WHERE public_id = :public_id AND status = 'sending'");
        $stmt->execute(['updated_at' => $this->now(), 'public_id' => $publicId]);
    }
}
