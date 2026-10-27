<?php
declare(strict_types=1);

namespace Module\Crm\SlackIntegration\Repository;

use PDO;

/** All user and event operations are pinned to one workspace. */
final class SlackRepository
{
    public function __construct(private readonly PDO $pdo, private readonly int $organizationId) {}
    private function publicId(string $prefix): string { return $prefix . '_' . bin2hex(random_bytes(10)); }
    private function now(): string { return gmdate('Y-m-d H:i:s'); }
    private function scope(): array { return ['organization_id' => max(0, $this->organizationId)]; }
    public function listConnections(): array { $s=$this->pdo->prepare('SELECT id, public_id, organization_id, name, channel, last_status, last_message, created_by_user_id, created_at, updated_at FROM module_slack_connections WHERE organization_id=:organization_id ORDER BY created_at DESC');$s->execute($this->scope());return $s->fetchAll(PDO::FETCH_ASSOC)?:[]; }
    public function getConnection(string $publicId): ?array { $s=$this->pdo->prepare('SELECT id, public_id, organization_id, name, channel, webhook_url_encrypted, last_status, last_message, created_by_user_id, created_at, updated_at FROM module_slack_connections WHERE public_id=:public_id AND organization_id=:organization_id LIMIT 1');$s->execute(['public_id'=>$publicId]+$this->scope());$r=$s->fetch(PDO::FETCH_ASSOC);return is_array($r)?$r:null; }
    public function getConnectionById(int $id): ?array { $s=$this->pdo->prepare('SELECT id, public_id, organization_id, name, channel, webhook_url_encrypted, last_status, last_message, created_by_user_id, created_at, updated_at FROM module_slack_connections WHERE id=:id AND organization_id=:organization_id LIMIT 1');$s->execute(['id'=>$id]+$this->scope());$r=$s->fetch(PDO::FETCH_ASSOC);return is_array($r)?$r:null; }
    public function createConnection(array $data): array { if($this->organizationId<=0)throw new \RuntimeException('Workspace context is required');$id=$this->publicId('slk');$now=$this->now();$this->pdo->prepare('INSERT INTO module_slack_connections (organization_id,public_id,name,channel,webhook_url_encrypted,created_by_user_id,created_at,updated_at) VALUES (:organization_id,:public_id,:name,:channel,:webhook,:created_by_user_id,:created_at,:updated_at)')->execute($this->scope()+['public_id'=>$id,'name'=>$data['name'],'channel'=>$data['channel']??null,'webhook'=>$data['webhook_url_encrypted'],'created_by_user_id'=>$data['created_by_user_id']??null,'created_at'=>$now,'updated_at'=>$now]);return $this->getConnection($id)??['public_id'=>$id]; }
    public function updateConnection(string $publicId,array $data): void { $sets=[];$p=['public_id'=>$publicId]+$this->scope();foreach(['name','channel','webhook_url_encrypted'] as $f)if(array_key_exists($f,$data)){$sets[]="$f=:$f";$p[$f]=$data[$f];}if(!$sets)return;$sets[]='updated_at=:updated_at';$p['updated_at']=$this->now();$this->pdo->prepare('UPDATE module_slack_connections SET '.implode(',',$sets).' WHERE public_id=:public_id AND organization_id=:organization_id')->execute($p); }
    public function updateConnectionLastCheck(string $id,string $status,string $message): void { $this->pdo->prepare('UPDATE module_slack_connections SET last_status=:status,last_message=:message,updated_at=:updated_at WHERE public_id=:public_id AND organization_id=:organization_id')->execute(['status'=>$status,'message'=>mb_substr($message,0,500),'updated_at'=>$this->now(),'public_id'=>$id]+$this->scope()); }
    public function deleteConnection(string $id): void { $c=$this->getConnection($id);if(!$c)return;$this->pdo->beginTransaction();try{$p=$this->scope()+['connection_id'=>(int)$c['id']];$this->pdo->prepare('DELETE FROM module_slack_deliveries WHERE organization_id=:organization_id AND connection_id=:connection_id')->execute($p);$this->pdo->prepare('DELETE FROM module_slack_rules WHERE organization_id=:organization_id AND connection_id=:connection_id')->execute($p);$this->pdo->prepare('DELETE FROM module_slack_connections WHERE id=:connection_id AND organization_id=:organization_id')->execute($p);$this->pdo->commit();}catch(\Throwable $e){if($this->pdo->inTransaction())$this->pdo->rollBack();throw $e;} }
    public function listRules(): array { $s=$this->pdo->prepare('SELECT r.id,r.public_id,r.organization_id,r.connection_id,r.event_code,r.text_template,r.is_enabled,r.created_by_user_id,r.created_at,r.updated_at,c.name AS connection_name FROM module_slack_rules r JOIN module_slack_connections c ON c.id=r.connection_id AND c.organization_id=r.organization_id WHERE r.organization_id=:organization_id ORDER BY r.created_at DESC');$s->execute($this->scope());return $s->fetchAll(PDO::FETCH_ASSOC)?:[]; }
    public function getRule(string $id): ?array { $s=$this->pdo->prepare('SELECT id,public_id,organization_id,connection_id,event_code,text_template,is_enabled,created_by_user_id,created_at,updated_at FROM module_slack_rules WHERE public_id=:public_id AND organization_id=:organization_id LIMIT 1');$s->execute(['public_id'=>$id]+$this->scope());$r=$s->fetch(PDO::FETCH_ASSOC);return is_array($r)?$r:null; }
    public function listEnabledRulesByEvent(array $codes): array { $codes=array_values(array_filter(array_map('strval',$codes),static fn($c)=>$c!==''));if(!$codes||$this->organizationId<=0)return [];$ph=implode(',',array_fill(0,count($codes),'?'));$s=$this->pdo->prepare("SELECT r.id,r.public_id,r.organization_id,r.connection_id,r.event_code,r.text_template,r.is_enabled,r.created_by_user_id,r.created_at,r.updated_at FROM module_slack_rules r JOIN module_slack_connections c ON c.id=r.connection_id AND c.organization_id=r.organization_id WHERE r.organization_id=? AND r.is_enabled=1 AND r.event_code IN ($ph) ORDER BY r.created_at ASC");$s->execute(array_merge([$this->organizationId],$codes));return $s->fetchAll(PDO::FETCH_ASSOC)?:[]; }
    public function createRule(array $d): array { if(!$this->getConnectionById((int)$d['connection_id']))throw new \RuntimeException('Connection is outside active workspace');$id=$this->publicId('slr');$n=$this->now();$this->pdo->prepare('INSERT INTO module_slack_rules (organization_id,public_id,connection_id,event_code,text_template,is_enabled,created_by_user_id,created_at,updated_at) VALUES (:organization_id,:public_id,:connection_id,:event_code,:text_template,:is_enabled,:created_by_user_id,:created_at,:updated_at)')->execute($this->scope()+['public_id'=>$id,'connection_id'=>$d['connection_id'],'event_code'=>$d['event_code'],'text_template'=>$d['text_template'],'is_enabled'=>(int)($d['is_enabled']??1),'created_by_user_id'=>$d['created_by_user_id']??null,'created_at'=>$n,'updated_at'=>$n]);return $this->getRule($id)??['public_id'=>$id]; }
    public function deleteRule(string $id): void { $rule=$this->getRule($id);if(!$rule)return;$p=['public_id'=>$id]+$this->scope();$this->pdo->beginTransaction();try{$this->pdo->prepare('DELETE FROM module_slack_deliveries WHERE organization_id=:organization_id AND rule_id=:rule_id')->execute($this->scope()+['rule_id'=>(int)$rule['id']]);$this->pdo->prepare('DELETE FROM module_slack_rules WHERE public_id=:public_id AND organization_id=:organization_id')->execute($p);$this->pdo->commit();}catch(\Throwable $e){if($this->pdo->inTransaction())$this->pdo->rollBack();throw $e;} }
    public function enqueueDelivery(array $d): array { if($this->organizationId<=0)throw new \RuntimeException('Workspace context is required');$cid=(int)($d['connection_id']??0);if($cid<=0||!$this->getConnectionById($cid))throw new \RuntimeException('Connection is outside active workspace');$rid=(int)($d['rule_id']??0);if($rid>0&&!$this->getRuleById($rid))throw new \RuntimeException('Rule is outside active workspace');$id=$this->publicId('sld');$now=$this->now();$this->pdo->prepare('INSERT INTO module_slack_deliveries (organization_id,public_id,connection_id,rule_id,event_code,payload_json,status,attempts,next_run_at,created_at,updated_at) VALUES (:organization_id,:public_id,:connection_id,:rule_id,:event_code,:payload_json,\'queued\',0,:next_run_at,:created_at,:updated_at)')->execute($this->scope()+['public_id'=>$id,'connection_id'=>$cid?:null,'rule_id'=>$rid?:null,'event_code'=>$d['event_code']??null,'payload_json'=>isset($d['payload_json'])?(is_string($d['payload_json'])?$d['payload_json']:json_encode($d['payload_json'],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)):null,'next_run_at'=>$now,'created_at'=>$now,'updated_at'=>$now]);return $this->getDelivery($id)??['public_id'=>$id]; }
    private function getRuleById(int $id): ?array { $s=$this->pdo->prepare('SELECT id FROM module_slack_rules WHERE id=:id AND organization_id=:organization_id LIMIT 1');$s->execute(['id'=>$id]+$this->scope());$r=$s->fetch(PDO::FETCH_ASSOC);return is_array($r)?$r:null; }
    public function getDelivery(string $id): ?array { $s=$this->pdo->prepare('SELECT id,public_id,organization_id,connection_id,rule_id,event_code,payload_json,status,attempts,response_code,last_error,next_run_at,sent_at,created_at,updated_at FROM module_slack_deliveries WHERE public_id=:public_id AND organization_id=:organization_id LIMIT 1');$s->execute(['public_id'=>$id]+$this->scope());$r=$s->fetch(PDO::FETCH_ASSOC);return is_array($r)?$r:null; }
    public function listDeliveries(int $limit=50): array { $s=$this->pdo->prepare('SELECT d.id,d.public_id,d.organization_id,d.connection_id,d.rule_id,d.event_code,d.status,d.attempts,d.response_code,d.last_error,d.sent_at,d.created_at,c.name AS connection_name FROM module_slack_deliveries d LEFT JOIN module_slack_connections c ON c.id=d.connection_id AND c.organization_id=d.organization_id WHERE d.organization_id=:organization_id ORDER BY d.created_at DESC LIMIT '.max(1,min(200,$limit)));$s->execute($this->scope());return $s->fetchAll(PDO::FETCH_ASSOC)?:[]; }
    /** Internal queue claim intentionally spans tenants, but returns the organization id and no secrets. */
    public static function claimNextDeliveryForWorker(PDO $pdo): ?array
    {
        $now = gmdate('Y-m-d H:i:s');
        $expired = gmdate('Y-m-d H:i:s', time() - 300);
        $token = bin2hex(random_bytes(32));
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare("SELECT id FROM module_slack_deliveries WHERE organization_id IS NOT NULL AND (next_run_at IS NULL OR next_run_at <= :now) AND (status = 'queued' OR (status = 'sending' AND (locked_at IS NULL OR locked_at <= :expired))) ORDER BY COALESCE(next_run_at, created_at) ASC, id ASC LIMIT 1 FOR UPDATE");
            $stmt->execute(['now' => $now, 'expired' => $expired]);
            $id = $stmt->fetchColumn();
            if ($id === false) { $pdo->commit(); return null; }

            $claim = $pdo->prepare("UPDATE module_slack_deliveries SET locked_at = :locked_at, locked_token = :token, status = 'sending', updated_at = :updated_at WHERE id = :id AND organization_id IS NOT NULL AND (status = 'queued' OR (status = 'sending' AND (locked_at IS NULL OR locked_at <= :expired)))");
            $claim->execute(['locked_at' => $now, 'token' => $token, 'updated_at' => $now, 'id' => (int)$id, 'expired' => $expired]);
            if ($claim->rowCount() !== 1) { $pdo->rollBack(); return null; }
            $pdo->commit();

            $stmt = $pdo->prepare('SELECT id,public_id,organization_id,connection_id,rule_id,event_code,payload_json,status,attempts FROM module_slack_deliveries WHERE id = :id AND locked_token = :token AND status = \'sending\' LIMIT 1');
            $stmt->execute(['id' => (int)$id, 'token' => $token]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!is_array($row) || (int)($row['organization_id'] ?? 0) <= 0) return null;
            $row['_lease_token'] = $token;
            return $row;
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
    }

