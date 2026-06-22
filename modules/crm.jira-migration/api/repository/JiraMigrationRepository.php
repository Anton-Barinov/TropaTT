<?php
declare(strict_types=1);

namespace Module\Crm\JiraMigration\Repository;

use PDO;

final class JiraMigrationRepository
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

    public function listConnections(string $actorPublicId): array
    {
        $stmt = $this->pdo->query('SELECT id, public_id, name, site_url, auth_type, email, cloud_id, status, last_checked_at, last_error, created_by_user_id, created_at, updated_at FROM jira_connections ORDER BY created_at DESC');
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function getConnection(string $publicId): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM jira_connections WHERE public_id = :public_id LIMIT 1');
        $stmt->execute(['public_id' => $publicId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return is_array($row) ? $row : null;
    }

    public function createConnection(array $data): array
    {
        $publicId = $this->publicId('jic');
        $now = $this->now();
        $stmt = $this->pdo->prepare('INSERT INTO jira_connections (public_id, name, site_url, auth_type, email, token_encrypted, created_by_user_id, created_at, updated_at) VALUES (:public_id, :name, :site_url, :auth_type, :email, :token_encrypted, :created_by_user_id, :created_at, :updated_at)');
        $stmt->execute([
            'public_id' => $publicId,
            'name' => $data['name'],
            'site_url' => $data['site_url'],
            'auth_type' => $data['auth_type'],
            'email' => $data['email'] ?? null,
            'token_encrypted' => $data['token_encrypted'],
            'created_by_user_id' => $data['created_by_user_id'] ?? null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $result = $this->getConnection($publicId);
        return $result ?? ['public_id' => $publicId];
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
        $this->pdo->prepare('UPDATE jira_connections SET ' . implode(', ', $sets) . ' WHERE public_id = :public_id')->execute($params);
    }

    public function updateConnectionLastCheck(string $publicId, string $status, string $message): void
    {
        $now = $this->now();
        $stmt = $this->pdo->prepare('UPDATE jira_connections SET status = :status, last_error = :message, last_checked_at = :now, updated_at = :now WHERE public_id = :public_id');
        $stmt->execute([
            'status' => $status,
            'message' => mb_substr($message, 0, 500),
            'now' => $now,
            'public_id' => $publicId,
        ]);
    }

    public function deleteConnection(string $publicId): void
    {
        $this->pdo->prepare('DELETE FROM jira_connections WHERE public_id = :public_id')->execute(['public_id' => $publicId]);
    }

    public function findRunningJobsByConnection(int $connectionId): bool
    {
        $stmt = $this->pdo->prepare("SELECT id FROM jira_jobs WHERE connection_id = :conn_id AND status IN ('queued', 'running', 'paused') LIMIT 1");
        $stmt->execute(['conn_id' => $connectionId]);
        return $stmt->fetchColumn() !== false;
    }

    public function getConnectionById(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM jira_connections WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return is_array($row) ? $row : null;
    }

    // ── Jobs ──

    public function listJobs(?string $actorPublicId = null): array
    {
        if ($actorPublicId !== null) {
            $stmt = $this->pdo->prepare('SELECT j.*, c.name AS connection_name FROM jira_jobs j LEFT JOIN jira_connections c ON c.id = j.connection_id WHERE j.created_by_user_id = (SELECT id FROM users WHERE public_id = :pub) ORDER BY j.created_at DESC');
            $stmt->execute(['pub' => $actorPublicId]);
        } else {
            $stmt = $this->pdo->query('SELECT j.*, c.name AS connection_name FROM jira_jobs j LEFT JOIN jira_connections c ON c.id = j.connection_id ORDER BY j.created_at DESC');
        }
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function getJob(string $publicId): ?array
    {
        $stmt = $this->pdo->prepare('SELECT j.*, c.name AS connection_name FROM jira_jobs j LEFT JOIN jira_connections c ON c.id = j.connection_id WHERE j.public_id = :public_id LIMIT 1');
        $stmt->execute(['public_id' => $publicId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!is_array($row)) {
            return null;
        }
        if (isset($row['source_scope_json']) && is_string($row['source_scope_json'])) {
            $row['source_scope'] = json_decode($row['source_scope_json'], true);
        }
        if (isset($row['target_options_json']) && is_string($row['target_options_json'])) {
            $row['target_options'] = json_decode($row['target_options_json'], true);
        }
        if (isset($row['progress_json']) && is_string($row['progress_json'])) {
            $row['progress'] = json_decode($row['progress_json'], true);
        }
        if (isset($row['summary_json']) && is_string($row['summary_json'])) {
            $row['summary'] = json_decode($row['summary_json'], true);
        }
        return $row;
    }

    public function createJob(array $data): array
    {
        $publicId = $this->publicId('jij');
        $now = $this->now();
        $stmt = $this->pdo->prepare('INSERT INTO jira_jobs (public_id, connection_id, status, mode, source_scope_json, target_options_json, created_by_user_id, created_at, updated_at) VALUES (:public_id, :connection_id, :status, :mode, :source_scope_json, :target_options_json, :created_by_user_id, :created_at, :updated_at)');
        $stmt->execute([
            'public_id' => $publicId,
            'connection_id' => $data['connection_id'],
            'status' => 'draft',
            'mode' => $data['mode'],
            'source_scope_json' => json_encode($data['source_scope_json'], JSON_UNESCAPED_UNICODE),
            'target_options_json' => json_encode($data['target_options_json'] ?? [], JSON_UNESCAPED_UNICODE),
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

        $this->pdo->prepare('UPDATE jira_jobs SET ' . implode(', ', $sets) . ' WHERE public_id = :public_id')->execute($params);
    }

    public function updateJobProgress(string $publicId, string $step, float $percent, array $stats): void
    {
        $now = $this->now();
        $stmt = $this->pdo->prepare('UPDATE jira_jobs SET current_step = :step, progress_percent = :percent, progress_json = :stats, updated_at = :updated_at WHERE public_id = :public_id');
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
        $stmt = $this->pdo->prepare("UPDATE jira_job_items SET status = 'pending', error_code = NULL, error_message = NULL, attempts = 0 WHERE job_id = (SELECT id FROM jira_jobs WHERE public_id = :public_id) AND status = 'failed'");
        $stmt->execute(['public_id' => $publicId]);
        return $stmt->rowCount();
    }

    // ── Job Items ──

    public function listJobItems(string $jobPublicId, string $sourceType = '', string $status = '', int $limit = 50, int $page = 1): array
    {
        $where = ['i.job_id = (SELECT id FROM jira_jobs WHERE public_id = :job_pub)'];
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
        $stmt = $this->pdo->prepare('SELECT i.* FROM jira_job_items i WHERE ' . implode(' AND ', $where) . ' ORDER BY i.id ASC LIMIT ' . $limit . ' OFFSET ' . $offset);
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
            $this->pdo->prepare('UPDATE jira_job_items SET ' . implode(', ', $sets) . ' WHERE id = :id')->execute($params);
        } else {
            $stmt = $this->pdo->prepare('INSERT INTO jira_job_items (job_id, source_type, source_id, source_key, source_parent_id, target_type, target_public_id, status, checksum, source_updated_at, error_code, error_message, attempts, payload_json, created_at, updated_at) VALUES (:job_id, :source_type, :source_id, :source_key, :source_parent_id, :target_type, :target_public_id, :status, :checksum, :source_updated_at, :error_code, :error_message, :attempts, :payload_json, :created_at, :updated_at)');
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
        $stmt = $this->pdo->prepare('SELECT * FROM jira_job_items WHERE job_id = :job_id AND source_type = :source_type AND source_id = :source_id LIMIT 1');
        $stmt->execute(['job_id' => $jobId, 'source_type' => $sourceType, 'source_id' => $sourceId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return is_array($row) ? $row : null;
    }

    public function findJobItemsByStatus(int $jobId, string $status, int $limit = 500): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM jira_job_items WHERE job_id = :job_id AND status = :status ORDER BY id ASC LIMIT ' . $limit);
        $stmt->execute(['job_id' => $jobId, 'status' => $status]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function countJobItemsByStatus(int $jobId): array
    {
        $stmt = $this->pdo->prepare("SELECT status, COUNT(*) AS cnt FROM jira_job_items WHERE job_id = :job_id GROUP BY status");
        $stmt->execute(['job_id' => $jobId]);
        $result = ['pending' => 0, 'processing' => 0, 'imported' => 0, 'updated' => 0, 'skipped' => 0, 'failed' => 0, 'unsupported' => 0, 'unresolved' => 0];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
            $result[(string)$row['status']] = (int)$row['cnt'];
        }
        return $result;
    }

    public function findTargetForSource(int $jobId, string $sourceType, string $sourceId): ?string
    {
        $stmt = $this->pdo->prepare("SELECT target_public_id FROM jira_job_items WHERE job_id = :job_id AND source_type = :source_type AND source_id = :source_id AND status IN ('imported', 'updated') LIMIT 1");
        $stmt->execute(['job_id' => $jobId, 'source_type' => $sourceType, 'source_id' => $sourceId]);
        $val = $stmt->fetchColumn();
        return $val !== false ? (string)$val : null;
    }

    // ── Job Logs ──

    public function addJobLog(string $jobPublicId, string $level, ?string $step, string $message, array $context = []): void
    {
        $now = $this->now();
        $stmt = $this->pdo->prepare('INSERT INTO jira_job_logs (job_id, level, step, message, context_json, created_at) VALUES ((SELECT id FROM jira_jobs WHERE public_id = :pub), :level, :step, :message, :context, :created_at)');
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
        $where = ['l.job_id = (SELECT id FROM jira_jobs WHERE public_id = :job_pub)'];
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
        $stmt = $this->pdo->prepare('SELECT l.* FROM jira_job_logs l WHERE ' . implode(' AND ', $where) . ' ORDER BY l.created_at DESC LIMIT ' . $limit . ' OFFSET ' . $offset);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    // ── Report ──

    public function getJobReport(string $jobPublicId): array
    {
        $stmt = $this->pdo->prepare('SELECT j.* FROM jira_jobs j WHERE j.public_id = :pub LIMIT 1');
        $stmt->execute(['pub' => $jobPublicId]);
        $job = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$job) {
            return [];
        }

        $itemCounts = $this->countJobItemsByStatus((int)$job['id']);

        $unresolved = $this->pdo->prepare('SELECT COUNT(*) FROM jira_unresolved_entities WHERE job_id = :job_id');
        $unresolved->execute(['job_id' => (int)$job['id']]);

        $unsupported = $this->pdo->prepare('SELECT COUNT(*) FROM jira_unsupported_fields WHERE job_id = :job_id');
        $unsupported->execute(['job_id' => (int)$job['id']]);

        $progress = json_decode((string)($job['progress_json'] ?? '{}'), true) ?? [];

        return [
            'job_public_id' => $jobPublicId,
            'mode' => $job['mode'],
            'status' => $job['status'],
            'current_step' => $job['current_step'],
            'progress_percent' => (float)($job['progress_percent'] ?? 0),
            'items' => $itemCounts,
            'unresolved_count' => (int)$unresolved->fetchColumn(),
            'unsupported_count' => (int)$unsupported->fetchColumn(),
            'progress' => $progress,
            'started_at' => $job['started_at'],
            'finished_at' => $job['finished_at'],
            'created_at' => $job['created_at'],
        ];
    }

    // ── Mappings ──

    public function listMappings(int $connectionId): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM jira_identity_mappings WHERE connection_id = :conn_id ORDER BY jira_subject_name ASC');
        $stmt->execute(['conn_id' => $connectionId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function upsertMapping(int $connectionId, string $subjectType, string $subjectId, string $subjectName): void
    {
        $now = $this->now();
        $stmt = $this->pdo->prepare('INSERT INTO jira_identity_mappings (connection_id, jira_subject_type, jira_subject_id, jira_subject_name, status, created_at, updated_at) VALUES (:conn_id, :type, :sid, :name, :status, :created_at, :updated_at) ON DUPLICATE KEY UPDATE jira_subject_name = VALUES(jira_subject_name), updated_at = VALUES(updated_at)');
        $stmt->execute([
            'conn_id' => $connectionId,
            'type' => $subjectType,
            'sid' => $subjectId,
            'name' => $subjectName,
            'status' => 'unresolved',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    public function updateMapping(string $publicId, ?string $crmSubjectType, ?string $crmSubjectPublicId, string $status): void
    {
        $now = $this->now();
        $stmt = $this->pdo->prepare('UPDATE jira_identity_mappings SET crm_subject_type = :stype, crm_subject_public_id = :spub, status = :status, updated_at = :updated_at WHERE public_id = :public_id');
        $stmt->execute([
            'stype' => $crmSubjectType,
            'spub' => $crmSubjectPublicId,
            'status' => $status,
            'updated_at' => $now,
            'public_id' => $publicId,
        ]);
    }

    // ── Unresolved Entities ──

    public function addUnresolvedEntity(string $jobPublicId, string $sourceType, string $sourceId, string $reasonCode, string $reasonText, array $payload = []): void
    {
        $now = $this->now();
        $stmt = $this->pdo->prepare('INSERT INTO jira_unresolved_entities (job_id, source_type, source_id, reason_code, reason_text, payload_json, created_at) VALUES ((SELECT id FROM jira_jobs WHERE public_id = :pub), :source_type, :source_id, :reason_code, :reason_text, :payload, :created_at)');
        $stmt->execute([
            'pub' => $jobPublicId,
            'source_type' => $sourceType,
            'source_id' => $sourceId,
            'reason_code' => $reasonCode,
            'reason_text' => $reasonText,
            'payload' => $payload !== [] ? json_encode($payload, JSON_UNESCAPED_UNICODE) : null,
            'created_at' => $now,
        ]);
    }

    public function listUnresolvedEntities(string $jobPublicId): array
    {
        if ($jobPublicId === '') {
            return [];
        }
        $stmt = $this->pdo->prepare('SELECT * FROM jira_unresolved_entities WHERE job_id = (SELECT id FROM jira_jobs WHERE public_id = :pub) ORDER BY created_at DESC');
        $stmt->execute(['pub' => $jobPublicId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    // ── Unsupported Fields ──

    public function addUnsupportedField(string $jobPublicId, ?string $issueId, string $fieldId, ?string $fieldName, string $handling, array $schema = [], ?string $sample = null): void
    {
        $now = $this->now();
        $stmt = $this->pdo->prepare('INSERT INTO jira_unsupported_fields (job_id, issue_id, field_id, field_name, field_schema_json, handling, sample_json, created_at) VALUES ((SELECT id FROM jira_jobs WHERE public_id = :pub), :issue_id, :field_id, :field_name, :schema, :handling, :sample, :created_at)');
        $stmt->execute([
            'pub' => $jobPublicId,
            'issue_id' => $issueId,
            'field_id' => $fieldId,
            'field_name' => $fieldName,
            'schema' => $schema !== [] ? json_encode($schema, JSON_UNESCAPED_UNICODE) : null,
            'handling' => $handling,
            'sample' => $sample,
            'created_at' => $now,
        ]);
    }

    // ── Rate Limits ──

    public function getRateLimit(int $connectionId): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM jira_rate_limits WHERE connection_id = :conn_id LIMIT 1');
        $stmt->execute(['conn_id' => $connectionId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return is_array($row) ? $row : null;
    }

    public function initRateLimit(int $connectionId): void
    {
        $now = gmdate('Y-m-d H:i:s');
        $this->pdo->prepare('INSERT IGNORE INTO jira_rate_limits (connection_id, requests_made, window_started_at, updated_at) VALUES (:conn_id, 0, :now, :now)')
            ->execute(['conn_id' => $connectionId, 'now' => $now]);
    }

    public function updateRateLimitAfterRequest(int $connectionId, bool $reset, ?string $retryAfterUntil = null): void
    {
        $now = gmdate('Y-m-d H:i:s');
        if ($reset) {
            $stmt = $this->pdo->prepare('UPDATE jira_rate_limits SET requests_made = 1, window_started_at = :now, retry_after_until = :retry, updated_at = :now WHERE connection_id = :conn_id');
        } else {
            $stmt = $this->pdo->prepare('UPDATE jira_rate_limits SET requests_made = requests_made + 1, retry_after_until = COALESCE(:retry, retry_after_until), updated_at = :now WHERE connection_id = :conn_id');
        }
        $stmt->execute(['conn_id' => $connectionId, 'now' => $now, 'retry' => $retryAfterUntil]);
    }

    public function resetRateLimit(int $connectionId): void
    {
        $now = gmdate('Y-m-d H:i:s');
        $this->pdo->prepare('UPDATE jira_rate_limits SET requests_made = 0, window_started_at = :now, retry_after_until = NULL, updated_at = :now WHERE connection_id = :conn_id')
            ->execute(['conn_id' => $connectionId, 'now' => $now]);
    }

    // ── Settings ──

    public function getModuleSettings(): array
    {
        $defaults = [
            'max_attachment_size_mb' => 20,
            'allowed_jira_hosts' => ['*.atlassian.net'],
            'custom_domain_allowlist' => [],
            'default_batch_size' => 100,
            'request_timeout_seconds' => 60,
            'max_retries' => 3,
            'jql_default_max_results' => 100,
        ];
        $stmt = $this->pdo->prepare("SELECT setting_key, setting_value FROM jira_settings WHERE module_name = 'crm.jira-migration'");
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
        $stmt = $this->pdo->prepare("INSERT INTO jira_settings (module_name, setting_key, setting_value, updated_at) VALUES ('crm.jira-migration', :skey, :svalue, :updated_at) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_at = VALUES(updated_at)");
        $stmt->execute([
            'skey' => $key,
            'svalue' => $jsonValue,
            'updated_at' => $now,
        ]);
    }

    // ── Rate limit helpers ──

    public function getRateLimitState(int $connectionId): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM jira_rate_limits WHERE connection_id = :conn_id LIMIT 1');
        $stmt->execute(['conn_id' => $connectionId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return is_array($row) ? $row : null;
    }
}
