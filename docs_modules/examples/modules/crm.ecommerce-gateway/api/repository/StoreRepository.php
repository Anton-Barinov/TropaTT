<?php
declare(strict_types=1);

namespace Module\Crm\EcommerceGateway\Repository;

use Api\System\Library\Support\Ulid;
use PDO;

final class StoreRepository
{
    private const STORE_COLUMNS = 'id, public_id, organization_id, name, cms_type, store_url, description, '
        . 'api_key, api_secret_encrypted, api_secret_hint, secondary_api_secret_encrypted, '
        . 'secondary_secret_expires_at, secret_rotated_at, ip_whitelist, rate_limit_per_minute, '
        . 'webhook_url, webhook_secret_encrypted, status, locale, settings_json, created_by_user_id, '
        . 'last_ingest_at, last_error, row_version, created_at, updated_at, deleted_at';

    public function __construct(private readonly PDO $pdo)
    {
    }

    private function now(): string
    {
        return gmdate('Y-m-d H:i:s');
    }

    // ── Routing targets (settings default project/assignee) ──────────────
    //
    // The settings screen works with public ids (prj_… / usr_…), while ingest
    // reads the stored routing defaults as ints. These helpers bridge the two so
    // the API accepts either form without breaking existing numeric clients.

    /** Resolve a numeric id or a prj_… public id to the internal project id. */
    public function resolveProjectId(mixed $value, ?int $organizationId = null): ?int
    {
        return $this->resolveEntityId('projects', 'prj_', $value, $organizationId);
    }

    /** Resolve a numeric id or a usr_… public id to the internal user id. */
    public function resolveUserId(mixed $value, ?int $organizationId = null): ?int
    {
        return $this->resolveEntityId('users', 'usr_', $value, $organizationId);
    }

    /**
     * @return array<int,string> internal id => project public id
     */
    public function projectPublicIdsByIds(array $ids, ?int $organizationId = null): array
    {
        return $this->publicIdsByIds('projects', $ids, $organizationId);
    }

    /**
     * @return array<int,string> internal id => user public id
     */
    public function userPublicIdsByIds(array $ids, ?int $organizationId = null): array
    {
        return $this->publicIdsByIds('users', $ids, $organizationId);
    }