    public function markDelivered(string $id, string $leaseToken, int $code): void
    {
        $this->pdo->prepare("UPDATE module_slack_deliveries SET status='sent',response_code=:code,attempts=attempts+1,locked_at=NULL,locked_token=NULL,last_error=NULL,sent_at=:sent_at,updated_at=:updated_at WHERE public_id=:public_id AND organization_id=:organization_id AND status='sending' AND locked_token=:lease_token")
            ->execute(['code' => $code, 'sent_at' => $this->now(), 'updated_at' => $this->now(), 'public_id' => $id, 'lease_token' => $leaseToken] + $this->scope());
    }

    public function markFailed(string $id, string $leaseToken, int $code, string $error, int $maxRetries, int $backoff): void
    {
        $stmt = $this->pdo->prepare("SELECT attempts FROM module_slack_deliveries WHERE public_id=:public_id AND organization_id=:organization_id AND status='sending' AND locked_token=:lease_token LIMIT 1");
        $stmt->execute(['public_id' => $id, 'lease_token' => $leaseToken] + $this->scope());
        $attempts = $stmt->fetchColumn();
        if ($attempts === false) return;
        $nextAttempt = (int)$attempts + 1;
        $failed = $nextAttempt >= max(1, $maxRetries);
        $status = $failed ? 'failed' : 'queued';
        $sql = "UPDATE module_slack_deliveries SET status=:status,response_code=:code,attempts=:attempts,locked_at=NULL,locked_token=NULL,last_error=:error,next_run_at=:next_run_at,updated_at=:updated_at WHERE public_id=:public_id AND organization_id=:organization_id AND status='sending' AND locked_token=:lease_token";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            'status' => $status,
            'code' => $code,
            'attempts' => $nextAttempt,
            'error' => mb_substr($error, 0, 500),
            'next_run_at' => $failed ? null : gmdate('Y-m-d H:i:s', time() + max(1, $backoff) * $nextAttempt),
            'updated_at' => $this->now(),
            'public_id' => $id,
            'lease_token' => $leaseToken,
        ] + $this->scope());
    }

    public function releaseDelivery(string $id, string $leaseToken): void
    {
        $this->pdo->prepare("UPDATE module_slack_deliveries SET status='queued',locked_at=NULL,locked_token=NULL,updated_at=:updated_at WHERE public_id=:public_id AND organization_id=:organization_id AND status='sending' AND locked_token=:lease_token")
            ->execute(['updated_at' => $this->now(), 'public_id' => $id, 'lease_token' => $leaseToken] + $this->scope());
    }
}
