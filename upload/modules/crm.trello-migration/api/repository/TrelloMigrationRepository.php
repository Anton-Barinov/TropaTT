<?php
declare(strict_types=1);

namespace Module\Crm\TrelloMigration\Repository;

use PDO;

final class TrelloMigrationRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    private function id(string $prefix): string
    {
        return $prefix . '_' . bin2hex(random_bytes(10));
    }

    private function now(): string
    {
        return gmdate('Y-m-d H:i:s');
    }

    private function json(mixed $value): string
    {
        return (string)json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    public function actor(int $userId): array
    {
        $stmt = $this->pdo->prepare('SELECT id, public_id, login, full_name, is_root, is_active FROM users WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $userId]);
        return ($row = $stmt->fetch(PDO::FETCH_ASSOC)) ?: ['id' => $userId, 'is_root' => 0];
    }

    public function listConnections(int $actorId, bool $isManager): array
    {
        $sql = 'SELECT id, public_id, name, status, last_checked_at, last_error, created_by_user_id, created_at, updated_at FROM module_trello_connections';
        $params = [];
        if (!$isManager) {
            $sql .= ' WHERE created_by_user_id = :owner';
            $params['owner'] = $actorId;
        }
        $sql .= ' ORDER BY created_at DESC';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function getConnection(string $publicId): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM module_trello_connections WHERE public_id = :id LIMIT 1');
        $stmt->execute(['id' => $publicId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return is_array($row) ? $row : null;
    }

    public function getConnectionById(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM module_trello_connections WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return is_array($row) ? $row : null;
    }

    public function createConnection(array $data): array
    {
        $publicId = $this->id('trc');
        $now = $this->now();
        $stmt = $this->pdo->prepare('INSERT INTO module_trello_connections (public_id,name,api_key_encrypted,token_encrypted,api_secret_encrypted,status,created_by_user_id,created_at,updated_at) VALUES (:public_id,:name,:api_key,:token,:secret,\'draft\',:owner,:created,:updated)');
        $stmt->execute([
            'public_id' => $publicId,
            'name' => $data['name'],
            'api_key' => $data['api_key_encrypted'],
            'token' => $data['token_encrypted'],
            'secret' => $data['api_secret_encrypted'] ?? null,
            'owner' => $data['created_by_user_id'],
            'created' => $now,
            'updated' => $now,
        ]);
        return $this->getConnection($publicId) ?? ['public_id' => $publicId];
    }

    public function updateConnection(string $publicId, array $data): void
    {
        $allowed = ['name', 'api_key_encrypted', 'token_encrypted', 'api_secret_encrypted'];
        $sets = [];
        $params = ['id' => $publicId, 'updated' => $this->now()];
        foreach ($allowed as $key) {
            if (array_key_exists($key, $data)) {
                $sets[] = $key . ' = :' . $key;
                $params[$key] = $data[$key];
            }
        }
        if ($sets === []) {
            return;
        }
        $sets[] = 'updated_at = :updated';
        $this->pdo->prepare('UPDATE module_trello_connections SET ' . implode(', ', $sets) . ' WHERE public_id = :id')->execute($params);
    }

    public function updateConnectionCheck(string $publicId, bool $ok, string $error = ''): void
    {
        $now = $this->now();
        $stmt = $this->pdo->prepare('UPDATE module_trello_connections SET status=:status,last_checked_at=:checked,last_error=:error,updated_at=:updated WHERE public_id=:id');
        $stmt->execute(['status' => $ok ? 'active' : 'failed', 'checked' => $now, 'error' => $error !== '' ? mb_substr($error, 0, 1000) : null, 'updated' => $now, 'id' => $publicId]);
    }

    public function deleteConnection(int $connectionId): void
    {
        $this->pdo->beginTransaction();
        try {
            foreach (['module_trello_job_logs' => 'job_id IN (SELECT id FROM module_trello_jobs WHERE connection_id=:id)', 'module_trello_job_items' => 'job_id IN (SELECT id FROM module_trello_jobs WHERE connection_id=:id)', 'module_trello_jobs' => 'connection_id=:id', 'module_trello_source_mappings' => 'connection_id=:id', 'module_trello_board_configs' => 'connection_id=:id', 'module_trello_user_mappings' => 'connection_id=:id', 'module_trello_rate_limits' => 'connection_id=:id', 'module_trello_webhooks' => 'connection_id=:id', 'module_trello_sync_states' => 'connection_id=:id'] as $table => $where) {
                $this->pdo->prepare('DELETE FROM ' . $table . ' WHERE ' . $where)->execute(['id' => $connectionId]);
            }
            $this->pdo->prepare('DELETE FROM module_trello_connections WHERE id=:id')->execute(['id' => $connectionId]);
            $this->pdo->commit();
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    public function hasRunningJobs(int $connectionId): bool
    {
        $stmt = $this->pdo->prepare("SELECT id FROM module_trello_jobs WHERE connection_id=:id AND status IN ('queued','running','pausing','cancelling','rolling_back') LIMIT 1");
        $stmt->execute(['id' => $connectionId]);
        return $stmt->fetchColumn() !== false;
    }

    public function listJobs(int $actorId, bool $isManager): array
    {
        $sql = 'SELECT j.id,j.public_id,j.connection_id,j.mode,j.status,j.source_scope_json,j.target_options_json,j.current_step,j.progress_percent,j.progress_json,j.summary_json,j.started_at,j.finished_at,j.created_by_user_id,j.created_at,j.updated_at,c.name AS connection_name FROM module_trello_jobs j JOIN module_trello_connections c ON c.id=j.connection_id';
        $params = [];
        if (!$isManager) {
            $sql .= ' WHERE j.created_by_user_id=:owner';
            $params['owner'] = $actorId;
        }
        $sql .= ' ORDER BY j.created_at DESC';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return array_map([$this, 'decodeJob'], $stmt->fetchAll(PDO::FETCH_ASSOC) ?: []);
    }

    private function decodeJob(array $row): array
    {
        foreach (['source_scope_json' => 'source_scope', 'target_options_json' => 'target_options', 'progress_json' => 'progress', 'summary_json' => 'summary'] as $from => $to) {
            if (isset($row[$from])) {
                $row[$to] = json_decode((string)$row[$from], true) ?: [];
            }
        }
        return $row;
    }

    public function getJob(string $publicId): ?array
    {
        $stmt = $this->pdo->prepare('SELECT j.*,c.name AS connection_name FROM module_trello_jobs j JOIN module_trello_connections c ON c.id=j.connection_id WHERE j.public_id=:id LIMIT 1');
        $stmt->execute(['id' => $publicId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return is_array($row) ? $this->decodeJob($row) : null;
    }

    public function createJob(array $data): array
    {
        $publicId = $this->id('trj');
        $now = $this->now();
        $stmt = $this->pdo->prepare('INSERT INTO module_trello_jobs (public_id,connection_id,mode,status,source_scope_json,target_options_json,created_by_user_id,created_at,updated_at) VALUES (:public_id,:connection_id,:mode,\'draft\',:scope,:options,:owner,:created,:updated)');
        $stmt->execute(['public_id' => $publicId, 'connection_id' => $data['connection_id'], 'mode' => $data['mode'] ?? 'import', 'scope' => $this->json($data['source_scope'] ?? []), 'options' => $this->json($data['options'] ?? []), 'owner' => $data['created_by_user_id'], 'created' => $now, 'updated' => $now]);
        return $this->getJob($publicId) ?? ['public_id' => $publicId];
    }

    public function updateJobStatus(string $publicId, string $status, ?string $leaseToken = null): void
    {
        $now = $this->now();
        $sets = ['status=:status', 'updated_at=:updated'];
        $params = ['id' => $publicId, 'status' => $status, 'updated' => $now];
        if ($status === 'queued' || $status === 'running') {
            $sets[] = 'started_at=COALESCE(started_at,:started)';
            $params['started'] = $now;
        }
        if (in_array($status, ['completed','completed_with_warnings','failed','cancelled','rolled_back'], true)) {
            $sets[] = 'finished_at=:finished';
            $params['finished'] = $now;
        }
        $where = ' WHERE public_id=:id';
        if ($leaseToken !== null) {
            $where .= " AND lease_token=:lease_token AND status='running'";
            $params['lease_token'] = $leaseToken;
        }
        $stmt = $this->pdo->prepare('UPDATE module_trello_jobs SET ' . implode(',', $sets) . $where);
        $stmt->execute($params);
        if ($leaseToken !== null && $stmt->rowCount() !== 1) throw new \RuntimeException('TRELLO_JOB_LEASE_LOST');
    }

    public function updateProgress(string $publicId, string $step, float $percent, array $progress, ?string $leaseToken = null): void
    {
        $params = ['step' => $step, 'percent' => max(0, min(100, $percent)), 'progress' => $this->json($progress), 'updated' => $this->now(), 'id' => $publicId];
        $where = ' WHERE public_id=:id';
        if ($leaseToken !== null) {
            $where .= " AND lease_token=:lease_token AND status='running'";
            $params['lease_token'] = $leaseToken;
        }
        $stmt = $this->pdo->prepare('UPDATE module_trello_jobs SET current_step=:step,progress_percent=:percent,progress_json=:progress,updated_at=:updated' . $where);
        $stmt->execute($params);
        if ($leaseToken !== null && $stmt->rowCount() !== 1) throw new \RuntimeException('TRELLO_JOB_LEASE_LOST');
    }

    public function updateSummary(string $publicId, array $summary, ?string $leaseToken = null): void
    {
        $params = ['summary' => $this->json($summary), 'updated' => $this->now(), 'id' => $publicId];
        $where = ' WHERE public_id=:id';
        if ($leaseToken !== null) {
            $where .= " AND lease_token=:lease_token AND status='running'";
            $params['lease_token'] = $leaseToken;
        }
        $stmt = $this->pdo->prepare('UPDATE module_trello_jobs SET summary_json=:summary,updated_at=:updated' . $where);
        $stmt->execute($params);
        if ($leaseToken !== null && $stmt->rowCount() !== 1) throw new \RuntimeException('TRELLO_JOB_LEASE_LOST');
    }

    public function claimNextJob(int $leaseSeconds = 240): ?array
    {
        $this->pdo->beginTransaction();
        try {
            $stmt = $this->pdo->query("SELECT j.id FROM module_trello_jobs j WHERE (j.status='queued' OR (j.status='running' AND j.lease_until IS NOT NULL AND j.lease_until < UTC_TIMESTAMP())) AND NOT EXISTS (SELECT 1 FROM module_trello_jobs active WHERE active.connection_id=j.connection_id AND active.status='running' AND active.lease_until IS NOT NULL AND active.lease_until >= UTC_TIMESTAMP()) ORDER BY j.created_at ASC LIMIT 1 FOR UPDATE");
            $id = $stmt->fetchColumn();
            if ($id === false) {
                $this->pdo->commit();
                return null;
            }
            $token = bin2hex(random_bytes(16));
            $until = gmdate('Y-m-d H:i:s', time() + $leaseSeconds);
            $this->pdo->prepare("UPDATE module_trello_jobs SET status='running',lease_token=:token,lease_until=:until,started_at=COALESCE(started_at,UTC_TIMESTAMP()),updated_at=UTC_TIMESTAMP() WHERE id=:id")->execute(['token' => $token, 'until' => $until, 'id' => $id]);
            $this->pdo->commit();
            $stmt = $this->pdo->prepare('SELECT j.*,c.name AS connection_name FROM module_trello_jobs j JOIN module_trello_connections c ON c.id=j.connection_id WHERE j.id=:id');
            $stmt->execute(['id' => $id]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return is_array($row) ? $this->decodeJob($row) : null;
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    public function heartbeat(string $publicId, string $leaseToken, int $leaseSeconds = 240): bool
    {
        $until = gmdate('Y-m-d H:i:s', time() + $leaseSeconds);
        return $this->pdo->prepare("UPDATE module_trello_jobs SET lease_until=:until,updated_at=UTC_TIMESTAMP() WHERE public_id=:id AND lease_token=:token AND status='running'")->execute(['until' => $until, 'id' => $publicId, 'token' => $leaseToken]);
    }

    public function releaseLease(string $publicId, string $leaseToken): void
    {
        $this->pdo->prepare('UPDATE module_trello_jobs SET lease_token=NULL,lease_until=NULL,updated_at=UTC_TIMESTAMP() WHERE public_id=:id AND lease_token=:token')->execute(['id' => $publicId, 'token' => $leaseToken]);
    }

    public function ownsLease(string $publicId, string $leaseToken): bool
    {
        $stmt = $this->pdo->prepare("SELECT id FROM module_trello_jobs WHERE public_id=:id AND lease_token=:token AND status='running' AND lease_until IS NOT NULL AND lease_until >= UTC_TIMESTAMP() LIMIT 1");
        $stmt->execute(['id' => $publicId, 'token' => $leaseToken]);
        return $stmt->fetchColumn() !== false;
    }

    public function requestStatus(string $publicId, string $status): void
    {
        $this->pdo->prepare('UPDATE module_trello_jobs SET status=:status,updated_at=UTC_TIMESTAMP() WHERE public_id=:id AND status IN (\'draft\',\'queued\',\'running\',\'paused\',\'pausing\',\'cancelling\')')->execute(['id' => $publicId, 'status' => $status]);
    }

    public function resetFailedItems(string $publicId): int
    {
        $stmt = $this->pdo->prepare("UPDATE module_trello_job_items SET status='pending',error_code=NULL,error_message=NULL,updated_at=UTC_TIMESTAMP() WHERE job_id=(SELECT id FROM module_trello_jobs WHERE public_id=:id) AND status='failed'");
        $stmt->execute(['id' => $publicId]);
        return $stmt->rowCount();
    }

    public function upsertItem(int $jobId, string $type, string $sourceId, array $data): void
    {
        $existing = $this->findItem($jobId, $type, $sourceId);
        $fields = ['source_parent_id','target_type','target_public_id','created_by_job','status','checksum','source_updated_at','payload_json','error_code','error_message','attempts'];
        if ($existing) {
            // A repeated crawl must not reset successfully imported items unless
            // the source checksum changed. This is what makes retries resumable.
            if (($data['status'] ?? null) === 'pending'
                && (string)($existing['checksum'] ?? '') === (string)($data['checksum'] ?? '')
                && in_array((string)($existing['status'] ?? ''), ['imported', 'updated', 'skipped'], true)
            ) {
                unset($data['status']);
            }
            $sets = ['updated_at=:updated'];
            $params = ['id' => $existing['id'], 'updated' => $this->now()];
            foreach ($fields as $field) {
                if (array_key_exists($field, $data)) {
                    $sets[] = $field . '=:' . $field;
                    $params[$field] = $field === 'payload_json' && is_array($data[$field]) ? $this->json($data[$field]) : $data[$field];
                }
            }
            $this->pdo->prepare('UPDATE module_trello_job_items SET ' . implode(',', $sets) . ' WHERE id=:id')->execute($params);
            return;
        }
        $stmt = $this->pdo->prepare('INSERT INTO module_trello_job_items (job_id,source_type,source_id,source_parent_id,target_type,target_public_id,created_by_job,status,checksum,source_updated_at,payload_json,error_code,error_message,attempts,created_at,updated_at) VALUES (:job,:type,:source,:parent,:target_type,:target,:created_by_job,:status,:checksum,:source_updated,:payload,:error_code,:error_message,:attempts,:created,:updated)');
        $stmt->execute(['job' => $jobId, 'type' => $type, 'source' => $sourceId, 'parent' => $data['source_parent_id'] ?? null, 'target_type' => $data['target_type'] ?? null, 'target' => $data['target_public_id'] ?? null, 'created_by_job' => !empty($data['created_by_job']) ? 1 : 0, 'status' => $data['status'] ?? 'pending', 'checksum' => $data['checksum'] ?? null, 'source_updated' => $data['source_updated_at'] ?? null, 'payload' => isset($data['payload_json']) ? (is_array($data['payload_json']) ? $this->json($data['payload_json']) : $data['payload_json']) : null, 'error_code' => $data['error_code'] ?? null, 'error_message' => $data['error_message'] ?? null, 'attempts' => $data['attempts'] ?? 0, 'created' => $this->now(), 'updated' => $this->now()]);
    }

    public function findItem(int $jobId, string $type, string $sourceId): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM module_trello_job_items WHERE job_id=:job AND source_type=:type AND source_id=:source LIMIT 1');
        $stmt->execute(['job' => $jobId, 'type' => $type, 'source' => $sourceId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return is_array($row) ? $row : null;
    }

    public function items(int $jobId, ?string $status = null, int $limit = 500): array
    {
        $sql = 'SELECT * FROM module_trello_job_items WHERE job_id=:job';
        $params = ['job' => $jobId];
        if ($status !== null && $status !== '') {
            $sql .= ' AND status=:status';
            $params['status'] = $status;
        }
        $sql .= ' ORDER BY id ASC LIMIT ' . max(1, min(5000, $limit));
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function itemCounts(int $jobId): array
    {
        $stmt = $this->pdo->prepare('SELECT status,COUNT(*) AS count FROM module_trello_job_items WHERE job_id=:job GROUP BY status');
        $stmt->execute(['job' => $jobId]);
        $result = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
            $result[(string)$row['status']] = (int)$row['count'];
        }
        return $result;
    }

    public function findMapping(int $connectionId, string $type, string $sourceId): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM module_trello_source_mappings WHERE connection_id=:connection AND source_type=:type AND source_id=:source LIMIT 1');
        $stmt->execute(['connection' => $connectionId, 'type' => $type, 'source' => $sourceId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return is_array($row) ? $row : null;
    }

    public function upsertMapping(int $connectionId, string $type, string $sourceId, array $data): array
    {
        $existing = $this->findMapping($connectionId, $type, $sourceId);
        $now = $this->now();
        if ($existing) {
            $stmt = $this->pdo->prepare('UPDATE module_trello_source_mappings SET source_parent_id=:parent,target_type=:target_type,target_public_id=:target,source_checksum=:source_checksum,target_checksum=:target_checksum,source_updated_at=:source_updated,state=:state,created_by_job_id=:job,last_seen_at=:seen,updated_at=:updated WHERE id=:id');
            $stmt->execute(['parent' => $data['source_parent_id'] ?? $existing['source_parent_id'], 'target_type' => $data['target_type'] ?? $existing['target_type'], 'target' => $data['target_public_id'] ?? $existing['target_public_id'], 'source_checksum' => $data['source_checksum'] ?? $existing['source_checksum'], 'target_checksum' => $data['target_checksum'] ?? $existing['target_checksum'], 'source_updated' => $data['source_updated_at'] ?? $existing['source_updated_at'], 'state' => $data['state'] ?? 'active', 'job' => $data['created_by_job_id'] ?? $existing['created_by_job_id'], 'seen' => $now, 'updated' => $now, 'id' => $existing['id']]);
            return $this->findMapping($connectionId, $type, $sourceId) ?? $existing;
        }
        $publicId = $this->id('trm');
        $stmt = $this->pdo->prepare('INSERT INTO module_trello_source_mappings (public_id,connection_id,source_type,source_id,source_parent_id,target_type,target_public_id,source_checksum,target_checksum,source_updated_at,state,created_by_job_id,last_seen_at,created_at,updated_at) VALUES (:public_id,:connection,:type,:source,:parent,:target_type,:target,:source_checksum,:target_checksum,:source_updated,:state,:job,:seen,:created,:updated)');
        $stmt->execute(['public_id' => $publicId, 'connection' => $connectionId, 'type' => $type, 'source' => $sourceId, 'parent' => $data['source_parent_id'] ?? null, 'target_type' => $data['target_type'] ?? null, 'target' => $data['target_public_id'] ?? null, 'source_checksum' => $data['source_checksum'] ?? null, 'target_checksum' => $data['target_checksum'] ?? null, 'source_updated' => $data['source_updated_at'] ?? null, 'state' => $data['state'] ?? 'active', 'job' => $data['created_by_job_id'] ?? null, 'seen' => $now, 'created' => $now, 'updated' => $now]);
        return $this->findMapping($connectionId, $type, $sourceId) ?? ['public_id' => $publicId];
    }

    public function listMappings(int $connectionId): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM module_trello_source_mappings WHERE connection_id=:id ORDER BY updated_at DESC');
        $stmt->execute(['id' => $connectionId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function listBoardConfigs(int $connectionId): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM module_trello_board_configs WHERE connection_id=:id ORDER BY board_name ASC');
        $stmt->execute(['id' => $connectionId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        foreach ($rows as &$row) {
            $row['list_mapping'] = json_decode((string)$row['list_mapping_json'], true) ?: [];
            $row['options'] = json_decode((string)$row['options_json'], true) ?: [];
        }
        return $rows;
    }

    public function boardConfig(int $connectionId, string $boardId): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM module_trello_board_configs WHERE connection_id=:connection AND board_id=:board LIMIT 1');
        $stmt->execute(['connection' => $connectionId, 'board' => $boardId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!is_array($row)) return null;
        $row['list_mapping'] = json_decode((string)$row['list_mapping_json'], true) ?: [];
        $row['options'] = json_decode((string)$row['options_json'], true) ?: [];
        return $row;
    }

    public function saveBoardConfig(int $connectionId, string $boardId, array $data): array
    {
        $now = $this->now();
        $stmt = $this->pdo->prepare('INSERT INTO module_trello_board_configs (connection_id,board_id,board_name,target_project_public_id,list_mapping_json,options_json,created_at,updated_at) VALUES (:connection,:board,:name,:project,:mapping,:options,:created,:updated) ON DUPLICATE KEY UPDATE board_name=VALUES(board_name),target_project_public_id=VALUES(target_project_public_id),list_mapping_json=VALUES(list_mapping_json),options_json=VALUES(options_json),updated_at=VALUES(updated_at)');
        $stmt->execute(['connection' => $connectionId, 'board' => $boardId, 'name' => $data['board_name'] ?? null, 'project' => $data['target_project_public_id'] ?? null, 'mapping' => $this->json($data['list_mapping'] ?? []), 'options' => $this->json($data['options'] ?? []), 'created' => $now, 'updated' => $now]);
        return $this->boardConfig($connectionId, $boardId) ?? [];
    }

    public function upsertUserMapping(int $connectionId, array $member): void
    {
        $now = $this->now();
        $stmt = $this->pdo->prepare('INSERT INTO module_trello_user_mappings (connection_id,trello_member_id,display_name,username,mapping_status,created_at,updated_at) VALUES (:connection,:member,:name,:username,\'unmapped\',:created,:updated) ON DUPLICATE KEY UPDATE display_name=VALUES(display_name),username=VALUES(username),updated_at=VALUES(updated_at)');
        $stmt->execute(['connection' => $connectionId, 'member' => (string)$member['id'], 'name' => $member['fullName'] ?? null, 'username' => $member['username'] ?? null, 'created' => $now, 'updated' => $now]);
    }

    public function listUserMappings(int $connectionId): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM module_trello_user_mappings WHERE connection_id=:id ORDER BY display_name ASC');
        $stmt->execute(['id' => $connectionId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function mappedUserId(int $connectionId, string $trelloMemberId): ?int
    {
        $stmt = $this->pdo->prepare('SELECT u.id FROM module_trello_user_mappings m JOIN users u ON u.public_id=m.crm_user_public_id WHERE m.connection_id=:connection AND m.trello_member_id=:member AND m.mapping_status=\'mapped\' AND u.is_active=1 LIMIT 1');
        $stmt->execute(['connection' => $connectionId, 'member' => $trelloMemberId]);
        $id = $stmt->fetchColumn();
        return $id === false ? null : (int)$id;
    }

    public function updateUserMapping(int $connectionId, int $mappingId, ?string $crmUserPublicId): bool
    {
        $status = $crmUserPublicId !== null && $crmUserPublicId !== '' ? 'mapped' : 'unmapped';
        return $this->pdo->prepare("UPDATE module_trello_user_mappings SET crm_user_public_id=:crm,mapping_status=:mapping_status,updated_at=UTC_TIMESTAMP() WHERE id=:id AND connection_id=:connection")->execute(['crm' => $crmUserPublicId ?: null, 'mapping_status' => $status, 'id' => $mappingId, 'connection' => $connectionId]);
    }

    public function activeUserPublicId(string $publicId): ?string
    {
        $stmt = $this->pdo->prepare('SELECT public_id FROM users WHERE public_id=:public_id AND is_active=1 LIMIT 1');
        $stmt->execute(['public_id' => $publicId]);
        $value = $stmt->fetchColumn();
        return $value === false ? null : (string)$value;
    }

    public function webhookForModel(int $connectionId, string $modelId): ?array
    {
        $stmt = $this->pdo->prepare('SELECT w.*, c.api_secret_encrypted FROM module_trello_webhooks w JOIN module_trello_connections c ON c.id=w.connection_id WHERE w.connection_id=:connection AND w.model_id=:model AND w.active=1 LIMIT 1');
        $stmt->execute(['connection' => $connectionId, 'model' => $modelId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return is_array($row) ? $row : null;
    }

    public function createWebhook(int $connectionId, array $data): array
    {
        $publicId = (string)($data['public_id'] ?? '');
        if ($publicId === '' || !str_starts_with($publicId, 'trw_')) {
            $publicId = $this->id('trw');
        }
        $now = $this->now();
        $stmt = $this->pdo->prepare('INSERT INTO module_trello_webhooks (public_id,connection_id,trello_webhook_id,model_id,callback_url,active,created_at,updated_at) VALUES (:public_id,:connection,:trello_id,:model,:callback,1,:created,:updated)');
        $stmt->execute(['public_id' => $publicId, 'connection' => $connectionId, 'trello_id' => $data['trello_webhook_id'] ?? null, 'model' => $data['model_id'], 'callback' => $data['callback_url'], 'created' => $now, 'updated' => $now]);
        return $this->webhook($publicId) ?? ['public_id' => $publicId];
    }

    public function deleteWebhook(string $publicId): bool
    {
        return $this->pdo->prepare('DELETE FROM module_trello_webhooks WHERE public_id=:id')->execute(['id' => $publicId]);
    }

    public function addLog(int $jobId, string $level, string $step, string $message, array $context = []): void
    {
        $stmt = $this->pdo->prepare('INSERT INTO module_trello_job_logs (job_id,level,step,message,context_json,created_at) VALUES (:job,:level,:step,:message,:context,:created)');
        $stmt->execute(['job' => $jobId, 'level' => $level, 'step' => $step, 'message' => mb_substr($message, 0, 2000), 'context' => $context === [] ? null : $this->json($context), 'created' => $this->now()]);
    }

    public function logs(int $jobId, int $limit = 100): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM module_trello_job_logs WHERE job_id=:job ORDER BY id DESC LIMIT ' . max(1, min(1000, $limit)));
        $stmt->execute(['job' => $jobId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function report(string $publicId): array
    {
        $job = $this->getJob($publicId);
        if (!$job) return [];
        $job['items'] = $this->itemCounts((int)$job['id']);
        return $job;
    }

    public function webhook(string $publicId): ?array
    {
        $stmt = $this->pdo->prepare('SELECT w.*, c.api_secret_encrypted FROM module_trello_webhooks w JOIN module_trello_connections c ON c.id=w.connection_id WHERE w.public_id=:id LIMIT 1');
        $stmt->execute(['id' => $publicId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return is_array($row) ? $row : null;
    }

    public function markWebhookEvent(string $publicId, string $eventId): bool
    {
        $stmt = $this->pdo->prepare("UPDATE module_trello_webhooks SET last_event_id=:event,last_received_at=UTC_TIMESTAMP(),updated_at=UTC_TIMESTAMP() WHERE public_id=:id AND (last_event_id IS NULL OR last_event_id <> :event_check)");
        $stmt->execute(['event' => $eventId, 'event_check' => $eventId, 'id' => $publicId]);
        return $stmt->rowCount() === 1;
    }

    public function rateState(int $connectionId): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM module_trello_rate_limits WHERE connection_id=:id LIMIT 1');
        $stmt->execute(['id' => $connectionId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return is_array($row) ? $row : null;
    }

    public function updateRateState(int $connectionId, array $data): void
    {
        $now = $this->now();
        $stmt = $this->pdo->prepare('INSERT INTO module_trello_rate_limits (connection_id,requests_made,window_started_at,token_remaining,key_remaining,retry_after_until,updated_at) VALUES (:id,:requests,:window,:token,:key,:retry,:updated) ON DUPLICATE KEY UPDATE requests_made=VALUES(requests_made),window_started_at=VALUES(window_started_at),token_remaining=VALUES(token_remaining),key_remaining=VALUES(key_remaining),retry_after_until=VALUES(retry_after_until),updated_at=VALUES(updated_at)');
        $stmt->execute(['id' => $connectionId, 'requests' => $data['requests_made'] ?? 0, 'window' => $data['window_started_at'] ?? $now, 'token' => $data['token_remaining'] ?? null, 'key' => $data['key_remaining'] ?? null, 'retry' => $data['retry_after_until'] ?? null, 'updated' => $now]);
    }
}
