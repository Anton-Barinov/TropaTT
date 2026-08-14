<?php
declare(strict_types=1);

namespace Module\Crm\GoogleCalendar\Repository;

use Api\System\Library\Support\Ulid;
use PDO;

final class GoogleCalendarRepository
{
    public function __construct(private readonly PDO $pdo) {}

    public function connectionById(int $connectionId): ?array
    {
        $s = $this->pdo->prepare('SELECT * FROM google_calendar_connections WHERE id = :id LIMIT 1');
        $s->execute(['id' => $connectionId]);
        return $s->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function activeConnections(): array
    {
        return $this->pdo->query("SELECT c.* FROM google_calendar_connections c JOIN users u ON u.id = c.user_id WHERE u.is_active = 1 AND u.deleted_at IS NULL AND c.status IN ('active','sync_warning') ORDER BY c.id ASC")->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function orphanedConnections(): array
    {
        return $this->pdo->query("SELECT c.id,c.user_id FROM google_calendar_connections c LEFT JOIN users u ON u.id = c.user_id WHERE u.id IS NULL OR u.deleted_at IS NOT NULL ORDER BY c.id ASC")->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function connectionForUser(int $userId): ?array
    {
        $s = $this->pdo->prepare('SELECT * FROM google_calendar_connections WHERE user_id = :user_id LIMIT 1');
        $s->execute(['user_id' => $userId]);
        return $s->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function listConnectionsForUser(int $userId): array
    {
        $s = $this->pdo->prepare('SELECT id, public_id, user_id, google_account_email, status, last_error, last_sync_at, created_at, updated_at FROM google_calendar_connections WHERE user_id = :user_id ORDER BY id DESC');
        $s->execute(['user_id' => $userId]);
        return $s->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function createConnection(int $userId, array $values): array
    {
        $now = gmdate('Y-m-d H:i:s');
        $publicId = Ulid::generate('gcal');
        $s = $this->pdo->prepare('INSERT INTO google_calendar_connections (public_id,user_id,google_account_email,refresh_token_encrypted,access_token_encrypted,access_token_expires_at,status,last_error,last_sync_at,created_at,updated_at) VALUES (:public_id,:user_id,:email,:refresh,:access,:expires,:status,NULL,NULL,:created_at,:updated_at)');
        $s->execute(['public_id'=>$publicId,'user_id'=>$userId,'email'=>$values['email']??null,'refresh'=>$values['refresh_token_encrypted'],'access'=>$values['access_token_encrypted']??null,'expires'=>$values['access_token_expires_at']??null,'status'=>'active','created_at'=>$now,'updated_at'=>$now]);
        return $this->connectionForUser($userId) ?? [];
    }

    public function updateConnection(int $id, array $values): void
    {
        $allowed = ['google_account_email','refresh_token_encrypted','access_token_encrypted','access_token_expires_at','status','last_error','last_sync_at','updated_at'];
        $set = [];
        $params = ['id' => $id];
        foreach ($values as $key => $value) {
            if (in_array($key, $allowed, true)) { $set[] = $key . ' = :' . $key; $params[$key] = $value; }
        }
        if ($set === []) return;
        $set[] = 'updated_at = :updated_at'; $params['updated_at'] = gmdate('Y-m-d H:i:s');
        $s = $this->pdo->prepare('UPDATE google_calendar_connections SET ' . implode(',', $set) . ' WHERE id = :id');
        $s->execute($params);
    }

    public function deleteConnection(int $id): void
    {
        $this->pdo->beginTransaction();
        try {
            $sources = $this->pdo->prepare('SELECT id FROM google_calendar_sources WHERE connection_id = :id');
            $sources->execute(['id'=>$id]);
            $ids = array_map('intval', $sources->fetchAll(PDO::FETCH_COLUMN) ?: []);
            if ($ids !== []) {
                $ph = implode(',', array_fill(0, count($ids), '?'));
                $this->pdo->prepare('DELETE FROM google_calendar_events WHERE source_id IN (' . $ph . ')')->execute($ids);
            }
            $this->pdo->prepare('DELETE FROM google_calendar_sources WHERE connection_id = :id')->execute(['id'=>$id]);
            $this->pdo->prepare('DELETE FROM google_calendar_connections WHERE id = :id')->execute(['id'=>$id]);
            $this->pdo->commit();
        } catch (\Throwable $e) { if ($this->pdo->inTransaction()) $this->pdo->rollBack(); throw $e; }
    }

    public function sourceById(int $sourceId): ?array
    {
        $s = $this->pdo->prepare('SELECT * FROM google_calendar_sources WHERE id = :id LIMIT 1');
        $s->execute(['id' => $sourceId]);
        return $s->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function sources(int $connectionId): array
    {
        $s = $this->pdo->prepare('SELECT * FROM google_calendar_sources WHERE connection_id = :id AND is_enabled = 1 ORDER BY is_primary DESC, id ASC');
        $s->execute(['id'=>$connectionId]); return $s->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function allSources(int $connectionId): array
    {
        $s = $this->pdo->prepare('SELECT * FROM google_calendar_sources WHERE connection_id = :id ORDER BY is_primary DESC, id ASC');
        $s->execute(['id'=>$connectionId]); return $s->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function clearSourcesForConnection(int $connectionId, int $ownerId): void
    {
        $ownTransaction = !$this->pdo->inTransaction();
        if ($ownTransaction) $this->pdo->beginTransaction();
        try {
            $sourceStmt = $this->pdo->prepare('SELECT id FROM google_calendar_sources WHERE connection_id = :connection_id');
            $sourceStmt->execute(['connection_id' => $connectionId]);
            $sourceIds = array_map('intval', $sourceStmt->fetchAll(PDO::FETCH_COLUMN) ?: []);
            if ($sourceIds !== []) {
                $sourceParams = [];
                foreach ($sourceIds as $index => $sourceId) $sourceParams['source_'.$index] = $sourceId;
                $placeholders = implode(',', array_map(static fn(int $index): string => ':source_'.$index, array_keys($sourceIds)));
                $mappingStmt = $this->pdo->prepare('SELECT crm_event_public_id FROM google_calendar_events WHERE source_id IN (' . $placeholders . ') AND crm_event_public_id IS NOT NULL');
                $mappingStmt->execute($sourceParams);
                $eventStmt = $this->pdo->prepare('DELETE FROM calendar_events WHERE public_id = :public_id AND owner_user_id = :owner_id');
                foreach ($mappingStmt->fetchAll(PDO::FETCH_COLUMN) ?: [] as $crmId) {
                    $eventStmt->execute(['public_id' => (string)$crmId, 'owner_id' => $ownerId]);
                }
                $this->pdo->prepare('DELETE FROM google_calendar_events WHERE source_id IN (' . $placeholders . ')')->execute($sourceParams);
            }
            $this->pdo->prepare('DELETE FROM google_calendar_sources WHERE connection_id = :connection_id')->execute(['connection_id' => $connectionId]);
            if ($ownTransaction) $this->pdo->commit();
        } catch (\Throwable $e) {
            if ($ownTransaction && $this->pdo->inTransaction()) $this->pdo->rollBack();
            throw $e;
        }
    }

    /** @param array<int,string> $calendarIds */
    public function disableMissingSources(int $connectionId, array $calendarIds): void
    {
        if ($calendarIds === []) {
            $this->pdo->prepare("UPDATE google_calendar_sources SET is_enabled = 0, sync_token = NULL, last_error = 'Calendar is no longer accessible', updated_at = :updated_at WHERE connection_id = :connection_id")->execute(['connection_id' => $connectionId, 'updated_at' => gmdate('Y-m-d H:i:s')]);
            return;
        }
        $placeholders = [];
        $params = [
            'last_error' => 'Calendar is no longer accessible',
            'updated_at' => gmdate('Y-m-d H:i:s'),
            'connection_id' => $connectionId,
        ];
        foreach (array_values($calendarIds) as $index => $calendarId) {
            $key = 'calendar_'.$index;
            $placeholders[] = ':'.$key;
            $params[$key] = $calendarId;
        }
        $this->pdo->prepare('UPDATE google_calendar_sources SET is_enabled = 0, sync_token = NULL, last_error = :last_error, updated_at = :updated_at WHERE connection_id = :connection_id AND calendar_id NOT IN (' . implode(',', $placeholders) . ')')->execute($params);
    }

    public function upsertSource(int $connectionId, array $calendar): array
    {
        $calendarId = (string)($calendar['id'] ?? '');
        $find = $this->pdo->prepare('SELECT * FROM google_calendar_sources WHERE connection_id = :connection_id AND calendar_id = :calendar_id LIMIT 1');
        $find->execute(['connection_id'=>$connectionId,'calendar_id'=>$calendarId]); $row = $find->fetch(PDO::FETCH_ASSOC);
        $now = gmdate('Y-m-d H:i:s');
        if ($row) {
            // Re-enable only calendars previously disabled by reconciliation;
            // a user-disabled source (no reconciliation error) stays disabled.
            $wasMissing = (string)($row['last_error'] ?? '') === 'Calendar is no longer accessible';
            $set = 'summary=:summary, timezone=:timezone, updated_at=:updated_at';
            $params = ['summary'=>$calendar['summary']??null,'timezone'=>$calendar['timeZone']??null,'updated_at'=>$now,'id'=>$row['id']];
            if ($wasMissing) {
                $set .= ', is_enabled=1, sync_token=NULL, last_error=NULL';
            }
            $s = $this->pdo->prepare('UPDATE google_calendar_sources SET ' . $set . ' WHERE id=:id');
            $s->execute($params);
            return array_merge($row, ['summary'=>$calendar['summary']??null,'timezone'=>$calendar['timeZone']??null, 'is_enabled'=>$wasMissing ? 1 : $row['is_enabled'], 'last_error'=>$wasMissing ? null : $row['last_error']]);
        }
        $primary = (int)$this->pdo->query('SELECT COUNT(*) FROM google_calendar_sources WHERE connection_id = ' . (int)$connectionId)->fetchColumn() === 0 ? 1 : 0;
        $publicId = Ulid::generate('gsrc');
        $s = $this->pdo->prepare('INSERT INTO google_calendar_sources (public_id,connection_id,calendar_id,summary,timezone,direction,is_enabled,is_primary,created_at,updated_at) VALUES (:public_id,:connection_id,:calendar_id,:summary,:timezone,:direction,1,:primary,:created_at,:updated_at)');
        $s->execute(['public_id'=>$publicId,'connection_id'=>$connectionId,'calendar_id'=>$calendarId,'summary'=>$calendar['summary']??null,'timezone'=>$calendar['timeZone']??null,'direction'=>'google_to_crm','primary'=>$primary,'created_at'=>$now,'updated_at'=>$now]);
        $find->execute(['connection_id'=>$connectionId,'calendar_id'=>$calendarId]); return $find->fetch(PDO::FETCH_ASSOC) ?: [];
    }

    public function updateSource(int $sourceId, array $values): void
    {
        $allowed = ['direction','is_enabled','sync_token','watch_channel_id','watch_resource_id','watch_expiration','watch_token_encrypted','last_sync_at','last_error']; $set=[]; $params=['id'=>$sourceId];
        foreach($values as $key=>$value){if(in_array($key,$allowed,true)){$set[]=$key.' = :'.$key;$params[$key]=$value;}}
        if($set===[])return; $set[]='updated_at=:updated_at';$params['updated_at']=gmdate('Y-m-d H:i:s');
        $this->pdo->prepare('UPDATE google_calendar_sources SET '.implode(',',$set).' WHERE id=:id')->execute($params);
    }

    public function sourceByChannelId(string $channelId): ?array
    {
        $s=$this->pdo->prepare('SELECT s.*,c.user_id FROM google_calendar_sources s JOIN google_calendar_connections c ON c.id=s.connection_id WHERE s.watch_channel_id=:channel_id LIMIT 1');
        $s->execute(['channel_id'=>$channelId]); return $s->fetch(PDO::FETCH_ASSOC)?:null;
    }

    public function sourceForUser(string $publicId, int $userId): ?array
    {
        $s=$this->pdo->prepare('SELECT s.*,c.user_id,c.public_id AS connection_public_id FROM google_calendar_sources s JOIN google_calendar_connections c ON c.id=s.connection_id WHERE s.public_id=:public_id AND c.user_id=:user_id LIMIT 1');$s->execute(['public_id'=>$publicId,'user_id'=>$userId]);return$s->fetch(PDO::FETCH_ASSOC)?:null;
    }

    public function event(int $sourceId, string $googleId): ?array
    {
        $s=$this->pdo->prepare('SELECT * FROM google_calendar_events WHERE source_id=:source_id AND google_event_id=:google_event_id LIMIT 1');$s->execute(['source_id'=>$sourceId,'google_event_id'=>$googleId]);return$s->fetch(PDO::FETCH_ASSOC)?:null;
    }

    public function insertEvent(int $sourceId,string $googleId,?string $crmId,array $values=[]):array
    {
        $now=gmdate('Y-m-d H:i:s');$id=Ulid::generate('gev');$s=$this->pdo->prepare('INSERT INTO google_calendar_events (public_id,source_id,google_event_id,crm_event_public_id,recurring_event_id,etag,google_updated_at,is_all_day,all_day_start,all_day_end,last_synced_at,status,last_error,created_at,updated_at) VALUES (:public_id,:source_id,:google_event_id,:crm_event_public_id,:recurring_event_id,:etag,:google_updated_at,:is_all_day,:all_day_start,:all_day_end,:last_synced_at,:status,NULL,:created_at,:updated_at)');$s->execute(['public_id'=>$id,'source_id'=>$sourceId,'google_event_id'=>$googleId,'crm_event_public_id'=>$crmId,'recurring_event_id'=>$values['recurring_event_id']??null,'etag'=>$values['etag']??null,'google_updated_at'=>$values['google_updated_at']??null,'is_all_day'=>!empty($values['is_all_day'])?1:0,'all_day_start'=>$values['all_day_start']??null,'all_day_end'=>$values['all_day_end']??null,'last_synced_at'=>$now,'status'=>$values['status']??'active','created_at'=>$now,'updated_at'=>$now]);return$this->event($sourceId,$googleId)?:[];
    }

    public function updateEventMapping(int $id,array $values):void{$allowed=['crm_event_public_id','recurring_event_id','etag','google_updated_at','is_all_day','all_day_start','all_day_end','last_synced_at','status','last_error'];$set=[];$params=['id'=>$id];foreach($values as $k=>$v){if(in_array($k,$allowed,true)){$set[]=$k.'=:'.$k;$params[$k]=$v;}}if($set===[])return;$set[]='updated_at=:updated_at';$params['updated_at']=gmdate('Y-m-d H:i:s');$this->pdo->prepare('UPDATE google_calendar_events SET '.implode(',',$set).' WHERE id=:id')->execute($params);}
    public function deleteEventMapping(int $id):void{$this->pdo->prepare('DELETE FROM google_calendar_events WHERE id=:id')->execute(['id'=>$id]);}
    public function mappings(int $sourceId):array{$s=$this->pdo->prepare('SELECT * FROM google_calendar_events WHERE source_id=:source_id');$s->execute(['source_id'=>$sourceId]);return$s->fetchAll(PDO::FETCH_ASSOC)?:[];}

    public function calendarEvent(string $publicId):?array{$s=$this->pdo->prepare('SELECT * FROM calendar_events WHERE public_id=:public_id LIMIT 1');$s->execute(['public_id'=>$publicId]);return$s->fetch(PDO::FETCH_ASSOC)?:null;}
    public function createCalendarEvent(int $ownerId,array $data):string{$id=Ulid::generate('evt');$now=gmdate('Y-m-d H:i:s');$s=$this->pdo->prepare('INSERT INTO calendar_events (public_id,title,description,starts_at,ends_at,owner_user_id,source_type,source_owner_user_id,source_external_id,created_at,updated_at) VALUES (:public_id,:title,:description,:starts_at,:ends_at,:owner_user_id,:source_type,:source_owner_user_id,:source_external_id,:created_at,:updated_at)');$s->execute(['public_id'=>$id,'title'=>$data['title'],'description'=>$data['description'],'starts_at'=>$data['starts_at'],'ends_at'=>$data['ends_at'],'owner_user_id'=>$ownerId,'source_type'=>'google_calendar','source_owner_user_id'=>$ownerId,'source_external_id'=>$data['google_event_id']??null,'created_at'=>$now,'updated_at'=>$now]);return$id;}
    public function updateCalendarEvent(string $publicId,array $data,?int $ownerId=null):void{$sql='UPDATE calendar_events SET title=:title,description=:description,starts_at=:starts_at,ends_at=:ends_at,source_type=:source_type,source_owner_user_id=:source_owner_user_id,source_external_id=:source_external_id,updated_at=:updated_at WHERE public_id=:public_id';$p=['title'=>$data['title'],'description'=>$data['description'],'starts_at'=>$data['starts_at'],'ends_at'=>$data['ends_at'],'source_type'=>'google_calendar','source_owner_user_id'=>$ownerId,'source_external_id'=>$data['google_event_id']??null,'updated_at'=>gmdate('Y-m-d H:i:s'),'public_id'=>$publicId];if($ownerId!==null){$sql.=' AND owner_user_id=:owner_user_id';$p['owner_user_id']=$ownerId;}$this->pdo->prepare($sql)->execute($p);}
    public function deleteCalendarEvent(string $publicId,?int $ownerId=null):void{$sql='DELETE FROM calendar_events WHERE public_id=:public_id';$p=['public_id'=>$publicId];if($ownerId!==null){$sql.=' AND owner_user_id=:owner_user_id';$p['owner_user_id']=$ownerId;}$this->pdo->prepare($sql)->execute($p);}
    public function localEventsForUser(int $userId,?string $after):array{$sql="SELECT e.* FROM calendar_events e WHERE e.owner_user_id=:user_id AND (e.source_type IS NULL OR e.source_type <> 'google_calendar') AND NOT EXISTS (SELECT 1 FROM google_calendar_events ge WHERE ge.crm_event_public_id=e.public_id)";$p=['user_id'=>$userId];if($after){$sql.=' AND (e.updated_at > :after OR e.source_type = :orphan_source)';$p['after']=$after;$p['orphan_source']='google_calendar';}$sql.=' ORDER BY e.updated_at ASC LIMIT 500';$s=$this->pdo->prepare($sql);$s->execute($p);return$s->fetchAll(PDO::FETCH_ASSOC)?:[];}
}
