<?php
declare(strict_types=1);

namespace Module\Crm\GitlabIntegration\Repository;

use PDO;

final class GitLabRepository
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
        $stmt = $this->pdo->query('SELECT id, public_id, name, base_url, last_status, last_message, created_by_user_id, created_at, updated_at FROM module_gitlab_connections ORDER BY created_at DESC');
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getConnection(string $publicId): ?array
    {
        $stmt = $this->pdo->prepare('SELECT id, public_id, name, base_url, token_encrypted, last_status, last_message, created_by_user_id, created_at, updated_at FROM module_gitlab_connections WHERE public_id = :public_id LIMIT 1');
        $stmt->execute(['public_id' => $publicId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return is_array($row) ? $row : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getConnectionById(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT id, public_id, name, base_url, token_encrypted, last_status, last_message, created_by_user_id, created_at, updated_at FROM module_gitlab_connections WHERE id = :id LIMIT 1');
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
        $publicId = $this->publicId('glc');
        $now = $this->now();
        $stmt = $this->pdo->prepare('INSERT INTO module_gitlab_connections (public_id, name, base_url, token_encrypted, created_by_user_id, created_at, updated_at) VALUES (:public_id, :name, :base_url, :token, :created_by_user_id, :created_at, :updated_at)');
        $stmt->execute([
            'public_id' => $publicId,
            'name' => $data['name'],
            'base_url' => $data['base_url'] ?? 'https://gitlab.com/api/v4',
            'token' => $data['token_encrypted'],
            'created_by_user_id' => $data['created_by_user_id'] ?? null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        return $this->getConnection($publicId) ?? ['public_id' => $publicId];
    }

    public function updateConnectionLastCheck(string $publicId, string $status, string $message): void
    {
        $stmt = $this->pdo->prepare('UPDATE module_gitlab_connections SET last_status = :status, last_message = :message, updated_at = :updated_at WHERE public_id = :public_id');
        $stmt->execute(['status' => $status, 'message' => mb_substr($message, 0, 500), 'updated_at' => $this->now(), 'public_id' => $publicId]);
    }

    /**
     * @param array<string, mixed> $data Allowed keys: name, base_url, token_encrypted.
     */
    public function updateConnection(string $publicId, array $data): void
    {
        $sets = ['updated_at = :updated_at'];
        $params = ['updated_at' => $this->now(), 'public_id' => $publicId];
        foreach (['name', 'base_url', 'token_encrypted'] as $field) {
            if (array_key_exists($field, $data)) {
                $sets[] = "$field = :$field";
                $params[$field] = $data[$field];
            }
        }
        $this->pdo->prepare('UPDATE module_gitlab_connections SET ' . implode(', ', $sets) . ' WHERE public_id = :public_id')->execute($params);
    }

    public function deleteConnection(string $publicId): void
    {
        $this->pdo->prepare('DELETE FROM module_gitlab_connections WHERE public_id = :public_id')->execute(['public_id' => $publicId]);
    }

    // ── Project links ──

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listLinks(?int $connectionId = null): array
    {
        if ($connectionId !== null) {
            $stmt = $this->pdo->prepare('SELECT l.*, c.name AS connection_name FROM module_gitlab_project_links l LEFT JOIN module_gitlab_connections c ON c.id = l.connection_id WHERE l.connection_id = :cid ORDER BY l.created_at DESC');
            $stmt->execute(['cid' => $connectionId]);
        } else {
            $stmt = $this->pdo->query('SELECT l.*, c.name AS connection_name FROM module_gitlab_project_links l LEFT JOIN module_gitlab_connections c ON c.id = l.connection_id ORDER BY l.created_at DESC');
        }
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getLink(string $publicId): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM module_gitlab_project_links WHERE public_id = :public_id LIMIT 1');
        $stmt->execute(['public_id' => $publicId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return is_array($row) ? $row : null;
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function createLink(array $data): array
    {
        $publicId = $this->publicId('gll');
        $now = $this->now();
        $stmt = $this->pdo->prepare('INSERT INTO module_gitlab_project_links (public_id, connection_id, project_path, project_public_id, webhook_secret_encrypted, is_active, created_by_user_id, created_at, updated_at) VALUES (:public_id, :connection_id, :project_path, :project_public_id, :webhook_secret, :is_active, :created_by_user_id, :created_at, :updated_at)');
        $stmt->execute([
            'public_id' => $publicId,
            'connection_id' => $data['connection_id'],
            'project_path' => $data['project_path'],
            'project_public_id' => $data['project_public_id'],
            'webhook_secret' => $data['webhook_secret_encrypted'] ?? null,
            'is_active' => (int)($data['is_active'] ?? 1),
            'created_by_user_id' => $data['created_by_user_id'] ?? null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        return $this->getLink($publicId) ?? ['public_id' => $publicId];
    }

    public function deleteLink(string $publicId): void
    {
        $this->pdo->prepare('DELETE FROM module_gitlab_project_links WHERE public_id = :public_id')->execute(['public_id' => $publicId]);
    }

    public function markDirty(string $publicId): void
    {
        $stmt = $this->pdo->prepare('UPDATE module_gitlab_project_links SET is_dirty = 1, updated_at = :updated_at WHERE public_id = :public_id');
        $stmt->execute(['updated_at' => $this->now(), 'public_id' => $publicId]);
    }

    public function markSynced(string $publicId): void
    {
        $stmt = $this->pdo->prepare('UPDATE module_gitlab_project_links SET is_dirty = 0, last_synced_at = :synced, updated_at = :updated_at WHERE public_id = :public_id');
        $stmt->execute(['synced' => $this->now(), 'updated_at' => $this->now(), 'public_id' => $publicId]);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findLinkByProject(int $connectionId, string $projectPath): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM module_gitlab_project_links WHERE connection_id = :cid AND project_path = :project_path LIMIT 1');
        $stmt->execute(['cid' => $connectionId, 'project_path' => $projectPath]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return is_array($row) ? $row : null;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listActiveLinks(): array
    {
        $stmt = $this->pdo->query('SELECT * FROM module_gitlab_project_links WHERE is_active = 1 ORDER BY id ASC');
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    // ── Sync items ──

    /**
     * @param array<string, mixed> $data
     */
    public function upsertSyncItem(int $linkId, string $sourceType, string $sourceId, array $data): void
    {
        $now = $this->now();
        $existing = $this->findSyncItem($linkId, $sourceType, $sourceId);
        if ($existing) {
            $sets = ['updated_at = :updated_at'];
            $params = ['id' => $existing['id'], 'updated_at' => $now];
            foreach (['target_type', 'target_public_id', 'status', 'payload_json'] as $field) {
                if (array_key_exists($field, $data)) {
                    $sets[] = "$field = :$field";
                    $params[$field] = $data[$field];
                }
            }
            $this->pdo->prepare('UPDATE module_gitlab_sync_items SET ' . implode(', ', $sets) . ' WHERE id = :id')->execute($params);
            return;
        }
        $stmt = $this->pdo->prepare('INSERT INTO module_gitlab_sync_items (link_id, source_type, source_id, target_type, target_public_id, status, payload_json, created_at, updated_at) VALUES (:link_id, :source_type, :source_id, :target_type, :target_public_id, :status, :payload_json, :created_at, :updated_at)');
        $stmt->execute([
            'link_id' => $linkId,
            'source_type' => $sourceType,
            'source_id' => $sourceId,
            'target_type' => $data['target_type'] ?? null,
            'target_public_id' => $data['target_public_id'] ?? null,
            'status' => $data['status'] ?? 'pending',
            'payload_json' => isset($data['payload_json']) ? (is_string($data['payload_json']) ? $data['payload_json'] : json_encode($data['payload_json'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) : null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findSyncItem(int $linkId, string $sourceType, string $sourceId): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM module_gitlab_sync_items WHERE link_id = :link_id AND source_type = :source_type AND source_id = :source_id LIMIT 1');
        $stmt->execute(['link_id' => $linkId, 'source_type' => $sourceType, 'source_id' => $sourceId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return is_array($row) ? $row : null;
    }

    // ── Logs ──

    public function addLog(?int $linkId, string $level, string $message): void
    {
        $stmt = $this->pdo->prepare('INSERT INTO module_gitlab_sync_logs (link_id, level, message, created_at) VALUES (:link_id, :level, :message, :created_at)');
        $stmt->execute(['link_id' => $linkId, 'level' => $level, 'message' => mb_substr($message, 0, 2000), 'created_at' => $this->now()]);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listLogs(?int $linkId = null, int $limit = 50): array
    {
        if ($linkId !== null) {
            $stmt = $this->pdo->prepare('SELECT * FROM module_gitlab_sync_logs WHERE link_id = :link_id ORDER BY created_at DESC LIMIT ' . max(1, min(200, $limit)));
            $stmt->execute(['link_id' => $linkId]);
        } else {
            $stmt = $this->pdo->query('SELECT * FROM module_gitlab_sync_logs ORDER BY created_at DESC LIMIT ' . max(1, min(200, $limit)));
        }
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * @return array<string, mixed>
     */
    public function getSettings(): array
    {
        return [
            'request_timeout_seconds' => 30,
            'max_retries' => 3,
            'batch_size' => 100,
            'default_base_url' => 'https://gitlab.com/api/v4',
            'sync_comments' => true,
            'poll_interval_minutes' => 15,
        ];
    }
}