    private function resolveEntityId(string $table, string $prefix, mixed $value, ?int $organizationId): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }
        $id = null;
        if (is_int($value) || (is_string($value) && ctype_digit(trim($value)))) {
            $candidate = (int)trim((string)$value);
            $id = $candidate > 0 ? $candidate : null;
        } elseif (is_string($value) && str_starts_with(trim($value), $prefix)) {
            $publicId = trim($value);
            if ($table === 'projects') {
                $stmt = $this->pdo->prepare('SELECT id FROM projects WHERE public_id = :public_id'
                    . ($organizationId !== null ? ' AND organization_id = :organization_id' : '') . ' LIMIT 1');
            } else {
                $stmt = $this->pdo->prepare('SELECT u.id FROM users u WHERE u.public_id = :public_id'
                    . ' AND u.deleted_at IS NULL AND u.is_active = 1'
                    . ($organizationId !== null ? ' AND EXISTS (SELECT 1 FROM organization_memberships om WHERE om.user_id = u.id AND om.organization_id = :organization_id)' : '')
                    . ' LIMIT 1');
            }
            $params = ['public_id' => $publicId];
            if ($organizationId !== null) {
                $params['organization_id'] = $organizationId;
            }
            $stmt->execute($params);
            $found = $stmt->fetchColumn();
            $id = $found === false ? null : (int)$found;
        }

        if ($id === null) {
            return null;
        }
        $map = $this->publicIdsByIds($table, [$id], $organizationId);
        return isset($map[$id]) ? $id : null;
    }

    /**
     * @param list<int> $ids
     * @return array<int,string>
     */
    private function publicIdsByIds(string $table, array $ids, ?int $organizationId = null): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids), static fn(int $id): bool => $id > 0)));
        if ($ids === []) {
            return [];
        }
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        if ($table === 'projects') {
            $sql = "SELECT id, public_id FROM projects WHERE id IN ({$placeholders})";
            $params = $ids;
            if ($organizationId !== null) {
                $sql .= ' AND organization_id = ?';
                $params[] = $organizationId;
            }
        } else {
            $sql = "SELECT u.id, u.public_id FROM users u WHERE u.id IN ({$placeholders}) AND u.deleted_at IS NULL AND u.is_active = 1";
            $params = $ids;
            if ($organizationId !== null) {
                $sql .= ' AND EXISTS (SELECT 1 FROM organization_memberships om WHERE om.user_id = u.id AND om.organization_id = ?)';
                $params[] = $organizationId;
            }
        }
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $map = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
            $map[(int)$row['id']] = (string)$row['public_id'];
        }

        return $map;
    }

    // ── Stores ──────────────────────────────────────────────────────────

    /**
     * @return list<array<string,mixed>>
     */
    public function listStores(int $limit = 100, bool $includeDeleted = false, ?int $organizationId = null): array
    {
        $limit = max(1, min(500, $limit));
        $conditions = [];
        $params = [];
        if (!$includeDeleted) {
            $conditions[] = 'deleted_at IS NULL';
        }
        if ($organizationId !== null) {
            $conditions[] = 'organization_id = :organization_id';
            $params['organization_id'] = $organizationId;
        }
        $where = $conditions === [] ? '' : ' WHERE ' . implode(' AND ', $conditions);
        $sql = 'SELECT ' . self::STORE_COLUMNS . ' FROM ecommerce_stores' . $where
            . ' ORDER BY id DESC LIMIT ' . $limit;
        if ($params === []) {
            $stmt = $this->pdo->query($sql);
        } else {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
        }

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * @return array<string,mixed>|null
     */
    public function getStoreByPublicId(string $publicId, bool $includeDeleted = false, ?int $organizationId = null): ?array
    {
        $sql = 'SELECT ' . self::STORE_COLUMNS . ' FROM ecommerce_stores WHERE public_id = :public_id';
        $params = ['public_id' => $publicId];
        if (!$includeDeleted) {
            $sql .= ' AND deleted_at IS NULL';
        }
        if ($organizationId !== null) {
            $sql .= ' AND organization_id = :organization_id';
            $params['organization_id'] = $organizationId;
        }
        $sql .= ' LIMIT 1';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    }

    /**
     * Lookup used by the ingestion path: paused/disabled and soft-deleted stores
     * are still returned so the caller can answer 403 instead of 404.
     *
     * @return array<string,mixed>|null
     */
    public function getStoreByApiKey(string $apiKey): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT ' . self::STORE_COLUMNS . ' FROM ecommerce_stores WHERE api_key = :api_key LIMIT 1'
        );
        $stmt->execute(['api_key' => $apiKey]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    }

    public function apiKeyExists(string $apiKey): bool
    {
        $stmt = $this->pdo->prepare('SELECT 1 FROM ecommerce_stores WHERE api_key = :api_key LIMIT 1');
        $stmt->execute(['api_key' => $apiKey]);

        return (bool)$stmt->fetchColumn();
    }

    /**
     * @param array<string,mixed> $data
     * @return array<string,mixed>
     */
    public function createStore(array $data): array
    {
        $publicId = Ulid::generate('stk');
        $now = $this->now();
        $stmt = $this->pdo->prepare(
            'INSERT INTO ecommerce_stores (
                public_id, organization_id, name, cms_type, store_url, description,
                api_key, api_secret_encrypted, api_secret_hint,
                ip_whitelist, rate_limit_per_minute,
                webhook_url, webhook_secret_encrypted,
                status, locale, settings_json,
                created_by_user_id, row_version, created_at, updated_at
            ) VALUES (
                :public_id, :organization_id, :name, :cms_type, :store_url, :description,
                :api_key, :api_secret_encrypted, :api_secret_hint,
                :ip_whitelist, :rate_limit_per_minute,
                :webhook_url, :webhook_secret_encrypted,
                :status, :locale, :settings_json,
                :created_by_user_id, 1, :created_at, :updated_at
            )'
        );
        $stmt->execute([
            'public_id' => $publicId,
            'organization_id' => isset($data['organization_id']) && (int)$data['organization_id'] > 0 ? (int)$data['organization_id'] : null,
            'name' => $data['name'],
            'cms_type' => $data['cms_type'] ?? 'custom',
            'store_url' => $data['store_url'] ?? null,
            'description' => $data['description'] ?? null,
            'api_key' => $data['api_key'],
            'api_secret_encrypted' => $data['api_secret_encrypted'],
            'api_secret_hint' => $data['api_secret_hint'] ?? null,
            'ip_whitelist' => $data['ip_whitelist'] ?? null,
            'rate_limit_per_minute' => (int)($data['rate_limit_per_minute'] ?? 120),
            'webhook_url' => $data['webhook_url'] ?? null,
            'webhook_secret_encrypted' => $data['webhook_secret_encrypted'] ?? null,
            'status' => $data['status'] ?? 'active',
            'locale' => $data['locale'] ?? 'ru-ru',
            'settings_json' => $data['settings_json'] ?? null,
            'created_by_user_id' => $data['created_by_user_id'] ?? null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return $this->getStoreByPublicId($publicId) ?? [];
    }

    /**
     * @param array<string,mixed> $set whitelisted columns only
     */
    public function updateStore(string $publicId, array $set, ?int $organizationId = null): bool
    {
        $allowed = [
            'name', 'cms_type', 'store_url', 'description',
            'api_secret_encrypted', 'api_secret_hint',
            'secondary_api_secret_encrypted', 'secondary_secret_expires_at', 'secret_rotated_at',
            'ip_whitelist', 'rate_limit_per_minute',
            'webhook_url', 'webhook_secret_encrypted',
            'status', 'locale', 'settings_json',
            'last_ingest_at', 'last_error',
        ];
        $set = array_intersect_key($set, array_flip($allowed));
        if ($set === []) {
            return false;
        }

        $assignments = [];
        $params = ['public_id' => $publicId];
        foreach ($set as $column => $value) {
            $assignments[] = '`' . $column . '` = :' . $column;
            $params[$column] = $value;
        }
        $assignments[] = '`row_version` = `row_version` + 1';
        $assignments[] = '`updated_at` = :updated_at';
        $params['updated_at'] = $this->now();

        $where = ' WHERE public_id = :public_id AND deleted_at IS NULL';
        if ($organizationId !== null) {
            $where .= ' AND organization_id = :organization_id';
            $params['organization_id'] = $organizationId;
        }
        $stmt = $this->pdo->prepare('UPDATE ecommerce_stores SET ' . implode(', ', $assignments) . $where);

        return $stmt->execute($params) && $stmt->rowCount() > 0;
    }

    public function softDeleteStore(string $publicId, ?int $organizationId = null): bool
    {
        $now = $this->now();
        $params = [
            'deleted_at' => $now,
            'status' => 'disabled',
            'updated_at' => $now,
            'public_id' => $publicId,
        ];
        if ($organizationId !== null) {
            $params['organization_id'] = $organizationId;
        }
        $stmt = $this->pdo->prepare(
            'UPDATE ecommerce_stores SET deleted_at = :deleted_at, status = :status, updated_at = :updated_at'
            . ' WHERE public_id = :public_id AND deleted_at IS NULL'
            . ($organizationId !== null ? ' AND organization_id = :organization_id' : '')
        );

        return $stmt->execute($params) && $stmt->rowCount() > 0;
    }

    // ── Anti-replay nonces ──────────────────────────────────────────────

    /**
     * Records a nonce. Returns false when the nonce was already used (replay).
     */
    public function recordNonce(int $storeId, string $nonce): bool
    {
        try {
            $stmt = $this->pdo->prepare(
                'INSERT INTO ecommerce_nonces (store_id, nonce, created_at) VALUES (:store_id, :nonce, :created_at)'
            );
            $stmt->execute([
                'store_id' => $storeId,
                'nonce' => $nonce,
                'created_at' => $this->now(),
            ]);
        } catch (\PDOException $e) {
            // A unique-key violation means replay. Any other database error must
            // surface instead of being silently treated as a security event.
            if ((string)$e->getCode() !== '23000') {
                throw $e;
            }

            return false;
        }

        return true;
    }

    public function pruneNonces(int $retentionSeconds): int
    {
        $cutoff = gmdate('Y-m-d H:i:s', time() - max(60, $retentionSeconds));
        $stmt = $this->pdo->prepare('DELETE FROM ecommerce_nonces WHERE created_at < :cutoff');
        $stmt->execute(['cutoff' => $cutoff]);

        return $stmt->rowCount();
    }

    // ── Ingest journal / sync log ──────────────────────────────────────

    /**
     * @param array<string,mixed> $data
     */
    public function recordIngestEvent(array $data): string
    {
        $publicId = Ulid::generate('evt');
        $stmt = $this->pdo->prepare(
            'INSERT INTO ecommerce_ingest_events (
                public_id, request_id, store_id, ingest_type, external_id, external_id_synthetic,
                status, http_status, error_code, entity_type, entity_public_id,
                payload_json, payload_bytes, duration_ms, ip, user_agent, created_at
            ) VALUES (
                :public_id, :request_id, :store_id, :ingest_type, :external_id, :external_id_synthetic,
                :status, :http_status, :error_code, :entity_type, :entity_public_id,
                :payload_json, :payload_bytes, :duration_ms, :ip, :user_agent, :created_at
            )'
        );
        $stmt->execute([
            'public_id' => $publicId,
            'request_id' => (string)($data['request_id'] ?? ''),
            'store_id' => (int)$data['store_id'],
            'ingest_type' => (string)$data['ingest_type'],
            'external_id' => $data['external_id'] ?? null,
            'external_id_synthetic' => (int)($data['external_id_synthetic'] ?? 0),
            'status' => (string)($data['status'] ?? 'received'),
            'http_status' => $data['http_status'] ?? null,
            'error_code' => $data['error_code'] ?? null,
            'entity_type' => $data['entity_type'] ?? null,
            'entity_public_id' => $data['entity_public_id'] ?? null,
            'payload_json' => $data['payload_json'] ?? null,
            'payload_bytes' => (int)($data['payload_bytes'] ?? 0),
            'duration_ms' => $data['duration_ms'] ?? null,
            'ip' => $data['ip'] ?? null,
            'user_agent' => $data['user_agent'] ?? null,
            'created_at' => $this->now(),
        ]);

        return $publicId;
    }

    /**
     * @param array<string,mixed> $data
     */
    public function logOrderSync(array $data): void
    {
        try {
            $stmt = $this->pdo->prepare(
                'INSERT INTO ecommerce_order_sync_log (
                    public_id, store_id, external_order_id, crm_task_id, intake_item_id,
                    direction, status, http_code, request_payload, response_payload,
                    error_message, ip_address, created_at
                ) VALUES (
                    :public_id, :store_id, :external_order_id, :crm_task_id, :intake_item_id,
                    :direction, :status, :http_code, :request_payload, :response_payload,
                    :error_message, :ip_address, :created_at
                )'
            );
            $stmt->execute([
                'public_id' => Ulid::generate('osl'),
                'store_id' => (int)$data['store_id'],
                'external_order_id' => $data['external_order_id'] ?? null,
                'crm_task_id' => $data['crm_task_id'] ?? null,
                'intake_item_id' => $data['intake_item_id'] ?? null,
                'direction' => (string)($data['direction'] ?? 'inbound'),
                'status' => (string)($data['status'] ?? 'received'),
                'http_code' => $data['http_code'] ?? null,
                'request_payload' => $data['request_payload'] ?? null,
                'response_payload' => $data['response_payload'] ?? null,
                'error_message' => $data['error_message'] ?? null,
                'ip_address' => $data['ip_address'] ?? null,
                'created_at' => $this->now(),
            ]);
        } catch (\Throwable $e) {
            error_log('[EcommerceGateway] order sync log write failed: ' . $e->getMessage());
        }
    }

    // ── Security log ───────────────────────────────────────────────────

    /**
     * @param array<string,mixed> $details
     */
    public function logSecurityEvent(
        string $eventType,
        string $severity,
        ?int $storeId,
        ?string $ip,
        ?string $userAgent,
        array $details = []
    ): void {
        try {
            $stmt = $this->pdo->prepare(
                'INSERT INTO ecommerce_security_log (
                    public_id, store_id, event_type, severity, ip_address, user_agent, details_json, created_at
                ) VALUES (
                    :public_id, :store_id, :event_type, :severity, :ip_address, :user_agent, :details_json, :created_at
                )'
            );
            $stmt->execute([
                'public_id' => Ulid::generate('sec'),
                'store_id' => $storeId,
                'event_type' => $eventType,
                'severity' => $severity,
                'ip_address' => $ip,
                'user_agent' => $userAgent,
                'details_json' => $details === []
                    ? null
                    : json_encode($details, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'created_at' => $this->now(),
            ]);
        } catch (\Throwable $e) {
            error_log('[EcommerceGateway] security log write failed: ' . $e->getMessage());
        }
    }

    // ── Audit ──────────────────────────────────────────────────────────

    /**
     * @param array<string,mixed> $details
     */
    public function audit(
        string $action,
        ?int $storeId,
        string $actorType,
        ?string $actorId,
        array $details = [],
        ?string $ip = null,
        ?string $userAgent = null
    ): void {
        try {
            $stmt = $this->pdo->prepare(
                'INSERT INTO ecommerce_audit_log (
                    public_id, store_id, actor_type, actor_id, action, ip, user_agent, details_json, created_at
                ) VALUES (
                    :public_id, :store_id, :actor_type, :actor_id, :action, :ip, :user_agent, :details_json, :created_at
                )'
            );
            $stmt->execute([
                'public_id' => Ulid::generate('aud'),
                'store_id' => $storeId,
                'actor_type' => $actorType,
                'actor_id' => $actorId,
                'action' => $action,
                'ip' => $ip,
                'user_agent' => $userAgent,
                'details_json' => $details === []
                    ? null
                    : json_encode($details, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'created_at' => $this->now(),
            ]);
        } catch (\Throwable $e) {
            // Audit must never break the request it is observing.
            error_log('[EcommerceGateway] audit write failed: ' . $e->getMessage());
        }
    }
}
