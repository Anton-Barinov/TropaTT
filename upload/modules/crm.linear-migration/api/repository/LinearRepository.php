<?php
declare(strict_types=1);

namespace Module\Crm\LinearMigration\Repository;

use PDO;

final class LinearRepository
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
        $stmt = $this->pdo->query('SELECT id, public_id, name, workspace_name, last_check_status, last_check_message, created_by_user_id, created_at, updated_at FROM module_linear_connections ORDER BY created_at DESC');
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getConnection(string $publicId): ?array
    {
        $stmt = $this->pdo->prepare('SELECT id, public_id, name, workspace_name, api_key_encrypted, last_check_status, last_check_message, created_by_user_id, created_at, updated_at FROM module_linear_connections WHERE public_id = :public_id LIMIT 1');
        $stmt->execute(['public_id' => $publicId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return is_array($row) ? $row : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getConnectionById(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT id, public_id, name, workspace_name, api_key_encrypted, last_check_status, last_check_message, created_by_user_id, created_at, updated_at FROM module_linear_connections WHERE id = :id LIMIT 1');
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
        $publicId = $this->publicId('lnc');
        $now = $this->now();
        $stmt = $this->pdo->prepare('INSERT INTO module_linear_connections (public_id, name, workspace_name, api_key_encrypted, created_by_user_id, created_at, updated_at) VALUES (:public_id, :name, :workspace_name, :api_key, :created_by_user_id, :created_at, :updated_at)');
        $stmt->execute([
            'public_id' => $publicId,
            'name' => $data['name'],
            'workspace_name' => $data['workspace_name'] ?? null,
            'api_key' => $data['api_key_encrypted'],
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
        foreach (['name', 'workspace_name', 'api_key_encrypted'] as $field) {
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
        $this->pdo->prepare('UPDATE module_linear_connections SET ' . implode(', ', $sets) . ' WHERE public_id = :public_id')->execute($params);
    }

    public function updateConnectionLastCheck(string $publicId, string $status, string $message): void
    {
        $stmt = $this->pdo->prepare('UPDATE module_linear_connections SET last_check_status = :status, last_check_message = :message, updated_at = :updated_at WHERE public_id = :public_id');
        $stmt->execute([
            'status' => $status,
            'message' => mb_substr($message, 0, 500),
            'updated_at' => $this->now(),
            'public_id' => $publicId,
        ]);
    }

    public function deleteConnection(string $publicId): void
    {
        $this->pdo->prepare('DELETE FROM module_linear_connections WHERE public_id = :public_id')->execute(['public_id' => $publicId]);
    }

    // ── Jobs ──

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listJobs(?int $actorUserId = null): array
    {
        if ($actorUserId !== null) {
            $stmt = $this->pdo->prepare('SELECT j.*, c.name AS connection_name FROM module_linear_import_jobs j LEFT JOIN module_linear_connections c ON c.id = j.connection_id WHERE j.created_by_user_id = :uid ORDER BY j.created_at DESC');
            $stmt->execute(['uid' => $actorUserId]);
        } else {
            $stmt = $this->pdo->query('SELECT j.*, c.name AS connection_name FROM module_linear_import_jobs j LEFT JOIN module_linear_connections c ON c.id = j.connection_id ORDER BY j.created_at DESC');
        }
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getJob(string $publicId): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM module_linear_import_jobs WHERE public_id = :public_id LIMIT 1');
        $stmt->execute(['public_id' => $publicId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return is_array($row) ? $row : null;
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function createJob(array $data): array
    {
        $publicId = $this->publicId('lnj');
        $now = $this->now();
        $stmt = $this->pdo->prepare('INSERT INTO module_linear_import_jobs (public_id, connection_id, status, mode, source_team_ids_json, target_project_public_id, options_json, created_by_user_id, created_at, updated_at) VALUES (:public_id, :connection_id, :status, :mode, :source_team_ids_json, :target_project_public_id, :options_json, :created_by_user_id, :created_at, :updated_at)');
        $stmt->execute([
            'public_id' => $publicId,
            'connection_id' => $data['connection_id'],
            'status' => 'draft',
            'mode' => $data['mode'],
            'source_team_ids_json' => isset($data['source_team_ids_json']) ? (is_string($data['source_team_ids_json']) ? $data['source_team_ids_json'] : json_encode($data['source_team_ids_json'], JSON_UNESCAPED_UNICODE)) : null,
            'target_project_public_id' => $data['target_project_public_id'] ?? null,
            'options_json' => is_string($data['options_json']) ? $data['options_json'] : json_encode($data['options_json'] ?? [], JSON_UNESCAPED_UNICODE),
            'created_by_user_id' => $data['created_by_user_id'] ?? null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        return $this->getJob($publicId) ?? ['public_id' => $publicId];
    }

    public function updateJobStatus(string $publicId, string $status): void
    {
        $now = $this->now();
        $sets = ['status = :status', 'updated_at = :updated_at'];
        $params = ['public_id' => $publicId, 'status' => $status, 'updated_at' => $now];
        if ($status === 'running' || $status === 'queued') {
            $sets[] = 'started_at = COALESCE(started_at, :started_at)';
            $params['started_at'] = $now;
        }
        if (in_array($status, ['completed', 'failed', 'cancelled'], true)) {
            $sets[] = 'finished_at = :finished_at';
            $params['finished_at'] = $now;
        }
        $this->pdo->prepare('UPDATE module_linear_import_jobs SET ' . implode(', ', $sets) . ' WHERE public_id = :public_id')->execute($params);
    }

    /**
     * @param array<string, mixed> $stats
     */
    public function updateJobProgress(string $publicId, string $step, float $percent, array $stats): void
    {
        $stmt = $this->pdo->prepare('UPDATE module_linear_import_jobs SET current_step = :step, progress_percent = :percent, stats_json = :stats, updated_at = :updated_at WHERE public_id = :public_id');
        $stmt->execute([
            'step' => $step,
            'percent' => $percent,
            'stats' => json_encode($stats, JSON_UNESCAPED_UNICODE),
            'updated_at' => $this->now(),
            'public_id' => $publicId,
        ]);
    }

    // ── Job items ──

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listJobItems(string $jobPublicId, string $status = '', string $sourceType = '', int $limit = 100, int $offset = 0): array
    {
        $where = ['i.job_id = (SELECT id FROM module_linear_import_jobs WHERE public_id = :job_pub)'];
        $params = ['job_pub' => $jobPublicId];
        if ($status !== '') {
            $where[] = 'i.status = :status';
            $params['status'] = $status;
        }
        if ($sourceType !== '') {
            $where[] = 'i.source_type = :source_type';
            $params['source_type'] = $sourceType;
        }
        $stmt = $this->pdo->prepare('SELECT * FROM module_linear_import_items i WHERE ' . implode(' AND ', $where) . ' ORDER BY i.id ASC LIMIT ' . max(1, min(500, $limit)) . ' OFFSET ' . max(0, $offset));
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * @param array<string, mixed> $data
     */
    public function upsertJobItem(int $jobId, string $sourceType, string $sourceId, array $data): void
    {
        $now = $this->now();
        $existing = $this->findJobItem($jobId, $sourceType, $sourceId);
        if ($existing) {
            $sets = ['updated_at = :updated_at'];
            $params = ['id' => $existing['id'], 'updated_at' => $now];
            foreach (['source_parent_id', 'target_type', 'target_public_id', 'status', 'error_code', 'error_message', 'payload_json'] as $field) {
                if (array_key_exists($field, $data)) {
                    $sets[] = "$field = :$field";
                    $params[$field] = $data[$field];
                }
            }
            $this->pdo->prepare('UPDATE module_linear_import_items SET ' . implode(', ', $sets) . ' WHERE id = :id')->execute($params);
            return;
        }
        $stmt = $this->pdo->prepare('INSERT INTO module_linear_import_items (job_id, source_type, source_id, source_parent_id, target_type, target_public_id, status, error_code, error_message, payload_json, created_at, updated_at) VALUES (:job_id, :source_type, :source_id, :source_parent_id, :target_type, :target_public_id, :status, :error_code, :error_message, :payload_json, :created_at, :updated_at)');
        $stmt->execute([
            'job_id' => $jobId,
            'source_type' => $sourceType,
            'source_id' => $sourceId,
            'source_parent_id' => $data['source_parent_id'] ?? null,
            'target_type' => $data['target_type'] ?? null,
            'target_public_id' => $data['target_public_id'] ?? null,
            'status' => $data['status'] ?? 'pending',
            'error_code' => $data['error_code'] ?? null,
            'error_message' => $data['error_message'] ?? null,
            'payload_json' => isset($data['payload_json']) ? (is_string($data['payload_json']) ? $data['payload_json'] : json_encode($data['payload_json'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) : null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findJobItem(int $jobId, string $sourceType, string $sourceId): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM module_linear_import_items WHERE job_id = :job_id AND source_type = :source_type AND source_id = :source_id LIMIT 1');
        $stmt->execute(['job_id' => $jobId, 'source_type' => $sourceType, 'source_id' => $sourceId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return is_array($row) ? $row : null;
    }

    /**
     * @return array<string, int>
     */
    public function countItemsByStatus(int $jobId): array
    {
        $stmt = $this->pdo->prepare('SELECT status, COUNT(*) AS cnt FROM module_linear_import_items WHERE job_id = :job_id GROUP BY status');
        $stmt->execute(['job_id' => $jobId]);
        $result = ['pending' => 0, 'imported' => 0, 'skipped' => 0, 'failed' => 0];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
            $result[(string)$row['status']] = (int)$row['cnt'];
        }
        return $result;
    }

    // ── Logs ──

    public function addLog(string $jobPublicId, string $level, ?string $step, string $message): void
    {
        $stmt = $this->pdo->prepare('INSERT INTO module_linear_import_logs (job_id, level, step, message, created_at) VALUES ((SELECT id FROM module_linear_import_jobs WHERE public_id = :pub), :level, :step, :message, :created_at)');
        $stmt->execute([
            'pub' => $jobPublicId,
            'level' => $level,
            'step' => $step,
            'message' => mb_substr($message, 0, 2000),
            'created_at' => $this->now(),
        ]);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listLogs(string $jobPublicId, int $limit = 50): array
    {
        $stmt = $this->pdo->prepare('SELECT l.level, l.step, l.message, l.created_at FROM module_linear_import_logs l WHERE l.job_id = (SELECT id FROM module_linear_import_jobs WHERE public_id = :pub) ORDER BY l.created_at DESC LIMIT ' . max(1, min(200, $limit)));
        $stmt->execute(['pub' => $jobPublicId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    // ── User mappings ──

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listUserMappings(int $connectionId): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM module_linear_user_mappings WHERE connection_id = :conn_id ORDER BY display_name ASC');
        $stmt->execute(['conn_id' => $connectionId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * @param array<string, mixed> $data
     */
    public function upsertUserMapping(int $connectionId, string $linearUserId, array $data): void
    {
        $now = $this->now();
        $stmt = $this->pdo->prepare('INSERT INTO module_linear_user_mappings (connection_id, linear_user_id, display_name, email, crm_user_public_id, mapping_status, created_at, updated_at) VALUES (:conn_id, :lid, :name, :email, :crm_pub, :status, :created_at, :updated_at) ON DUPLICATE KEY UPDATE display_name = VALUES(display_name), email = VALUES(email), crm_user_public_id = VALUES(crm_user_public_id), mapping_status = VALUES(mapping_status), updated_at = VALUES(updated_at)');
        $stmt->execute([
            'conn_id' => $connectionId,
            'lid' => $linearUserId,
            'name' => $data['display_name'] ?? null,
            'email' => $data['email'] ?? null,
            'crm_pub' => $data['crm_user_public_id'] ?? null,
            'status' => $data['mapping_status'] ?? 'unmapped',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    /**
     * @return string|null
     */
    public function mappedUserPublicId(int $connectionId, string $linearUserId): ?string
    {
        $stmt = $this->pdo->prepare('SELECT crm_user_public_id FROM module_linear_user_mappings WHERE connection_id = :conn_id AND linear_user_id = :lid AND crm_user_public_id IS NOT NULL LIMIT 1');
        $stmt->execute(['conn_id' => $connectionId, 'lid' => $linearUserId]);
        $val = $stmt->fetchColumn();
        return $val !== false ? (string)$val : null;
    }

    // ── Settings ──

    /**
     * @return array<string, mixed>
     */
    public function getSettings(): array
    {
        $defaults = [
            'request_timeout_seconds' => 30,
            'max_retries' => 3,
            'batch_size' => 50,
            'max_issues_per_job' => 0,
            'include_comments_by_default' => true,
        ];
        $stmt = $this->pdo->prepare("SELECT setting_key, setting_value FROM module_linear_settings WHERE module_name = 'crm.linear-migration'");
        $stmt->execute();
        $settings = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
            $settings[(string)$row['setting_key']] = json_decode((string)$row['setting_value'], true);
        }
        return array_merge($defaults, $settings);
    }

    public function setSetting(string $key, mixed $value): void
    {
        $stmt = $this->pdo->prepare("INSERT INTO module_linear_settings (module_name, setting_key, setting_value, updated_at) VALUES ('crm.linear-migration', :skey, :svalue, :updated_at) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_at = VALUES(updated_at)");
        $stmt->execute([
            'skey' => $key,
            'svalue' => json_encode($value, JSON_UNESCAPED_UNICODE),
            'updated_at' => $this->now(),
        ]);
    }
}
