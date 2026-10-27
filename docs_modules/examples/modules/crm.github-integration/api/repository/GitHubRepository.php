<?php
declare(strict_types=1);

namespace Module\Crm\GithubIntegration\Repository;

use PDO;

/** Tenant-scoped persistence. A zero organization id intentionally reads/writes nothing. */
final class GitHubRepository
{
    public function __construct(private readonly PDO $pdo, private readonly int $organizationId)
    {
    }

    private function publicId(string $prefix): string { return $prefix . '_' . bin2hex(random_bytes(10)); }
    private function now(): string { return gmdate('Y-m-d H:i:s'); }
    private function scope(): array { return ['organization_id' => max(0, $this->organizationId)]; }

    /** Worker-only: returns scope identifiers, never secrets or payloads. */
    public static function listActiveLinksForWorker(PDO $pdo, int $limit = 100): array
    {
        $stmt = $pdo->prepare('SELECT id, public_id, organization_id FROM module_github_repo_links WHERE organization_id IS NOT NULL AND is_active = 1 ORDER BY id ASC LIMIT ' . max(1, min(500, $limit)));
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function listConnections(): array
    {
        $stmt = $this->pdo->prepare('SELECT id, public_id, name, base_url, last_status, last_message, created_by_user_id, created_at, updated_at FROM module_github_connections WHERE organization_id = :organization_id ORDER BY created_at DESC');
        $stmt->execute($this->scope()); return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
    public function getConnection(string $publicId): ?array
    {
        $stmt = $this->pdo->prepare('SELECT id, public_id, organization_id, name, base_url, token_encrypted, last_status, last_message, created_by_user_id, created_at, updated_at FROM module_github_connections WHERE public_id = :public_id AND organization_id = :organization_id LIMIT 1');
        $stmt->execute(['public_id' => $publicId] + $this->scope()); $row = $stmt->fetch(PDO::FETCH_ASSOC); return is_array($row) ? $row : null;
    }
    public function getConnectionById(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT id, public_id, organization_id, name, base_url, token_encrypted, last_status, last_message, created_by_user_id, created_at, updated_at FROM module_github_connections WHERE id = :id AND organization_id = :organization_id LIMIT 1');
        $stmt->execute(['id' => $id] + $this->scope()); $row = $stmt->fetch(PDO::FETCH_ASSOC); return is_array($row) ? $row : null;
    }
    public function createConnection(array $data): array
    {
        if ($this->organizationId <= 0) { throw new \RuntimeException('Workspace context is required'); }
        $publicId = $this->publicId('ghc'); $now = $this->now();
        $stmt = $this->pdo->prepare('INSERT INTO module_github_connections (organization_id, public_id, name, base_url, token_encrypted, created_by_user_id, created_at, updated_at) VALUES (:organization_id, :public_id, :name, :base_url, :token, :created_by_user_id, :created_at, :updated_at)');
        $stmt->execute($this->scope() + ['public_id' => $publicId, 'name' => $data['name'], 'base_url' => $data['base_url'] ?? 'https://api.github.com', 'token' => $data['token_encrypted'], 'created_by_user_id' => $data['created_by_user_id'] ?? null, 'created_at' => $now, 'updated_at' => $now]);
        return $this->getConnection($publicId) ?? ['public_id' => $publicId];
    }
    public function updateConnectionLastCheck(string $publicId, string $status, string $message): void
    {
        $stmt = $this->pdo->prepare('UPDATE module_github_connections SET last_status = :status, last_message = :message, updated_at = :updated_at WHERE public_id = :public_id AND organization_id = :organization_id');
        $stmt->execute(['status' => $status, 'message' => mb_substr($message, 0, 500), 'updated_at' => $this->now(), 'public_id' => $publicId] + $this->scope());
    }
    public function updateConnection(string $publicId, array $data): void
    {
        $sets = ['updated_at = :updated_at']; $params = ['updated_at' => $this->now(), 'public_id' => $publicId] + $this->scope();
        foreach (['name', 'base_url', 'token_encrypted'] as $field) { if (array_key_exists($field, $data)) { $sets[] = "$field = :$field"; $params[$field] = $data[$field]; } }
        $this->pdo->prepare('UPDATE module_github_connections SET ' . implode(', ', $sets) . ' WHERE public_id = :public_id AND organization_id = :organization_id')->execute($params);
    }
    public function deleteConnection(string $publicId): void
    {
        $connection = $this->getConnection($publicId);
        if (!$connection) return;
        $links = $this->listLinks((int)$connection['id']);
        $this->pdo->beginTransaction();
        try {
            foreach ($links as $link) {
                $scope = $this->scope() + ['link_id' => (int)$link['id']];
                $this->pdo->prepare('DELETE FROM module_github_sync_items WHERE link_id = :link_id AND organization_id = :organization_id')->execute($scope);
                $this->pdo->prepare('DELETE FROM module_github_sync_logs WHERE link_id = :link_id AND organization_id = :organization_id')->execute($scope);
            }
            $this->pdo->prepare('DELETE FROM module_github_repo_links WHERE connection_id = :connection_id AND organization_id = :organization_id')->execute($this->scope() + ['connection_id' => (int)$connection['id']]);
            $this->pdo->prepare('DELETE FROM module_github_connections WHERE id = :connection_id AND organization_id = :organization_id')->execute($this->scope() + ['connection_id' => (int)$connection['id']]);
            $this->pdo->commit();
        } catch (\Throwable $e) { if ($this->pdo->inTransaction()) $this->pdo->rollBack(); throw $e; }
    }

    public function listLinks(?int $connectionId = null): array
    {
        $sql = 'SELECT l.*, c.name AS connection_name FROM module_github_repo_links l JOIN module_github_connections c ON c.id = l.connection_id AND c.organization_id = l.organization_id WHERE l.organization_id = :organization_id';
        $params = $this->scope(); if ($connectionId !== null) { $sql .= ' AND l.connection_id = :cid'; $params['cid'] = $connectionId; }
        $stmt = $this->pdo->prepare($sql . ' ORDER BY l.created_at DESC'); $stmt->execute($params); return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
    public function getLink(string $publicId): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM module_github_repo_links WHERE public_id = :public_id AND organization_id = :organization_id LIMIT 1');
        $stmt->execute(['public_id' => $publicId] + $this->scope()); $row = $stmt->fetch(PDO::FETCH_ASSOC); return is_array($row) ? $row : null;
    }
    public function getWebhookLink(string $publicId): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM module_github_repo_links WHERE public_id = :public_id AND organization_id IS NOT NULL LIMIT 1');
        $stmt->execute(['public_id' => $publicId]); $row = $stmt->fetch(PDO::FETCH_ASSOC); return is_array($row) ? $row : null;
    }
    public function createLink(array $data): array
    {
        if ($this->organizationId <= 0) throw new \RuntimeException('Workspace context is required');
        $connection = $this->getConnectionById((int)$data['connection_id']); if (!$connection) throw new \RuntimeException('Connection is outside the active workspace');
        $publicId = $this->publicId('ghl'); $now = $this->now();
        $stmt = $this->pdo->prepare('INSERT INTO module_github_repo_links (organization_id, public_id, connection_id, owner, repo, project_public_id, webhook_secret_encrypted, is_active, created_by_user_id, created_at, updated_at) VALUES (:organization_id, :public_id, :connection_id, :owner, :repo, :project_public_id, :webhook_secret, :is_active, :created_by_user_id, :created_at, :updated_at)');
        $stmt->execute($this->scope() + ['public_id' => $publicId, 'connection_id' => $data['connection_id'], 'owner' => $data['owner'], 'repo' => $data['repo'], 'project_public_id' => $data['project_public_id'], 'webhook_secret' => $data['webhook_secret_encrypted'] ?? null, 'is_active' => (int)($data['is_active'] ?? 1), 'created_by_user_id' => $data['created_by_user_id'] ?? null, 'created_at' => $now, 'updated_at' => $now]);
        return $this->getLink($publicId) ?? ['public_id' => $publicId];
    }
    public function deleteLink(string $publicId): void
    {
        $link = $this->getLink($publicId); if (!$link) return;
        $params = $this->scope() + ['link_id' => (int)$link['id']];
        $this->pdo->beginTransaction(); try {
            $this->pdo->prepare('DELETE FROM module_github_sync_items WHERE organization_id = :organization_id AND link_id = :link_id')->execute($params);
            $this->pdo->prepare('DELETE FROM module_github_sync_logs WHERE organization_id = :organization_id AND link_id = :link_id')->execute($params);
            $this->pdo->prepare('DELETE FROM module_github_repo_links WHERE id = :link_id AND organization_id = :organization_id')->execute($params);
            $this->pdo->commit();
        } catch (\Throwable $e) { if ($this->pdo->inTransaction()) $this->pdo->rollBack(); throw $e; }
    }
    public function markDirty(string $publicId): void { $this->pdo->prepare('UPDATE module_github_repo_links SET is_dirty = 1, updated_at = :updated_at WHERE public_id = :public_id AND organization_id = :organization_id')->execute(['updated_at' => $this->now(), 'public_id' => $publicId] + $this->scope()); }
    public function markSynced(string $publicId): void { $this->pdo->prepare('UPDATE module_github_repo_links SET is_dirty = 0, last_synced_at = :synced, updated_at = :updated_at WHERE public_id = :public_id AND organization_id = :organization_id')->execute(['synced' => $this->now(), 'updated_at' => $this->now(), 'public_id' => $publicId] + $this->scope()); }
    public function findLinkByRepo(int $connectionId, string $owner, string $repo): ?array { $stmt = $this->pdo->prepare('SELECT * FROM module_github_repo_links WHERE connection_id = :cid AND owner = :owner AND repo = :repo AND organization_id = :organization_id LIMIT 1'); $stmt->execute(['cid' => $connectionId, 'owner' => $owner, 'repo' => $repo] + $this->scope()); $row = $stmt->fetch(PDO::FETCH_ASSOC); return is_array($row) ? $row : null; }
    public function listActiveLinks(): array { $stmt = $this->pdo->prepare('SELECT * FROM module_github_repo_links WHERE organization_id = :organization_id AND is_active = 1 ORDER BY id ASC'); $stmt->execute($this->scope()); return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: []; }
    public function listSyncItems(int $linkId, int $limit = 500): array { $stmt = $this->pdo->prepare('SELECT * FROM module_github_sync_items WHERE link_id = :link_id AND organization_id = :organization_id ORDER BY id DESC LIMIT ' . max(1, min(2000, $limit))); $stmt->execute(['link_id' => $linkId] + $this->scope()); return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: []; }
    public function upsertSyncItem(int $linkId, string $sourceType, string $sourceId, array $data): void
    {
        if (!$this->getLinkById($linkId)) throw new \RuntimeException('Link is outside active workspace');
        $existing = $this->findSyncItem($linkId, $sourceType, $sourceId); $now = $this->now();
        if ($existing) { $sets = ['updated_at = :updated_at']; $params = ['id' => $existing['id'], 'updated_at' => $now] + $this->scope(); foreach (['target_type','target_public_id','status','payload_json'] as $field) if (array_key_exists($field,$data)) { $sets[] = "$field = :$field"; $params[$field] = $data[$field]; } $this->pdo->prepare('UPDATE module_github_sync_items SET '.implode(', ',$sets).' WHERE id = :id AND organization_id = :organization_id')->execute($params); return; }
        $this->pdo->prepare('INSERT INTO module_github_sync_items (organization_id, link_id, source_type, source_id, target_type, target_public_id, status, payload_json, created_at, updated_at) VALUES (:organization_id, :link_id, :source_type, :source_id, :target_type, :target_public_id, :status, :payload_json, :created_at, :updated_at)')->execute($this->scope() + ['link_id'=>$linkId,'source_type'=>$sourceType,'source_id'=>$sourceId,'target_type'=>$data['target_type']??null,'target_public_id'=>$data['target_public_id']??null,'status'=>$data['status']??'pending','payload_json'=>isset($data['payload_json'])?(is_string($data['payload_json'])?$data['payload_json']:json_encode($data['payload_json'],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)):null,'created_at'=>$now,'updated_at'=>$now]);
    }
    public function findSyncItem(int $linkId, string $sourceType, string $sourceId): ?array { $stmt=$this->pdo->prepare('SELECT * FROM module_github_sync_items WHERE link_id = :link_id AND source_type = :source_type AND source_id = :source_id AND organization_id = :organization_id LIMIT 1'); $stmt->execute(['link_id'=>$linkId,'source_type'=>$sourceType,'source_id'=>$sourceId]+$this->scope()); $row=$stmt->fetch(PDO::FETCH_ASSOC); return is_array($row)?$row:null; }
    public function findTaskMapping(string $taskPublicId): ?array { $stmt=$this->pdo->prepare("SELECT i.id AS sync_item_id, i.source_type, i.source_id, i.target_public_id, l.id AS link_id, l.* FROM module_github_sync_items i JOIN module_github_repo_links l ON l.id=i.link_id AND l.organization_id=i.organization_id WHERE i.target_type='task' AND i.target_public_id=:target_public_id AND i.source_type IN ('issue','pull_request') AND i.organization_id=:organization_id ORDER BY i.id DESC LIMIT 1"); $stmt->execute(['target_public_id'=>$taskPublicId]+$this->scope()); $row=$stmt->fetch(PDO::FETCH_ASSOC); return is_array($row)?$row:null; }
    public function addLog(?int $linkId, string $level, string $message): void { if ($linkId !== null && !$this->getLinkById($linkId)) return; $this->pdo->prepare('INSERT INTO module_github_sync_logs (organization_id, link_id, level, message, created_at) VALUES (:organization_id, :link_id, :level, :message, :created_at)')->execute($this->scope()+['link_id'=>$linkId,'level'=>$level,'message'=>mb_substr($message,0,2000),'created_at'=>$this->now()]); }
    private function getLinkById(int $id): ?array { $stmt=$this->pdo->prepare('SELECT id FROM module_github_repo_links WHERE id=:id AND organization_id=:organization_id LIMIT 1'); $stmt->execute(['id'=>$id]+$this->scope()); $r=$stmt->fetch(PDO::FETCH_ASSOC); return is_array($r)?$r:null; }
    public function listLogs(?int $linkId = null, int $limit = 50): array { $sql='SELECT * FROM module_github_sync_logs WHERE organization_id=:organization_id'; $p=$this->scope(); if($linkId!==null){$sql.=' AND link_id=:link_id';$p['link_id']=$linkId;} $stmt=$this->pdo->prepare($sql.' ORDER BY created_at DESC LIMIT '.max(1,min(200,$limit)));$stmt->execute($p);return $stmt->fetchAll(PDO::FETCH_ASSOC)?:[]; }
    public function getCommentBody(string $publicId): string { $stmt=$this->pdo->prepare('SELECT body FROM comments WHERE public_id=:public_id AND organization_id=:organization_id LIMIT 1'); $stmt->execute(['public_id'=>$publicId]+$this->scope()); $v=$stmt->fetchColumn(); return is_string($v)?trim($v):''; }
    public function getSettings(): array { return ['request_timeout_seconds'=>30,'max_retries'=>3,'batch_size'=>100,'default_base_url'=>'https://api.github.com','allowed_api_hosts'=>['api.github.com'],'sync_comments'=>true,'poll_interval_minutes'=>15]; }
}
