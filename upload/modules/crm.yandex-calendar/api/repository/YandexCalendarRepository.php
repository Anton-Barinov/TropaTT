<?php
declare(strict_types=1);

namespace Module\Crm\YandexCalendar\Repository;

use Api\System\Library\Support\Ulid;
use PDO;

final class YandexCalendarRepository
{
    public function __construct(private readonly PDO $pdo) {}

    public function connectionById(int $id): ?array
    {
        $s = $this->pdo->prepare('SELECT * FROM yandex_calendar_connections WHERE id = :id LIMIT 1');
        $s->execute(['id' => $id]);
        return $s->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function connectionForUser(int $userId): ?array
    {
        $s = $this->pdo->prepare('SELECT * FROM yandex_calendar_connections WHERE user_id = :user_id LIMIT 1');
        $s->execute(['user_id' => $userId]);
        return $s->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function listConnectionsForUser(int $userId): array
    {
        $s = $this->pdo->prepare('SELECT id,public_id,user_id,account_email,auth_mode,status,last_error,last_sync_at,created_at,updated_at FROM yandex_calendar_connections WHERE user_id = :user_id ORDER BY id DESC');
        $s->execute(['user_id' => $userId]);
        return $s->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function activeConnections(): array
    {
        return $this->pdo->query("SELECT c.* FROM yandex_calendar_connections c JOIN users u ON u.id = c.user_id WHERE u.is_active = 1 AND u.deleted_at IS NULL AND c.status IN ('active','sync_warning') ORDER BY c.id ASC")->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function orphanedConnections(): array
    {
        return $this->pdo->query("SELECT c.id,c.user_id FROM yandex_calendar_connections c LEFT JOIN users u ON u.id = c.user_id WHERE u.id IS NULL OR u.deleted_at IS NOT NULL ORDER BY c.id ASC")->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function createConnection(int $userId, string $email, string $username, string $credential): array
    {
        $now = gmdate('Y-m-d H:i:s');
        $s = $this->pdo->prepare('INSERT INTO yandex_calendar_connections (public_id,user_id,account_email,caldav_username,credential_encrypted,auth_mode,status,created_at,updated_at) VALUES (:public_id,:user_id,:email,:username,:credential,:auth_mode,:status,:created_at,:updated_at)');
        $s->execute(['public_id' => Ulid::generate('ycal'), 'user_id' => $userId, 'email' => $email, 'username' => $username, 'credential' => $credential, 'auth_mode' => 'app_password', 'status' => 'active', 'created_at' => $now, 'updated_at' => $now]);
        return $this->connectionForUser($userId) ?? [];
    }

    public function updateConnection(int $id, array $values): void
    {
        $allowed = ['account_email','caldav_username','credential_encrypted','status','last_error','last_sync_at'];
        $set = [];
        $params = ['id' => $id];
        foreach ($values as $key => $value) {
            if (in_array($key, $allowed, true)) { $set[] = $key . ' = :' . $key; $params[$key] = $value; }
        }
        if ($set === []) return;
        $set[] = 'updated_at = :updated_at'; $params['updated_at'] = gmdate('Y-m-d H:i:s');
        $this->pdo->prepare('UPDATE yandex_calendar_connections SET ' . implode(',', $set) . ' WHERE id = :id')->execute($params);
    }

    public function deleteConnection(int $id, int $ownerId): void
    {
        $ownTransaction = !$this->pdo->inTransaction();
        if ($ownTransaction) $this->pdo->beginTransaction();
        try {
            $s = $this->pdo->prepare('SELECT id FROM yandex_calendar_sources WHERE connection_id = :id');
            $s->execute(['id' => $id]);
            $sourceIds = array_map('intval', $s->fetchAll(PDO::FETCH_COLUMN) ?: []);
            foreach ($sourceIds as $sourceId) {
                foreach ($this->mappings($sourceId) as $mapping) {
                    if (!empty($mapping['crm_event_public_id'])) $this->deleteCalendarEvent((string)$mapping['crm_event_public_id'], $ownerId);
                }
            }
            if ($sourceIds !== []) {
                $ph = implode(',', array_fill(0, count($sourceIds), '?'));
                $this->pdo->prepare('DELETE FROM yandex_calendar_events WHERE source_id IN (' . $ph . ')')->execute($sourceIds);
            }
            $this->pdo->prepare('DELETE FROM yandex_calendar_sources WHERE connection_id = :id')->execute(['id' => $id]);
            $this->pdo->prepare('DELETE FROM yandex_calendar_connections WHERE id = :id AND user_id = :user_id')->execute(['id' => $id, 'user_id' => $ownerId]);
            if ($ownTransaction) $this->pdo->commit();
        } catch (\Throwable $e) {
            if ($ownTransaction && $this->pdo->inTransaction()) $this->pdo->rollBack();
            throw $e;
        }
    }

    public function sourceById(int $id): ?array
    {
        $s = $this->pdo->prepare('SELECT * FROM yandex_calendar_sources WHERE id = :id LIMIT 1');
        $s->execute(['id' => $id]);
        return $s->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function sourceForUser(string $publicId, int $userId): ?array
    {
        $s = $this->pdo->prepare('SELECT s.*,c.user_id,c.public_id AS connection_public_id FROM yandex_calendar_sources s JOIN yandex_calendar_connections c ON c.id=s.connection_id WHERE s.public_id=:public_id AND c.user_id=:user_id LIMIT 1');
        $s->execute(['public_id' => $publicId, 'user_id' => $userId]);
        return $s->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function sources(int $connectionId): array
    {
        $s = $this->pdo->prepare('SELECT * FROM yandex_calendar_sources WHERE connection_id=:id AND is_enabled=1 ORDER BY is_primary DESC,id ASC');
        $s->execute(['id' => $connectionId]);
        return $s->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function allSources(int $connectionId): array
    {
        $s = $this->pdo->prepare('SELECT * FROM yandex_calendar_sources WHERE connection_id=:id ORDER BY is_primary DESC,id ASC');
        $s->execute(['id' => $connectionId]);
        return $s->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function upsertSource(int $connectionId, array $calendar): array
    {
        $href = trim((string)($calendar['href'] ?? ''));
        if ($href === '' || strlen($href) > 512) {
            throw new \RuntimeException('YANDEX_CALENDAR_URL_TOO_LONG');
        }
        $find = $this->pdo->prepare('SELECT * FROM yandex_calendar_sources WHERE connection_id=:connection_id AND calendar_href=:href LIMIT 1');
        $find->execute(['connection_id' => $connectionId, 'href' => $href]);
        $row = $find->fetch(PDO::FETCH_ASSOC);
        $now = gmdate('Y-m-d H:i:s');
        if ($row) {
            $this->pdo->prepare('UPDATE yandex_calendar_sources SET display_name=:display_name,timezone=:timezone,ctag=:ctag,last_error=NULL,updated_at=:updated_at WHERE id=:id')->execute(['display_name' => $calendar['display_name'] ?? null, 'timezone' => $calendar['timezone'] ?? null, 'ctag' => $calendar['ctag'] ?? null, 'updated_at' => $now, 'id' => $row['id']]);
            return array_merge($row, ['display_name' => $calendar['display_name'] ?? null, 'timezone' => $calendar['timezone'] ?? null, 'ctag' => $calendar['ctag'] ?? null, 'last_error' => null]);
        }
        $primary = (int)$this->pdo->query('SELECT COUNT(*) FROM yandex_calendar_sources WHERE connection_id=' . (int)$connectionId)->fetchColumn() === 0 ? 1 : 0;
        $this->pdo->prepare('INSERT INTO yandex_calendar_sources (public_id,connection_id,calendar_href,display_name,timezone,ctag,is_primary,created_at,updated_at) VALUES (:public_id,:connection_id,:href,:display_name,:timezone,:ctag,:primary,:created_at,:updated_at)')->execute(['public_id' => Ulid::generate('ysrc'), 'connection_id' => $connectionId, 'href' => $href, 'display_name' => $calendar['display_name'] ?? null, 'timezone' => $calendar['timezone'] ?? null, 'ctag' => $calendar['ctag'] ?? null, 'primary' => $primary, 'created_at' => $now, 'updated_at' => $now]);
        $find->execute(['connection_id' => $connectionId, 'href' => $href]);
        return $find->fetch(PDO::FETCH_ASSOC) ?: [];
    }

    public function disableMissingSources(int $connectionId, array $hrefs): void
    {
        if ($hrefs === []) return;
        $params = ['connection_id' => $connectionId, 'error' => 'Calendar is no longer accessible', 'updated_at' => gmdate('Y-m-d H:i:s')];
        $placeholders = [];
        foreach (array_values($hrefs) as $i => $href) { $key = 'href_' . $i; $placeholders[] = ':' . $key; $params[$key] = $href; }
        $this->pdo->prepare('UPDATE yandex_calendar_sources SET is_enabled=0,last_error=:error,updated_at=:updated_at WHERE connection_id=:connection_id AND calendar_href NOT IN (' . implode(',', $placeholders) . ') AND last_error IS NULL')->execute($params);
    }

    public function updateSource(int $id, array $values): void
    {
        $allowed = ['direction','is_enabled','ctag','last_sync_at','last_error']; $set=[]; $params=['id'=>$id];
        foreach ($values as $key=>$value) if (in_array($key,$allowed,true)) { $set[]=$key.'=:'.$key; $params[$key]=$value; }
        if ($set===[]) return;
        $set[]='updated_at=:updated_at'; $params['updated_at']=gmdate('Y-m-d H:i:s');
        $this->pdo->prepare('UPDATE yandex_calendar_sources SET '.implode(',',$set).' WHERE id=:id')->execute($params);
    }

    public function eventByKey(int $sourceId, string $uid, ?string $recurrenceId): ?array
    {
        $sql = 'SELECT * FROM yandex_calendar_events WHERE source_id=:source_id AND external_uid=:uid AND ' . ($recurrenceId === null ? 'recurrence_id IS NULL' : 'recurrence_id=:recurrence_id') . ' LIMIT 1';
        $params = ['source_id' => $sourceId, 'uid' => $uid];
        if ($recurrenceId !== null) $params['recurrence_id'] = $recurrenceId;
        $s=$this->pdo->prepare($sql); $s->execute($params); return $s->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function mappings(int $sourceId): array
    {
        $s=$this->pdo->prepare('SELECT * FROM yandex_calendar_events WHERE source_id=:source_id'); $s->execute(['source_id'=>$sourceId]); return $s->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function insertEvent(int $sourceId, array $values): array
    {
        $now=gmdate('Y-m-d H:i:s');
        $this->pdo->prepare('INSERT INTO yandex_calendar_events (public_id,source_id,external_uid,recurrence_id,event_href,etag,recurrence_rule,event_start,event_end,is_all_day,all_day_start,all_day_end,crm_event_public_id,last_synced_at,status,created_at,updated_at) VALUES (:public_id,:source_id,:uid,:recurrence_id,:href,:etag,:rrule,:event_start,:event_end,:is_all_day,:all_day_start,:all_day_end,:crm_id,:last_synced_at,:status,:created_at,:updated_at)')->execute(['public_id'=>Ulid::generate('yevt'),'source_id'=>$sourceId,'uid'=>$values['external_uid'],'recurrence_id'=>$values['recurrence_id']??null,'href'=>$values['event_href']??null,'etag'=>$values['etag']??null,'rrule'=>$values['recurrence_rule']??null,'event_start'=>$values['event_start']??null,'event_end'=>$values['event_end']??null,'is_all_day'=>!empty($values['is_all_day'])?1:0,'all_day_start'=>$values['all_day_start']??null,'all_day_end'=>$values['all_day_end']??null,'crm_id'=>$values['crm_event_public_id']??null,'last_synced_at'=>$now,'status'=>$values['status']??'active','created_at'=>$now,'updated_at'=>$now]);
        return $this->eventByKey($sourceId,(string)$values['external_uid'],$values['recurrence_id']??null) ?: [];
    }

    public function updateEventMapping(int $id, array $values): void
    {
        $allowed=['event_href','etag','recurrence_rule','event_start','event_end','is_all_day','all_day_start','all_day_end','crm_event_public_id','last_synced_at','status','last_error'];$set=[];$params=['id'=>$id];
        foreach($values as $key=>$value) if(in_array($key,$allowed,true)){$set[]=$key.'=:'.$key;$params[$key]=$value;}
        if($set===[])return; $set[]='updated_at=:updated_at';$params['updated_at']=gmdate('Y-m-d H:i:s');$this->pdo->prepare('UPDATE yandex_calendar_events SET '.implode(',',$set).' WHERE id=:id')->execute($params);
    }

    public function deleteEventMapping(int $id): void { $this->pdo->prepare('DELETE FROM yandex_calendar_events WHERE id=:id')->execute(['id'=>$id]); }

    public function calendarEvent(string $publicId): ?array { $s=$this->pdo->prepare('SELECT * FROM calendar_events WHERE public_id=:public_id LIMIT 1');$s->execute(['public_id'=>$publicId]);return$s->fetch(PDO::FETCH_ASSOC)?:null; }
    public function createCalendarEvent(int $ownerId,array $data): string { $id=Ulid::generate('evt');$now=gmdate('Y-m-d H:i:s');$this->pdo->prepare('INSERT INTO calendar_events (public_id,title,description,starts_at,ends_at,owner_user_id,source_type,source_owner_user_id,source_external_id,created_at,updated_at) VALUES (:public_id,:title,:description,:starts_at,:ends_at,:owner,:source_type,:source_owner,:external_id,:created_at,:updated_at)')->execute(['public_id'=>$id,'title'=>$data['title'],'description'=>$data['description'],'starts_at'=>$data['starts_at'],'ends_at'=>$data['ends_at'],'owner'=>$ownerId,'source_type'=>'yandex_calendar','source_owner'=>$ownerId,'external_id'=>$data['external_id']??null,'created_at'=>$now,'updated_at'=>$now]);return$id; }
    public function updateCalendarEvent(string $publicId,array $data,int $ownerId): void { $this->pdo->prepare('UPDATE calendar_events SET title=:title,description=:description,starts_at=:starts_at,ends_at=:ends_at,source_type=:source_type,source_owner_user_id=:source_owner,source_external_id=:external_id,updated_at=:updated_at WHERE public_id=:public_id AND owner_user_id=:owner')->execute(['title'=>$data['title'],'description'=>$data['description'],'starts_at'=>$data['starts_at'],'ends_at'=>$data['ends_at'],'source_type'=>'yandex_calendar','source_owner'=>$ownerId,'owner'=>$ownerId,'external_id'=>$data['external_id']??null,'updated_at'=>gmdate('Y-m-d H:i:s'),'public_id'=>$publicId]); }
    public function deleteCalendarEvent(string $publicId,int $ownerId): void { $this->pdo->prepare('DELETE FROM calendar_events WHERE public_id=:public_id AND owner_user_id=:owner')->execute(['public_id'=>$publicId,'owner'=>$ownerId]); }
    public function localEventsForUser(int $userId,?string $after): array { $sql="SELECT e.* FROM calendar_events e WHERE e.owner_user_id=:user_id AND (e.source_type IS NULL OR e.source_type <> 'yandex_calendar') AND NOT EXISTS (SELECT 1 FROM yandex_calendar_events ye WHERE ye.crm_event_public_id=e.public_id)";$p=['user_id'=>$userId];if($after!==null&&$after!==''){$sql.=' AND e.updated_at > :after';$p['after']=$after;}$sql.=' ORDER BY e.updated_at ASC LIMIT 500';$s=$this->pdo->prepare($sql);$s->execute($p);return$s->fetchAll(PDO::FETCH_ASSOC)?:[]; }
}
