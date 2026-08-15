<?php
declare(strict_types=1);

namespace Module\Crm\NotionMigration\Repository;

use PDO;

final class NotionMigrationRepository
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

    public function listConnections(): array
    {
        $stmt = $this->pdo->query('SELECT id, public_id, name, workspace_name, last_check_status, last_check_message, created_by_user_id, created_at, updated_at FROM module_notion_connections ORDER BY created_at DESC');
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function getConnection(string $publicId): ?array
    {
        $stmt = $this->pdo->prepare('SELECT id, public_id, name, workspace_name, token_encrypted, last_check_status, last_check_message, created_by_user_id, created_at, updated_at FROM module_notion_connections WHERE public_id = :public_id LIMIT 1');
        $stmt->execute(['public_id' => $publicId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return is_array($row) ? $row : null;
    }

    public function getConnectionById(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT id, public_id, name, workspace_name, token_encrypted, last_check_status, last_check_message, created_by_user_id, created_at, updated_at FROM module_notion_connections WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return is_array($row) ? $row : null;
    }

    public function createConnection(array $data): array
    {
        $publicId = $this->publicId('ncf');
        $now = $this->now();
        $stmt = $this->pdo->prepare('INSERT INTO module_notion_connections (public_id, name, workspace_name, token_encrypted, created_by_user_id, created_at, updated_at) VALUES (:public_id, :name, :workspace_name, :token_encrypted, :created_by_user_id, :created_at, :updated_at)');
        $stmt->execute([
            'public_id' => $publicId,
            'name' => $data['name'],
            'workspace_name' => $data['workspace_name'] ?? null,
            'token_encrypted' => $data['token_encrypted'],
            'created_by_user_id' => $data['created_by_user_id'] ?? null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        return $this->getConnection($publicId) ?? ['public_id' => $publicId];
    }

    public function updateConnection(string $publicId, array $data): void
    {
        $sets = [];
        $params = ['public_id' => $publicId];
        foreach ($data as $key => $value) {
            $sets[] = "$key = :$key";
            $params[$key] = $value;
        }
        $params['updated_at'] = $this->now();
        $sets[] = 'updated_at = :updated_at';
        $this->pdo->prepare('UPDATE module_notion_connections SET ' . implode(', ', $sets) . ' WHERE public_id = :public_id')->execute($params);
    }

    public function updateConnectionLastCheck(string $publicId, string $status, string $message): void
    {
        $now = $this->now();
        $stmt = $this->pdo->prepare('UPDATE module_notion_connections SET last_check_status = :status, last_check_message = :message, updated_at = :updated_at WHERE public_id = :public_id');
        $stmt->execute([
            'status' => $status,
            'message' => mb_substr($message, 0, 500),
            'updated_at' => $now,
            'public_id' => $publicId,
        ]);
    }

    public function deleteConnection(string $publicId): void
    {
        $this->pdo->prepare('DELETE FROM module_notion_connections WHERE public_id = :public_id')->execute(['public_id' => $publicId]);
    }

    public function findRunningJobsByConnection(int $connectionId): bool
    {
        $stmt = $this->pdo->prepare("SELECT id FROM module_notion_import_jobs WHERE connection_id = :conn_id AND status IN ('queued', 'running', 'paused') LIMIT 1");
        $stmt->execute(['conn_id' => $connectionId]);
        return $stmt->fetchColumn() !== false;
    }

    // ── Jobs ──

    public function listJobs(?int $actorUserId = null): array
    {
        if ($actorUserId !== null) {
            $stmt = $this->pdo->prepare('SELECT j.id, j.public_id, j.connection_id, j.status, j.mode, j.source_object_ids_json, j.target_root_space_public_id, j.options_json, j.current_step, j.progress_percent, j.stats_json, j.created_by_user_id, j.started_at, j.finished_at, j.created_at, j.updated_at, c.name AS connection_name FROM module_notion_import_jobs j LEFT JOIN module_notion_connections c ON c.id = j.connection_id WHERE j.created_by_user_id = :uid ORDER BY j.created_at DESC');
            $stmt->execute(['uid' => $actorUserId]);
        } else {
            $stmt = $this->pdo->query('SELECT j.id, j.public_id, j.connection_id, j.status, j.mode, j.source_object_ids_json, j.target_root_space_public_id, j.options_json, j.current_step, j.progress_percent, j.stats_json, j.created_by_user_id, j.started_at, j.finished_at, j.created_at, j.updated_at, c.name AS connection_name FROM module_notion_import_jobs j LEFT JOIN module_notion_connections c ON c.id = j.connection_id ORDER BY j.created_at DESC');
        }
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function getJob(string $publicId): ?array
    {
        $stmt = $this->pdo->prepare('SELECT j.id, j.public_id, j.connection_id, j.status, j.mode, j.source_object_ids_json, j.target_root_space_public_id, j.options_json, j.current_step, j.progress_percent, j.stats_json, j.created_by_user_id, j.started_at, j.finished_at, j.created_at, j.updated_at, c.name AS connection_name FROM module_notion_import_jobs j LEFT JOIN module_notion_connections c ON c.id = j.connection_id WHERE j.public_id = :public_id LIMIT 1');
        $stmt->execute(['public_id' => $publicId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!is_array($row)) {
            return null;
        }
        if (isset($row['source_object_ids_json']) && is_string($row['source_object_ids_json'])) {
            $row['source_object_ids'] = json_decode($row['source_object_ids_json'], true);
        }
        if (isset($row['options_json']) && is_string($row['options_json'])) {
            $row['options'] = json_decode($row['options_json'], true);
        }
        if (isset($row['stats_json']) && is_string($row['stats_json'])) {
            $row['stats'] = json_decode($row['stats_json'], true);
        }
        return $row;
    }

    public function createJob(array $data): array
    {
        $publicId = $this->publicId('nij');
        $now = $this->now();
        $stmt = $this->pdo->prepare('INSERT INTO module_notion_import_jobs (public_id, connection_id, status, mode, source_object_ids_json, target_root_space_public_id, options_json, created_by_user_id, created_at, updated_at) VALUES (:public_id, :connection_id, :status, :mode, :source_object_ids_json, :target_root_space_public_id, :options_json, :created_by_user_id, :created_at, :updated_at)');
        $stmt->execute([
            'public_id' => $publicId,
            'connection_id' => $data['connection_id'],
            'status' => 'draft',
            'mode' => $data['mode'],
            'source_object_ids_json' => json_encode($data['source_object_ids_json'], JSON_UNESCAPED_UNICODE),
            'target_root_space_public_id' => $data['target_root_space_public_id'] ?? null,
            'options_json' => json_encode($data['options_json'], JSON_UNESCAPED_UNICODE),
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

        $this->pdo->prepare('UPDATE module_notion_import_jobs SET ' . implode(', ', $sets) . ' WHERE public_id = :public_id')->execute($params);
    }

    public function updateJobProgress(string $publicId, string $step, float $percent, array $stats): void
    {
        $now = $this->now();
        $stmt = $this->pdo->prepare('UPDATE module_notion_import_jobs SET current_step = :step, progress_percent = :percent, stats_json = :stats, updated_at = :updated_at WHERE public_id = :public_id');
        $stmt->execute([
            'step' => $step,
            'percent' => $percent,
            'stats' => json_encode($stats, JSON_UNESCAPED_UNICODE),
            'updated_at' => $now,
            'public_id' => $publicId,
        ]);
    }

    public function resetFailedItems(string $publicId): int
    {
        $stmt = $this->pdo->prepare("UPDATE module_notion_import_items SET status = 'pending', error_code = NULL, error_message = NULL, attempts = 0 WHERE job_id = (SELECT id FROM module_notion_import_jobs WHERE public_id = :public_id) AND status = 'failed'");
        $stmt->execute(['public_id' => $publicId]);
        return $stmt->rowCount();
    }

    // ── Job Items ──

    public function listJobItems(string $jobPublicId, string $sourceType = '', string $status = '', int $limit = 50, int $page = 1): array
    {
        $where = ['i.job_id = (SELECT id FROM module_notion_import_jobs WHERE public_id = :job_pub)'];
        $params = ['job_pub' => $jobPublicId];
        if ($sourceType !== '') {
            $where[] = 'i.source_type = :source_type';
            $params['source_type'] = $sourceType;
        }
        if ($status !== '') {
            $where[] = 'i.status = :status';
            $params['status'] = $status;
        }
        $offset = max(0, ($page - 1) * $limit);
        $stmt = $this->pdo->prepare('SELECT i.id, i.job_id, i.source_type, i.source_id, i.source_key, i.source_parent_id, i.target_type, i.target_public_id, i.status, i.checksum, i.source_updated_at, i.error_code, i.error_message, i.attempts, i.payload_json, i.created_at, i.updated_at FROM module_notion_import_items i WHERE ' . implode(' AND ', $where) . ' ORDER BY i.id ASC LIMIT ' . $limit . ' OFFSET ' . $offset);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function upsertJobItem(int $jobId, string $sourceType, string $sourceId, array $data): void
    {
        $now = $this->now();
        $existing = $this->findJobItem($jobId, $sourceType, $sourceId);
        if ($existing) {
            $sets = ['updated_at = :updated_at'];
            $params = ['id' => $existing['id'], 'updated_at' => $now];
            foreach (['source_key', 'source_parent_id', 'target_type', 'target_public_id', 'status', 'checksum', 'source_updated_at', 'error_code', 'error_message', 'attempts', 'payload_json'] as $field) {
                if (array_key_exists($field, $data)) {
                    $sets[] = "$field = :$field";
                    $params[$field] = $data[$field];
                }
            }
            $this->pdo->prepare('UPDATE module_notion_import_items SET ' . implode(', ', $sets) . ' WHERE id = :id')->execute($params);
        } else {
            $stmt = $this->pdo->prepare('INSERT INTO module_notion_import_items (job_id, source_type, source_id, source_key, source_parent_id, target_type, target_public_id, status, checksum, source_updated_at, error_code, error_message, attempts, payload_json, created_at, updated_at) VALUES (:job_id, :source_type, :source_id, :source_key, :source_parent_id, :target_type, :target_public_id, :status, :checksum, :source_updated_at, :error_code, :error_message, :attempts, :payload_json, :created_at, :updated_at)');
            $stmt->execute([
                'job_id' => $jobId,
                'source_type' => $sourceType,
                'source_id' => $sourceId,
                'source_key' => $data['source_key'] ?? null,
                'source_parent_id' => $data['source_parent_id'] ?? null,
                'target_type' => $data['target_type'] ?? null,
                'target_public_id' => $data['target_public_id'] ?? null,
                'status' => $data['status'] ?? 'pending',
                'checksum' => $data['checksum'] ?? null,
                'source_updated_at' => $data['source_updated_at'] ?? null,
                'error_code' => $data['error_code'] ?? null,
                'error_message' => $data['error_message'] ?? null,
                'attempts' => $data['attempts'] ?? 0,
                'payload_json' => isset($data['payload_json']) ? (is_string($data['payload_json']) ? $data['payload_json'] : json_encode($data['payload_json'], JSON_UNESCAPED_UNICODE)) : null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function findJobItem(int $jobId, string $sourceType, string $sourceId): ?array
    {
        $stmt = $this->pdo->prepare('SELECT id, job_id, source_type, source_id, source_key, source_parent_id, target_type, target_public_id, status, checksum, source_updated_at, error_code, error_message, attempts, payload_json, created_at, updated_at FROM module_notion_import_items WHERE job_id = :job_id AND source_type = :source_type AND source_id = :source_id LIMIT 1');
        $stmt->execute(['job_id' => $jobId, 'source_type' => $sourceType, 'source_id' => $sourceId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return is_array($row) ? $row : null;
    }

    public function findJobItemsByStatus(int $jobId, string $status, int $limit = 500, int $offset = 0): array
    {
        $stmt = $this->pdo->prepare('SELECT id, job_id, source_type, source_id, source_key, source_parent_id, target_type, target_public_id, status, checksum, source_updated_at, error_code, error_message, attempts, payload_json, created_at, updated_at FROM module_notion_import_items WHERE job_id = :job_id AND status = :status ORDER BY id ASC LIMIT ' . $limit . ' OFFSET ' . $offset);
        $stmt->execute(['job_id' => $jobId, 'status' => $status]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function countJobItemsByStatus(int $jobId): array
    {
        $stmt = $this->pdo->prepare("SELECT status, COUNT(*) AS cnt FROM module_notion_import_items WHERE job_id = :job_id GROUP BY status");
        $stmt->execute(['job_id' => $jobId]);
        $result = ['pending' => 0, 'processing' => 0, 'imported' => 0, 'skipped' => 0, 'failed' => 0];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
            $result[(string)$row['status']] = (int)$row['cnt'];
        }
        return $result;
    }

    public function findTargetForSource(int $jobId, string $sourceType, string $sourceId): ?string
    {
        $stmt = $this->pdo->prepare("SELECT target_public_id FROM module_notion_import_items WHERE job_id = :job_id AND source_type = :source_type AND source_id = :source_id AND status IN ('imported', 'skipped') LIMIT 1");
        $stmt->execute(['job_id' => $jobId, 'source_type' => $sourceType, 'source_id' => $sourceId]);
        $val = $stmt->fetchColumn();
        return $val !== false ? (string)$val : null;
    }

    // ── Job Logs ──

    public function addJobLog(string $jobPublicId, string $level, ?string $step, string $message, array $context = []): void
    {
        $now = $this->now();
        $stmt = $this->pdo->prepare('INSERT INTO module_notion_import_logs (job_id, level, step, message, context_json, created_at) VALUES ((SELECT id FROM module_notion_import_jobs WHERE public_id = :pub), :level, :step, :message, :context, :created_at)');
        $stmt->execute([
            'pub' => $jobPublicId,
            'level' => $level,
            'step' => $step,
            'message' => mb_substr($message, 0, 2000),
            'context' => $context !== [] ? json_encode($context, JSON_UNESCAPED_UNICODE) : null,
            'created_at' => $now,
        ]);
    }

    public function listJobLogs(string $jobPublicId, string $level = '', string $step = '', int $limit = 50, int $page = 1): array
    {
        $where = ['l.job_id = (SELECT id FROM module_notion_import_jobs WHERE public_id = :job_pub)'];
        $params = ['job_pub' => $jobPublicId];
        if ($level !== '') {
            $where[] = 'l.level = :level';
            $params['level'] = $level;
        }
        if ($step !== '') {
            $where[] = 'l.step = :step';
            $params['step'] = $step;
        }
        $offset = max(0, ($page - 1) * $limit);
        $stmt = $this->pdo->prepare('SELECT l.id, l.job_id, l.level, l.step, l.message, l.context_json, l.created_at FROM module_notion_import_logs l WHERE ' . implode(' AND ', $where) . ' ORDER BY l.created_at DESC LIMIT ' . $limit . ' OFFSET ' . $offset);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    // ── Report ──

    public function getJobReport(string $jobPublicId): array
    {
        $stmt = $this->pdo->prepare('SELECT j.id, j.public_id, j.connection_id, j.status, j.mode, j.source_object_ids_json, j.target_root_space_public_id, j.options_json, j.current_step, j.progress_percent, j.stats_json, j.created_by_user_id, j.started_at, j.finished_at, j.created_at, j.updated_at FROM module_notion_import_jobs j WHERE j.public_id = :pub LIMIT 1');
        $stmt->execute(['pub' => $jobPublicId]);
        $job = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$job) {
            return [];
        }

        $itemCounts = $this->countJobItemsByStatus((int)$job['id']);
        $stats = json_decode((string)($job['stats_json'] ?? '{}'), true) ?? [];

        return [
            'job_public_id' => $jobPublicId,
            'mode' => $job['mode'],
            'status' => $job['status'],
            'current_step' => $job['current_step'],
            'progress_percent' => (float)($job['progress_percent'] ?? 0),
            'items' => $itemCounts,
            'stats' => $stats,
            'started_at' => $job['started_at'],
            'finished_at' => $job['finished_at'],
            'created_at' => $job['created_at'],
        ];
    }

    public function getJobReportMarkdown(string $jobPublicId): string
    {
        $report = $this->getJobReport($jobPublicId);
        if ($report === []) {
            return 'Job not found.';
        }

        $lines = [];
        $lines[] = '# Notion Migration Report: ' . $jobPublicId;
        $lines[] = '';
        $lines[] = '- **Mode:** ' . ($report['mode'] ?? 'N/A');
        $lines[] = '- **Status:** ' . ($report['status'] ?? 'N/A');
        $lines[] = '- **Progress:** ' . ($report['progress_percent'] ?? 0) . '%';
        $lines[] = '- **Current step:** ' . ($report['current_step'] ?? 'N/A');
        $lines[] = '';
        $lines[] = '## Items';
        $lines[] = '';
        $lines[] = '| Status | Count |';
        $lines[] = '|--------|-------|';
        foreach (($report['items'] ?? []) as $status => $count) {
            $lines[] = "| $status | $count |";
        }
        $lines[] = '';
        $lines[] = '- **Started:** ' . ($report['started_at'] ?? 'N/A');
        $lines[] = '- **Finished:** ' . ($report['finished_at'] ?? 'N/A');

        return implode("\n", $lines);
    }

    // ── User Mappings ──

    public function listUserMappings(int $connectionId): array
    {
        $stmt = $this->pdo->prepare('SELECT id, connection_id, notion_user_id, display_name, email, crm_user_public_id, mapping_status, created_at, updated_at FROM module_notion_user_mappings WHERE connection_id = :conn_id ORDER BY display_name ASC');
        $stmt->execute(['conn_id' => $connectionId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function upsertUserMapping(int $connectionId, string $notionUserId, string $displayName, ?string $email = null): void
    {
        $now = $this->now();
        $stmt = $this->pdo->prepare('INSERT INTO module_notion_user_mappings (connection_id, notion_user_id, display_name, email, mapping_status, created_at, updated_at) VALUES (:conn_id, :nid, :name, :email, :status, :created_at, :updated_at) ON DUPLICATE KEY UPDATE display_name = VALUES(display_name), email = VALUES(email), updated_at = VALUES(updated_at)');
        $stmt->execute([
            'conn_id' => $connectionId,
            'nid' => $notionUserId,
            'name' => $displayName,
            'email' => $email,
            'status' => 'unmapped',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    public function updateUserMapping(int $mappingId, ?string $crmUserPublicId, string $status): void
    {
        $now = $this->now();
        $stmt = $this->pdo->prepare('UPDATE module_notion_user_mappings SET crm_user_public_id = :crm_pub, mapping_status = :status, updated_at = :updated_at WHERE id = :id');
        $stmt->execute([
            'crm_pub' => $crmUserPublicId,
            'status' => $status,
            'updated_at' => $now,
            'id' => $mappingId,
        ]);
    }

    // ── Settings ──

    public function getModuleSettings(): array
    {
        $defaults = [
            'request_timeout_seconds' => 30,
            'max_retries' => 4,
            'default_batch_size' => 100,
            'max_pages_per_job' => 0,
            'max_depth' => 20,
            'include_comments_by_default' => true,
            'publish_by_default' => false,
        ];
        $stmt = $this->pdo->prepare("SELECT setting_key, setting_value FROM module_notion_settings WHERE module_name = 'crm.notion-migration'");
        $stmt->execute();
        $settings = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
            $settings[(string)$row['setting_key']] = json_decode((string)$row['setting_value'], true);
        }
        return array_merge($defaults, $settings);
    }

    public function setModuleSetting(string $key, mixed $value): void
    {
        $now = $this->now();
        $jsonValue = json_encode($value, JSON_UNESCAPED_UNICODE);
        $stmt = $this->pdo->prepare("INSERT INTO module_notion_settings (module_name, setting_key, setting_value, updated_at) VALUES ('crm.notion-migration', :skey, :svalue, :updated_at) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_at = VALUES(updated_at)");
        $stmt->execute([
            'skey' => $key,
            'svalue' => $jsonValue,
            'updated_at' => $now,
        ]);
    }
}
