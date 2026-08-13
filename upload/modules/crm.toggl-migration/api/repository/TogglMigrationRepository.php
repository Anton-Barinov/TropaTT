<?php
declare(strict_types=1);

namespace Module\Crm\TogglMigration\Repository;

use PDO;

final class TogglMigrationRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    private function id(string $prefix): string { return $prefix . '_' . bin2hex(random_bytes(10)); }
    private function now(): string { return gmdate('Y-m-d H:i:s'); }
    private function json(mixed $value): string { return (string)json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); }

    public function actor(int $id): array
    {
        $stmt = $this->pdo->prepare('SELECT id, public_id, login, full_name, is_root, is_active FROM users WHERE id=:id LIMIT 1');
        $stmt->execute(['id' => $id]);
        return ($row = $stmt->fetch(PDO::FETCH_ASSOC)) ?: ['id' => $id, 'is_root' => 0];
    }

    public function listConnections(int $actorId, bool $manager): array
    {
        $sql = 'SELECT id,public_id,name,auth_type,workspace_gid,status,last_checked_at,last_error,created_by_user_id,created_at,updated_at FROM module_toggl_connections';
        $params = [];
        if (!$manager) { $sql .= ' WHERE created_by_user_id=:owner'; $params['owner'] = $actorId; }
        $sql .= ' ORDER BY created_at DESC';
        $stmt = $this->pdo->prepare($sql); $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function getConnection(string $publicId): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM module_toggl_connections WHERE public_id=:id LIMIT 1');
        $stmt->execute(['id' => $publicId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC); return is_array($row) ? $row : null;
    }

    public function getConnectionById(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM module_toggl_connections WHERE id=:id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC); return is_array($row) ? $row : null;
    }

    public function createConnection(array $data): array
    {
        $publicId = $this->id('tgc'); $now = $this->now();
        $stmt = $this->pdo->prepare('INSERT INTO module_toggl_connections (public_id,name,auth_type,access_token_encrypted,refresh_token_encrypted,client_id_encrypted,client_secret_encrypted,status,created_by_user_id,created_at,updated_at) VALUES (:public_id,:name,:auth,:access,:refresh,:client,:secret,\'draft\',:owner,:created,:updated)');
        $stmt->execute(['public_id' => $publicId, 'name' => $data['name'], 'auth' => $data['auth_type'] ?? 'pat', 'access' => $data['access_token_encrypted'] ?? null, 'refresh' => $data['refresh_token_encrypted'] ?? null, 'client' => $data['client_id_encrypted'] ?? null, 'secret' => $data['client_secret_encrypted'] ?? null, 'owner' => $data['created_by_user_id'], 'created' => $now, 'updated' => $now]);
        return $this->getConnection($publicId) ?? ['public_id' => $publicId];
    }

    public function updateConnection(string $publicId, array $data): void
    {
        $allowed = ['name','access_token_encrypted','refresh_token_encrypted','client_id_encrypted','client_secret_encrypted','workspace_gid'];
        $sets = []; $params = ['id' => $publicId, 'updated' => $this->now()];
        foreach ($allowed as $field) if (array_key_exists($field, $data)) { $sets[] = $field . '=:' . $field; $params[$field] = $data[$field]; }
        if ($sets === []) return;
        $sets[] = 'updated_at=:updated';
        $this->pdo->prepare('UPDATE module_toggl_connections SET ' . implode(',', $sets) . ' WHERE public_id=:id')->execute($params);
    }

    public function updateConnectionCheck(string $publicId, bool $ok, string $error = '', ?string $workspaceGid = null): void
    {
        $sets = ['status=:status','last_checked_at=:checked','last_error=:error','updated_at=:updated'];
        $params = ['status' => $ok ? 'active' : 'failed', 'checked' => $this->now(), 'error' => $error !== '' ? mb_substr($error, 0, 1000) : null, 'updated' => $this->now(), 'id' => $publicId];
        if ($workspaceGid !== null) { $sets[] = 'workspace_gid=:workspace'; $params['workspace'] = $workspaceGid; }
        $this->pdo->prepare('UPDATE module_toggl_connections SET ' . implode(',', $sets) . ' WHERE public_id=:id')->execute($params);
    }

    public function deleteConnection(int $connectionId): void
    {
        $this->pdo->beginTransaction();
        try {
            foreach (['module_toggl_unresolved_entities' => 'job_id IN (SELECT id FROM module_toggl_jobs WHERE connection_id=:id)', 'module_toggl_job_logs' => 'job_id IN (SELECT id FROM module_toggl_jobs WHERE connection_id=:id)', 'module_toggl_job_items' => 'job_id IN (SELECT id FROM module_toggl_jobs WHERE connection_id=:id)', 'module_toggl_jobs' => 'connection_id=:id', 'module_toggl_source_mappings' => 'connection_id=:id', 'module_toggl_user_mappings' => 'connection_id=:id', 'module_toggl_rate_limits' => 'connection_id=:id'] as $table => $where) $this->pdo->prepare('DELETE FROM ' . $table . ' WHERE ' . $where)->execute(['id' => $connectionId]);
            $this->pdo->prepare('DELETE FROM module_toggl_connections WHERE id=:id')->execute(['id' => $connectionId]);
            $this->pdo->commit();
        } catch (\Throwable $e) { if ($this->pdo->inTransaction()) $this->pdo->rollBack(); throw $e; }
    }

    public function hasRunningJobs(int $connectionId): bool
    {
        $stmt = $this->pdo->prepare("SELECT id FROM module_toggl_jobs WHERE connection_id=:id AND status IN ('queued','running','pausing','cancelling','rolling_back') LIMIT 1"); $stmt->execute(['id' => $connectionId]); return $stmt->fetchColumn() !== false;
    }

    public function rateState(int $connectionId): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM module_toggl_rate_limits WHERE connection_id=:id LIMIT 1'); $stmt->execute(['id' => $connectionId]); $row = $stmt->fetch(PDO::FETCH_ASSOC); return is_array($row) ? $row : null;
    }

    public function recordRequest(int $connectionId, int $status, array $headers): void
    {
        $state = $this->rateState($connectionId) ?: [];
        $started = (string)($state['window_started_at'] ?? '');
        if ($started === '' || time() - (strtotime($started) ?: time()) >= 60) { $started = $this->now(); $count = 0; } else $count = (int)($state['requests_made'] ?? 0);
        $this->upsertRateState($connectionId, ['requests_made' => $count + 1, 'window_started_at' => $started, 'retry_after_until' => $state['retry_after_until'] ?? null, 'last_http_status' => $status, 'last_request_at' => $this->now()]);
    }

    public function recordRetryAfter(int $connectionId, int $seconds): void
    {
        $state = $this->rateState($connectionId) ?: [];
        $this->upsertRateState($connectionId, array_merge($state, ['retry_after_until' => gmdate('Y-m-d H:i:s', time() + max(1, $seconds))]));
    }

    private function upsertRateState(int $connectionId, array $data): void
    {
        $stmt = $this->pdo->prepare('INSERT INTO module_toggl_rate_limits (connection_id,requests_made,window_started_at,retry_after_until,last_http_status,last_request_at,updated_at) VALUES (:id,:requests,:window,:retry,:status,:last_request,:updated) ON DUPLICATE KEY UPDATE requests_made=VALUES(requests_made),window_started_at=VALUES(window_started_at),retry_after_until=VALUES(retry_after_until),last_http_status=VALUES(last_http_status),last_request_at=VALUES(last_request_at),updated_at=VALUES(updated_at)');
        $stmt->execute(['id' => $connectionId, 'requests' => (int)($data['requests_made'] ?? 0), 'window' => $data['window_started_at'] ?? $this->now(), 'retry' => $data['retry_after_until'] ?? null, 'status' => $data['last_http_status'] ?? null, 'last_request' => $data['last_request_at'] ?? null, 'updated' => $this->now()]);
    }

    public function createJob(array $data): array
    {
        $publicId = $this->id('tgj'); $now = $this->now();
        $stmt = $this->pdo->prepare('INSERT INTO module_toggl_jobs (public_id,connection_id,workspace_gid,mode,status,source_scope_json,target_options_json,created_by_user_id,created_at,updated_at) VALUES (:public_id,:connection,:workspace,:mode,\'draft\',:scope,:options,:owner,:created,:updated)');
        $stmt->execute(['public_id' => $publicId, 'connection' => $data['connection_id'], 'workspace' => $data['workspace_gid'], 'mode' => $data['mode'] ?? 'import', 'scope' => $this->json($data['source_scope'] ?? []), 'options' => $this->json($data['target_options'] ?? []), 'owner' => $data['created_by_user_id'], 'created' => $now, 'updated' => $now]);
        return $this->getJob($publicId) ?? ['public_id' => $publicId];
    }

    private function decodeJob(array $row): array
    {
        foreach (['source_scope_json' => 'source_scope','target_options_json' => 'target_options','progress_json' => 'progress','summary_json' => 'summary'] as $from => $to) if (array_key_exists($from, $row)) $row[$to] = json_decode((string)$row[$from], true) ?: [];
        return $row;
    }

    public function getJob(string $publicId): ?array
    {
        $stmt = $this->pdo->prepare('SELECT j.*,c.name AS connection_name FROM module_toggl_jobs j JOIN module_toggl_connections c ON c.id=j.connection_id WHERE j.public_id=:id LIMIT 1'); $stmt->execute(['id' => $publicId]); $row = $stmt->fetch(PDO::FETCH_ASSOC); return is_array($row) ? $this->decodeJob($row) : null;
    }

    public function listJobs(int $actorId, bool $manager): array
    {
        $sql = 'SELECT j.*,c.name AS connection_name FROM module_toggl_jobs j JOIN module_toggl_connections c ON c.id=j.connection_id'; $params = [];
        if (!$manager) { $sql .= ' WHERE j.created_by_user_id=:owner'; $params['owner'] = $actorId; }
        $sql .= ' ORDER BY j.created_at DESC'; $stmt = $this->pdo->prepare($sql); $stmt->execute($params); return array_map([$this, 'decodeJob'], $stmt->fetchAll(PDO::FETCH_ASSOC) ?: []);
    }

    public function updateJobStatus(string $publicId, string $status, ?string $leaseToken = null): void
    {
        $sets = ['status=:status','updated_at=:updated']; $params = ['id' => $publicId, 'status' => $status, 'updated' => $this->now()];
        if (in_array($status, ['queued','running'], true)) { $sets[] = 'started_at=COALESCE(started_at,:started)'; $params['started'] = $this->now(); }
        if (in_array($status, ['completed','completed_with_warnings','failed','cancelled','rolled_back','rolled_back_with_warnings','rollback_failed'], true)) { $sets[] = 'finished_at=:finished'; $params['finished'] = $this->now(); }
        $where = ' WHERE public_id=:id';
        if ($leaseToken !== null) { $where .= ' AND lease_token=:lease AND lease_until >= UTC_TIMESTAMP() AND status IN (\'running\',\'rolling_back\')'; $params['lease'] = $leaseToken; }
        $stmt = $this->pdo->prepare('UPDATE module_toggl_jobs SET ' . implode(',', $sets) . $where); $stmt->execute($params);
        if ($leaseToken !== null && !$this->leaseTokenMatches($publicId, $leaseToken)) throw new \RuntimeException('TOGGL_JOB_LEASE_LOST');
    }

    public function requestStatus(string $publicId, string $status): bool
    {
        $stmt = $this->pdo->prepare("UPDATE module_toggl_jobs SET status=:status,updated_at=UTC_TIMESTAMP() WHERE public_id=:id AND status IN ('draft','queued','running','paused','pausing','cancelling','failed','cancelled','completed','completed_with_warnings')");
        $stmt->execute(['id' => $publicId, 'status' => $status]);
        return $stmt->rowCount() === 1;
    }

    /** Atomically reserves a terminal job for rollback and gives the caller a lease. */
    public function beginRollback(string $publicId, int $leaseSeconds = 900): ?array
    {
        $this->pdo->beginTransaction();
        try {
            $stmt = $this->pdo->prepare('SELECT id,status,lease_until,last_source_cursor FROM module_toggl_jobs WHERE public_id=:id LIMIT 1 FOR UPDATE');
            $stmt->execute(['id' => $publicId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!is_array($row)) { $this->pdo->commit(); return null; }
            $status=(string)$row['status'];
            $terminal=in_array($status,['completed','completed_with_warnings','failed','cancelled','rolled_back_with_warnings','rollback_failed'],true);
            $leaseUntil = (string)($row['lease_until'] ?? '');
            $expiredRollback = false;
            if ($status === 'rolling_back' && $leaseUntil !== '') {
                try { $expiredRollback = new \DateTimeImmutable($leaseUntil, new \DateTimeZone('UTC')) < new \DateTimeImmutable('now', new \DateTimeZone('UTC')); } catch (\Throwable) { $expiredRollback = true; }
            }
            if (!$terminal && !$expiredRollback) { $this->pdo->commit(); return null; }
            $token=bin2hex(random_bytes(16));$until=gmdate('Y-m-d H:i:s',time()+max(60,$leaseSeconds));
            $cursor=$expiredRollback?(string)($row['last_source_cursor']??''):json_encode(['phase'=>'rollback','before_id'=>PHP_INT_MAX],JSON_UNESCAPED_UNICODE);
            $update=$this->pdo->prepare("UPDATE module_toggl_jobs SET status='rolling_back',lease_token=:token,lease_until=:until,last_source_cursor=:cursor,updated_at=UTC_TIMESTAMP() WHERE id=:id AND status=:expected");
            $update->execute(['token'=>$token,'until'=>$until,'cursor'=>$cursor,'id'=>(int)$row['id'],'expected'=>$status]);
            if ($update->rowCount() !== 1) { $this->pdo->rollBack(); return null; }
            $this->pdo->commit();
            return $this->getJob($publicId);
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            throw $e;
        }
    }

    public function updateProgress(string $publicId, string $step, float $percent, array $progress, ?string $leaseToken = null): void
    {
        $params = ['step' => $step, 'percent' => max(0, min(100, $percent)), 'progress' => $this->json($progress), 'updated' => $this->now(), 'id' => $publicId]; $where = ' WHERE public_id=:id';
        if ($leaseToken !== null) { $where .= ' AND lease_token=:lease AND lease_until >= UTC_TIMESTAMP() AND status IN (\'running\',\'rolling_back\')'; $params['lease'] = $leaseToken; }
        $stmt = $this->pdo->prepare('UPDATE module_toggl_jobs SET current_step=:step,progress_percent=:percent,progress_json=:progress,updated_at=:updated' . $where); $stmt->execute($params);
        if ($leaseToken !== null && !$this->ownsLease($publicId, $leaseToken)) throw new \RuntimeException('TOGGL_JOB_LEASE_LOST');
    }

    public function updateSummary(string $publicId, array $summary, ?string $leaseToken = null): void
    {
        $params = ['summary' => $this->json($summary), 'updated' => $this->now(), 'id' => $publicId]; $where = ' WHERE public_id=:id';
        if ($leaseToken !== null) { $where .= ' AND lease_token=:lease AND lease_until >= UTC_TIMESTAMP() AND status IN (\'running\',\'rolling_back\')'; $params['lease'] = $leaseToken; }
        $stmt = $this->pdo->prepare('UPDATE module_toggl_jobs SET summary_json=:summary,updated_at=:updated' . $where); $stmt->execute($params);
        if ($leaseToken !== null && !$this->ownsLease($publicId, $leaseToken)) throw new \RuntimeException('TOGGL_JOB_LEASE_LOST');
    }

    public function claimNextJob(int $leaseSeconds = 240): ?array
    {
        $this->pdo->beginTransaction();
        try {
            $stmt = $this->pdo->query("SELECT j.id FROM module_toggl_jobs j WHERE (j.status='queued' OR (j.status='running' AND j.lease_until IS NOT NULL AND j.lease_until < UTC_TIMESTAMP())) AND NOT EXISTS (SELECT 1 FROM module_toggl_jobs active WHERE active.connection_id=j.connection_id AND (active.status='rolling_back' OR (active.status='running' AND active.lease_until IS NOT NULL AND active.lease_until >= UTC_TIMESTAMP()))) ORDER BY j.created_at ASC LIMIT 1 FOR UPDATE");
            $id = $stmt->fetchColumn();
            if ($id === false) { $this->pdo->commit(); return null; }
            $token = bin2hex(random_bytes(16)); $until = gmdate('Y-m-d H:i:s', time() + $leaseSeconds);
            $this->pdo->prepare("UPDATE module_toggl_jobs SET status='running',lease_token=:token,lease_until=:until,started_at=COALESCE(started_at,UTC_TIMESTAMP()),updated_at=UTC_TIMESTAMP() WHERE id=:id")->execute(['token' => $token, 'until' => $until, 'id' => $id]);
            $this->pdo->commit();
            $stmt = $this->pdo->prepare('SELECT j.*,c.name AS connection_name FROM module_toggl_jobs j JOIN module_toggl_connections c ON c.id=j.connection_id WHERE j.id=:id'); $stmt->execute(['id' => $id]); $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return is_array($row) ? $this->decodeJob($row) : null;
        } catch (\Throwable $e) { if ($this->pdo->inTransaction()) $this->pdo->rollBack(); throw $e; }
    }

    public function heartbeat(string $publicId, string $leaseToken, int $leaseSeconds = 240): bool
    {
        $stmt = $this->pdo->prepare("UPDATE module_toggl_jobs SET lease_until=:until,updated_at=UTC_TIMESTAMP() WHERE public_id=:id AND lease_token=:token AND lease_until >= UTC_TIMESTAMP() AND status IN ('running','rolling_back')"); $stmt->execute(['until' => gmdate('Y-m-d H:i:s', time() + $leaseSeconds), 'id' => $publicId, 'token' => $leaseToken]); return $this->ownsLease($publicId, $leaseToken);
    }

    public function ownsLease(string $publicId, string $leaseToken): bool
    {
        $stmt = $this->pdo->prepare("SELECT id FROM module_toggl_jobs WHERE public_id=:id AND lease_token=:token AND status IN ('running','rolling_back') AND lease_until >= UTC_TIMESTAMP() LIMIT 1"); $stmt->execute(['id' => $publicId, 'token' => $leaseToken]); return $stmt->fetchColumn() !== false;
    }

    private function leaseTokenMatches(string $publicId, string $leaseToken): bool
    {
        $stmt = $this->pdo->prepare('SELECT id FROM module_toggl_jobs WHERE public_id=:id AND lease_token=:token AND lease_until >= UTC_TIMESTAMP() LIMIT 1');
        $stmt->execute(['id' => $publicId, 'token' => $leaseToken]);
        return $stmt->fetchColumn() !== false;
    }

    public function releaseLease(string $publicId, string $leaseToken): void
    { $this->pdo->prepare('UPDATE module_toggl_jobs SET lease_token=NULL,lease_until=NULL,updated_at=UTC_TIMESTAMP() WHERE public_id=:id AND lease_token=:token')->execute(['id' => $publicId, 'token' => $leaseToken]); }

    /** Atomically queues failed items and resets the import checkpoint. */
    public function retryJob(string $publicId): ?int
    {
        $this->pdo->beginTransaction();
        try {
            $jobStmt=$this->pdo->prepare("SELECT id FROM module_toggl_jobs WHERE public_id=:id AND status IN ('completed_with_warnings','failed','cancelled') LIMIT 1 FOR UPDATE");
            $jobStmt->execute(['id'=>$publicId]);$job=$jobStmt->fetch(PDO::FETCH_ASSOC);
            if(!is_array($job)){$this->pdo->commit();return null;}
            $items=$this->pdo->prepare("UPDATE module_toggl_job_items SET status='pending',error_code=NULL,error_message=NULL,updated_at=UTC_TIMESTAMP() WHERE job_id=:job AND status='failed'");$items->execute(['job'=>(int)$job['id']]);$count=$items->rowCount();
            $this->pdo->prepare("UPDATE module_toggl_jobs SET status='queued',last_source_cursor=NULL,updated_at=UTC_TIMESTAMP() WHERE id=:id")->execute(['id'=>(int)$job['id']]);
            $this->pdo->commit();return $count;
        } catch(\Throwable $e){if($this->pdo->inTransaction())$this->pdo->rollBack();throw $e;}
    }

    public function resetFailedItems(string $publicId): int
    { return $this->retryJob($publicId) ?? 0; }

    public function upsertItem(int $jobId, string $type, string $sourceId, array $data): void
    {
        $existing = $this->findItem($jobId, $type, $sourceId); $fields = ['source_parent_id','source_project_id','target_type','target_public_id','created_by_job','status','checksum','source_updated_at','attempts','error_code','error_message','payload_json'];
        if ($existing) {
            if (($data['status'] ?? null) === 'pending' && (string)($existing['checksum'] ?? '') === (string)($data['checksum'] ?? '') && in_array((string)$existing['status'], ['imported','updated','skipped'], true)) unset($data['status']);
            $sets = ['updated_at=:updated']; $params = ['id' => $existing['id'], 'updated' => $this->now()];
            foreach ($fields as $field) if (array_key_exists($field, $data)) { $sets[] = $field . '=:' . $field; $params[$field] = $field === 'payload_json' && is_array($data[$field]) ? $this->json($data[$field]) : $data[$field]; }
            $this->pdo->prepare('UPDATE module_toggl_job_items SET ' . implode(',', $sets) . ' WHERE id=:id')->execute($params); return;
        }
        $stmt = $this->pdo->prepare('INSERT INTO module_toggl_job_items (job_id,source_type,source_id,source_parent_id,source_project_id,target_type,target_public_id,created_by_job,status,checksum,source_updated_at,attempts,error_code,error_message,payload_json,created_at,updated_at) VALUES (:job,:type,:source,:parent,:project,:target_type,:target,:created_by,:status,:checksum,:source_updated,:attempts,:error_code,:error_message,:payload,:created,:updated)');
        $stmt->execute(['job' => $jobId, 'type' => $type, 'source' => $sourceId, 'parent' => $data['source_parent_id'] ?? null, 'project' => $data['source_project_id'] ?? null, 'target_type' => $data['target_type'] ?? null, 'target' => $data['target_public_id'] ?? null, 'created_by' => !empty($data['created_by_job']) ? 1 : 0, 'status' => $data['status'] ?? 'pending', 'checksum' => $data['checksum'] ?? null, 'source_updated' => $data['source_updated_at'] ?? null, 'attempts' => $data['attempts'] ?? 0, 'error_code' => $data['error_code'] ?? null, 'error_message' => $data['error_message'] ?? null, 'payload' => isset($data['payload_json']) ? (is_array($data['payload_json']) ? $this->json($data['payload_json']) : $data['payload_json']) : null, 'created' => $this->now(), 'updated' => $this->now()]);
    }

    public function findItem(int $jobId, string $type, string $sourceId): ?array
    { $stmt = $this->pdo->prepare('SELECT * FROM module_toggl_job_items WHERE job_id=:job AND source_type=:type AND source_id=:source LIMIT 1'); $stmt->execute(['job' => $jobId, 'type' => $type, 'source' => $sourceId]); $row = $stmt->fetch(PDO::FETCH_ASSOC); return is_array($row) ? $row : null; }

    public function items(int $jobId, ?string $status = null, int $limit = 5000): array
    { $sql = 'SELECT * FROM module_toggl_job_items WHERE job_id=:job'; $params = ['job' => $jobId]; if ($status !== null && $status !== '') { $sql .= ' AND status=:status'; $params['status'] = $status; } $sql .= ' ORDER BY id ASC LIMIT ' . max(1, min(10000, $limit)); $stmt = $this->pdo->prepare($sql); $stmt->execute($params); return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: []; }

    /** @return array<int,array<string,mixed>> */
    public function importItemsBatch(int $jobId, int $priority = 0, int $lastId = 0, int $limit = 250): array
    {
        $limit = max(1, min(1000, $limit));
        $sql = "SELECT * FROM (SELECT i.*, CASE i.source_type WHEN 'client' THEN 10 WHEN 'project' THEN 20 WHEN 'tag' THEN 30 WHEN 'task' THEN 40 WHEN 'time_entry' THEN 50 ELSE 90 END AS import_priority FROM module_toggl_job_items i WHERE i.job_id=:job AND i.status='pending') AS ordered_items WHERE (ordered_items.import_priority>:priority OR (ordered_items.import_priority=:same_priority AND ordered_items.id>:last_id)) ORDER BY ordered_items.import_priority ASC, ordered_items.id ASC LIMIT {$limit}";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['job' => $jobId, 'priority' => $priority, 'same_priority' => $priority, 'last_id' => $lastId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /** @return array<int,array<string,mixed>> */
    public function rollbackItemsBatch(int $jobId, int $beforeId, int $limit = 250): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM module_toggl_job_items WHERE job_id=:job AND created_by_job=1 AND target_public_id IS NOT NULL AND status<>\'rolled_back\' AND id<:before_id ORDER BY id DESC LIMIT ' . max(1, min(1000, $limit)));
        $stmt->execute(['job' => $jobId, 'before_id' => $beforeId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function updateCursor(string $publicId, string $cursor, ?string $leaseToken = null): void
    {
        $params = ['id' => $publicId, 'cursor' => $cursor];
        $where = ' WHERE public_id=:id';
        if ($leaseToken !== null) { $where .= ' AND lease_token=:lease AND lease_until >= UTC_TIMESTAMP() AND status IN (\'running\',\'rolling_back\')'; $params['lease'] = $leaseToken; }
        $stmt = $this->pdo->prepare('UPDATE module_toggl_jobs SET last_source_cursor=:cursor,updated_at=UTC_TIMESTAMP()' . $where);
        $stmt->execute($params);
        if ($leaseToken !== null && !$this->ownsLease($publicId, $leaseToken)) throw new \RuntimeException('TOGGL_JOB_LEASE_LOST');
    }

    public function itemCounts(int $jobId): array
    { $stmt = $this->pdo->prepare('SELECT status,COUNT(*) AS count FROM module_toggl_job_items WHERE job_id=:job GROUP BY status'); $stmt->execute(['job' => $jobId]); $out = []; foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) $out[(string)$row['status']]=(int)$row['count']; return $out; }

    public function itemCount(int $jobId): int
    { $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM module_toggl_job_items WHERE job_id=:job'); $stmt->execute(['job' => $jobId]); return (int)$stmt->fetchColumn(); }

    public function findMapping(int $connectionId, string $workspace, string $type, string $sourceId): ?array
    { $stmt = $this->pdo->prepare('SELECT * FROM module_toggl_source_mappings WHERE connection_id=:connection AND workspace_gid=:workspace AND source_type=:type AND source_id=:source LIMIT 1'); $stmt->execute(['connection' => $connectionId, 'workspace' => $workspace, 'type' => $type, 'source' => $sourceId]); $row = $stmt->fetch(PDO::FETCH_ASSOC); return is_array($row) ? $row : null; }

    public function upsertMapping(int $connectionId, string $workspace, string $type, string $sourceId, array $data): array
    {
        $existing = $this->findMapping($connectionId, $workspace, $type, $sourceId); $now = $this->now();
        if ($existing) { $target = $data['target_public_id'] ?? $existing['target_public_id']; $sameTarget = (string)($existing['target_public_id'] ?? '') !== '' && (string)$existing['target_public_id'] === (string)$target; $ownerJob = $sameTarget && !empty($existing['created_by_job_id']) ? $existing['created_by_job_id'] : ($data['created_by_job_id'] ?? $existing['created_by_job_id']); $stmt = $this->pdo->prepare('UPDATE module_toggl_source_mappings SET source_parent_id=:parent,target_type=:target_type,target_public_id=:target,source_checksum=:source_checksum,target_checksum=:target_checksum,state=:state,created_by_job_id=:job,last_seen_at=:seen,updated_at=:updated WHERE id=:id'); $stmt->execute(['parent'=>$data['source_parent_id']??$existing['source_parent_id'],'target_type'=>$data['target_type']??$existing['target_type'],'target'=>$target,'source_checksum'=>$data['source_checksum']??$existing['source_checksum'],'target_checksum'=>$data['target_checksum']??$existing['target_checksum'],'state'=>$data['state']??'active','job'=>$ownerJob,'seen'=>$now,'updated'=>$now,'id'=>$existing['id']]); return $this->findMapping($connectionId,$workspace,$type,$sourceId)??$existing; }
        $publicId=$this->id('tgm'); $stmt=$this->pdo->prepare('INSERT INTO module_toggl_source_mappings (public_id,connection_id,workspace_gid,source_type,source_id,source_parent_id,target_type,target_public_id,source_checksum,target_checksum,state,created_by_job_id,last_seen_at,created_at,updated_at) VALUES (:public_id,:connection,:workspace,:type,:source,:parent,:target_type,:target,:source_checksum,:target_checksum,:state,:job,:seen,:created,:updated)'); $stmt->execute(['public_id'=>$publicId,'connection'=>$connectionId,'workspace'=>$workspace,'type'=>$type,'source'=>$sourceId,'parent'=>$data['source_parent_id']??null,'target_type'=>$data['target_type']??null,'target'=>$data['target_public_id']??null,'source_checksum'=>$data['source_checksum']??null,'target_checksum'=>$data['target_checksum']??null,'state'=>$data['state']??'active','job'=>$data['created_by_job_id']??null,'seen'=>$now,'created'=>$now,'updated'=>$now]); return $this->findMapping($connectionId,$workspace,$type,$sourceId)??['public_id'=>$publicId];
    }

    public function upsertUserMapping(int $connectionId, array $user): void
    {
        $gid = trim((string)($user['gid'] ?? $user['id'] ?? ''));
        if ($gid === '') return;
        $email = trim((string)($user['email'] ?? ''));
        $crmPublicId = null;
        if ($email !== '') {
            $stmt = $this->pdo->prepare('SELECT public_id FROM users WHERE email=:email AND is_active=1 LIMIT 1');
            $stmt->execute(['email' => $email]);
            $candidate = $stmt->fetchColumn();
            if ($candidate !== false) $crmPublicId = (string)$candidate;
        }
        $status = $crmPublicId !== null ? 'mapped' : 'unmapped';
        $stmt = $this->pdo->prepare('INSERT INTO module_toggl_user_mappings (connection_id,toggl_user_gid,display_name,email,crm_user_public_id,mapping_status,created_at,updated_at) VALUES (:connection,:gid,:name,:email,:crm,:status,:created,:updated) ON DUPLICATE KEY UPDATE display_name=VALUES(display_name),email=VALUES(email),crm_user_public_id=COALESCE(crm_user_public_id,VALUES(crm_user_public_id)),mapping_status=CASE WHEN crm_user_public_id IS NOT NULL THEN \'mapped\' ELSE VALUES(mapping_status) END,updated_at=VALUES(updated_at)');
        $stmt->execute(['connection'=>$connectionId,'gid'=>$gid,'name'=>$user['name']??$user['fullname']??null,'email'=>$email!==''?$email:null,'crm'=>$crmPublicId,'status'=>$status,'created'=>$this->now(),'updated'=>$this->now()]);
    }
    public function listCrmUsers(): array
    {
        $stmt = $this->pdo->query('SELECT id,public_id,login,full_name,email FROM users WHERE is_active=1 ORDER BY full_name ASC');
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
    public function listUserMappings(int $connectionId): array
    { $stmt=$this->pdo->prepare('SELECT * FROM module_toggl_user_mappings WHERE connection_id=:id ORDER BY display_name ASC'); $stmt->execute(['id'=>$connectionId]); return $stmt->fetchAll(PDO::FETCH_ASSOC)?:[]; }
    public function updateUserMapping(int $connectionId,int $mappingId,?string $crmPublicId): bool
    { $status=$crmPublicId?'mapped':'unmapped'; $stmt=$this->pdo->prepare('UPDATE module_toggl_user_mappings SET crm_user_public_id=:crm,mapping_status=:status,updated_at=UTC_TIMESTAMP() WHERE id=:id AND connection_id=:connection'); $stmt->execute(['crm'=>$crmPublicId?:null,'status'=>$status,'id'=>$mappingId,'connection'=>$connectionId]); return $stmt->rowCount()===1; }
    public function activeUserPublicId(string $publicId): ?string
    { $stmt=$this->pdo->prepare('SELECT public_id FROM users WHERE public_id=:id AND is_active=1 LIMIT 1'); $stmt->execute(['id'=>$publicId]); $value=$stmt->fetchColumn(); return $value===false?null:(string)$value; }public function mappedUserId(int $connectionId,string $togglGid): ?int
     { $stmt=$this->pdo->prepare('SELECT u.id FROM module_toggl_user_mappings m JOIN users u ON u.public_id=m.crm_user_public_id WHERE m.connection_id=:connection AND m.toggl_user_gid=:gid AND m.mapping_status=\'mapped\' AND u.is_active=1 LIMIT 1'); $stmt->execute(['connection'=>$connectionId,'gid'=>$togglGid]); $value=$stmt->fetchColumn(); return $value===false?null:(int)$value; }

    public function mappedUserPublicId(int $connectionId, string $togglGid): ?string
    { $stmt=$this->pdo->prepare('SELECT u.public_id FROM module_toggl_user_mappings m JOIN users u ON u.public_id=m.crm_user_public_id WHERE m.connection_id=:connection AND m.toggl_user_gid=:gid AND m.mapping_status=\'mapped\' AND u.is_active=1 LIMIT 1'); $stmt->execute(['connection'=>$connectionId,'gid'=>$togglGid]); $value=$stmt->fetchColumn(); return $value===false?null:(string)$value; }


    public function addLog(int $jobId,string $level,string $step,string $message,array $context=[]): void
    { $stmt=$this->pdo->prepare('INSERT INTO module_toggl_job_logs (job_id,level,step,message,context_json,created_at) VALUES (:job,:level,:step,:message,:context,:created)'); $stmt->execute(['job'=>$jobId,'level'=>$level,'step'=>$step,'message'=>mb_substr($message,0,2000),'context'=>$context===[]?null:$this->json($context),'created'=>$this->now()]); }
    public function logs(int $jobId,int $limit=100): array
    { $stmt=$this->pdo->prepare('SELECT * FROM module_toggl_job_logs WHERE job_id=:job ORDER BY id DESC LIMIT '.max(1,min(1000,$limit))); $stmt->execute(['job'=>$jobId]); return $stmt->fetchAll(PDO::FETCH_ASSOC)?:[]; }
    public function report(string $publicId): array
    { $job=$this->getJob($publicId); if(!$job)return[]; $job['items']=$this->itemCounts((int)$job['id']); $job['logs']=$this->logs((int)$job['id']); return $job; }
    public function unresolved(int $jobId,string $type,string $sourceId,string $code,string $text,array $payload=[]): void
    { $stmt=$this->pdo->prepare('INSERT INTO module_toggl_unresolved_entities (job_id,source_type,source_id,reason_code,reason_text,payload_json,created_at) VALUES (:job,:type,:source,:code,:text,:payload,:created)'); $stmt->execute(['job'=>$jobId,'type'=>$type,'source'=>$sourceId,'code'=>$code,'text'=>$text,'payload'=>$payload===[]?null:$this->json($payload),'created'=>$this->now()]); }

    public function taskInternalId(string $publicId): ?int
    { $stmt=$this->pdo->prepare('SELECT id FROM tasks WHERE public_id=:id AND deleted_at IS NULL LIMIT 1'); $stmt->execute(['id'=>$publicId]); $value=$stmt->fetchColumn(); return $value===false?null:(int)$value; }

    public function targetExists(string $targetType, string $publicId): bool
    {
        $table = match ($targetType) { 'client' => 'counterparties', 'project' => 'projects', 'task' => 'tasks', 'tag' => 'tags', default => '' };
        if ($table === '') return false;
        $where = $table === 'tasks' ? ' AND deleted_at IS NULL' : '';
        $stmt = $this->pdo->prepare('SELECT id FROM ' . $table . ' WHERE public_id=:id' . $where . ' LIMIT 1');
        $stmt->execute(['id'=>$publicId]);
        return $stmt->fetchColumn() !== false;
    }

    public function createWorklog(array $data): string
    {
        $publicId = $this->id('wlg');
        $stmt = $this->pdo->prepare('INSERT INTO work_logs (public_id,user_id,task_id,minutes_spent,note,logged_at,started_at,ended_at,created_at) VALUES (:public_id,:user_id,:task_id,:minutes,:note,:logged_at,:started,:ended,:created)');
        $stmt->execute(['public_id'=>$publicId,'user_id'=>(int)$data['user_id'],'task_id'=>$data['task_id']??null,'minutes'=>(int)$data['minutes_spent'],'note'=>(string)($data['note']??''),'logged_at'=>(string)$data['logged_at'],'started'=>$data['started_at']??null,'ended'=>$data['ended_at']??null,'created'=>$this->now()]);
        return $publicId;
    }

    public function updateWorklogByPublicId(string $publicId, array $data): bool
    {
        $set = ['user_id=:user_id','task_id=:task_id','minutes_spent=:minutes','note=:note','logged_at=:logged_at'];
        $params = ['id'=>$publicId,'user_id'=>(int)$data['user_id'],'task_id'=>$data['task_id']??null,'minutes'=>(int)$data['minutes_spent'],'note'=>(string)($data['note']??''),'logged_at'=>(string)$data['logged_at']];
        if (array_key_exists('started_at',$data)) { $set[]='started_at=:started'; $params['started']=$data['started_at']; }
        if (array_key_exists('ended_at',$data)) { $set[]='ended_at=:ended'; $params['ended']=$data['ended_at']; }
        $stmt=$this->pdo->prepare('UPDATE work_logs SET '.implode(',',$set).' WHERE public_id=:id');
        $stmt->execute($params);
        return $stmt->rowCount()>0 || $this->worklogExists($publicId);
    }

    public function worklogExists(string $publicId): bool
    { $stmt=$this->pdo->prepare('SELECT id FROM work_logs WHERE public_id=:id LIMIT 1'); $stmt->execute(['id'=>$publicId]); return $stmt->fetchColumn() !== false; }

    public function deleteWorklogByPublicId(string $publicId): bool
    { $stmt=$this->pdo->prepare('DELETE FROM work_logs WHERE public_id=:id'); $stmt->execute(['id'=>$publicId]); return $stmt->rowCount()>0; }
}
