<?php
declare(strict_types=1);

namespace Module\Crm\KaitenMigration\Service;

use Api\System\Library\Container;
use Module\Crm\KaitenMigration\Repository\KaitenMigrationRepository;
use RuntimeException;

final class KaitenTargetWriter
{
    public function __construct(private readonly Container $container, private readonly KaitenMigrationRepository $repo, private readonly KaitenClient $client) {}
    public function service(string $id): mixed { return $this->container->get($id); }

    public function space(array $job,array $payload,array $actor): array
    {
        $source=$this->id($payload);$workspace=(string)$job['workspace_gid'];$mapping=$this->repo->findMapping((int)$job['connection_id'],$workspace,'space',$source);$warnings=[];
        if($mapping&& !empty($mapping['target_public_id'])){ $existing=$this->service('service.project')->get((string)$mapping['target_public_id'],$actor); if($existing){ if(($job['mode']??'import')!=='sync')return $this->result('project',(string)$mapping['target_public_id'],'skipped');$updated=$this->service('service.project')->update((string)$mapping['target_public_id'],['title'=>$this->title($payload),'description'=>$this->description($payload)],$actor);return $this->result('project',(string)$mapping['target_public_id'],is_array($updated)?'updated':'warning',is_array($updated)?[]:['Space update failed.']); }$warnings[]='Stored space mapping no longer resolves.'; }
        $created=$this->service('service.project')->create(['title'=>$this->title($payload),'description'=>$this->description($payload),'status'=>'active','priority'=>'normal','task_key_prefix'=>'KA'.strtoupper(substr(hash('sha256',$source),0,4))],$actor);
        if(!is_array($created)||empty($created['public_id']))throw new RuntimeException('KAITEN_SPACE_CREATE_FAILED');return $this->result('project',(string)$created['public_id'],'imported',$warnings);
    }

    public function board(array $job,array $payload,array $actor): array
    {
        $source=$this->id($payload);$workspace=(string)$job['workspace_gid'];$spaceId=(string)($payload['__space_id']??$payload['space_id']??$payload['spaceId']??'');$space=$this->repo->findMapping((int)$job['connection_id'],$workspace,'space',$spaceId);if(!$space||empty($space['target_public_id']))throw new RuntimeException('KAITEN_BOARD_SPACE_NOT_READY');
        $mapping=$this->repo->findMapping((int)$job['connection_id'],$workspace,'board',$source);if($mapping&& !empty($mapping['target_public_id']))return $this->result('project_module',(string)$mapping['target_public_id'],'skipped');
        try{$created=$this->service('service.project_module')->create(['project_public_id'=>(string)$space['target_public_id'],'title'=>$this->title($payload),'description'=>$this->description($payload),'status'=>'planned','sort_order'=>(int)($payload['sort_order']??$payload['position']??0)],$actor);if(is_array($created)&&!empty($created['public_id']))return $this->result('project_module',(string)$created['public_id'],'imported');}catch(\Throwable){}
        return $this->result('project',(string)$space['target_public_id'],'reused',['Board preserved in source payload; project modules are unavailable.']);
    }

    public function column(array $job,array $payload,array $actor): array
    {
        $source=$this->id($payload);$workspace=(string)$job['workspace_gid'];$mapping=$this->repo->findMapping((int)$job['connection_id'],$workspace,'column',$source);if($mapping&& !empty($mapping['target_public_id']))return $this->result('status',(string)$mapping['target_public_id'],'skipped');
        $code='kaiten_'.substr(hash('sha256',$workspace.':'.$source),0,24);$created=$this->service('service.status')->create(['scope'=>'task','code'=>$code,'title'=>$this->title($payload),'color'=>$this->color((string)($payload['color']??'')),'sort_order'=>(int)($payload['sort_order']??$payload['position']??0)]);
        if($created==='STATUS_CODE_EXISTS'){$list=$this->service('service.status')->list(['scope'=>'task','search'=>$code,'limit'=>5]);$created=$list['items'][0]??null;}
        if(!is_array($created)||empty($created['public_id']))throw new RuntimeException('KAITEN_COLUMN_STATUS_FAILED');return $this->result('status',(string)$created['public_id'],'imported');
    }

    public function tag(array $job,array $payload): array
    {
        $source=$this->id($payload);$workspace=(string)$job['workspace_gid'];$mapping=$this->repo->findMapping((int)$job['connection_id'],$workspace,'tag',$source);if($mapping&& !empty($mapping['target_public_id']))return $this->result('tag',(string)$mapping['target_public_id'],'skipped');
        $code='kaiten_'.substr(hash('sha256',$workspace.':'.$source),0,24);$created=$this->service('service.tag')->create(['code'=>$code,'title'=>$this->title($payload),'color'=>$this->color((string)($payload['color']??'')),'description'=>'Imported from Kaiten tag '.$source]);
        if($created==='TAG_CODE_EXISTS'){$list=$this->service('service.tag')->list(['search'=>$code,'limit'=>5]);$created=$list['items'][0]??null;}if(!is_array($created)||empty($created['public_id']))throw new RuntimeException('KAITEN_TAG_CREATE_FAILED');return $this->result('tag',(string)$created['public_id'],'imported');
    }

    public function customField(array $job,array $payload): array { return $this->result('custom_field','', 'skipped',['Kaiten custom property was retained in source payload because CRM has no generic custom-field write contract.']); }

    public function card(array $job,array $payload,array $actor): array
    {
        $source=$this->id($payload);$workspace=(string)$job['workspace_gid'];$mapping=$this->repo->findMapping((int)$job['connection_id'],$workspace,'card',$source);$warnings=[];
        $spaceId=(string)($payload['__space_id']??$payload['space_id']??$payload['spaceId']??'');$space=$this->repo->findMapping((int)$job['connection_id'],$workspace,'space',$spaceId);if(!$space||empty($space['target_public_id']))throw new RuntimeException('KAITEN_CARD_SPACE_NOT_READY');
        $assignee=$this->assignee($job,$payload);$status='new';$columnId=(string)($payload['column_id']??$payload['columnId']??'');if($columnId!==''){ $column=$this->repo->findMapping((int)$job['connection_id'],$workspace,'column',$columnId);if($column&&!empty($column['target_public_id'])){try{$row=$this->service('service.status')->get((string)$column['target_public_id']);if(is_array($row))$status=(string)($row['code']??'new');}catch(\Throwable){$warnings[]='Column status could not be resolved.';}} }
        if(!empty($payload['state'])&&in_array((string)$payload['state'],['3','done','completed'],true))$status='completed';
        $input=['project_public_id'=>(string)$space['target_public_id'],'title'=>$this->title($payload),'description'=>$this->cardDescription($payload),'status'=>$status,'priority'=>$this->priority($payload),'due_at'=>$this->date((string)($payload['due_date']??$payload['due_at']??$payload['dueDate']??'')),'start_at'=>$this->date((string)($payload['start_date']??$payload['start_at']??$payload['startDate']??'')),'assignee_user_id'=>$assignee,'source_type'=>'kaiten','source_id'=>$source,'source_url'=>(string)($payload['url']??$payload['permalink']??''),'source_payload_json'=>$payload,'created_at'=>$this->date((string)($payload['created_at']??$payload['createdAt']??'')),'updated_at'=>$this->date((string)($payload['updated_at']??$payload['updatedAt']??''))];
        $parent=$this->parent($payload);if($parent!==''){ $parentMap=$this->repo->findMapping((int)$job['connection_id'],$workspace,'card',$parent);if(!empty($parentMap['target_public_id']))$input['parent_task_public_id']=(string)$parentMap['target_public_id'];else$warnings[]='Parent card mapping is not ready.'; }
        $task=$this->service('service.task');
        if($mapping&& !empty($mapping['target_public_id'])){$target=(string)$mapping['target_public_id'];if(($job['mode']??'import')==='sync'){ $updated=$task->update($target,$input,(int)($actor['id']??0),$actor);if(!is_array($updated))$warnings[]='Card update failed.';$state='updated';}else$state='skipped';}else{$created=$task->create($input,$actor);if(!is_array($created)||empty($created['public_id']))throw new RuntimeException('KAITEN_CARD_CREATE_FAILED');$target=(string)$created['public_id'];$state='imported';}
        foreach((array)($payload['tags']??$payload['tag_ids']??[]) as $tag){$tagId=is_array($tag)?$this->id($tag):(string)$tag;$tagMap=$this->repo->findMapping((int)$job['connection_id'],$workspace,'tag',$tagId);if($tagMap&&!empty($tagMap['target_public_id']))try{$this->service('service.tag')->attachToTask($target,(string)$tagMap['target_public_id'],$actor); }catch(\Throwable){$warnings[]='Tag attachment failed.';}}
        return $this->result('task',$target,$state,$warnings);
    }

    public function comment(array $job,array $payload,array $actor): array
    {
        $source=$this->id($payload);$workspace=(string)$job['workspace_gid'];$mapping=$this->repo->findMapping((int)$job['connection_id'],$workspace,'comment',$source);if($mapping&& !empty($mapping['target_public_id']))return $this->result('comment',(string)$mapping['target_public_id'],'skipped');$card=(string)($payload['__card_id']??$payload['card_id']??'');$task=$this->repo->findMapping((int)$job['connection_id'],$workspace,'card',$card);if(!$task||empty($task['target_public_id']))throw new RuntimeException('KAITEN_COMMENT_CARD_NOT_READY');$body=(string)($payload['text']??$payload['body']??$payload['content']??'');if(trim($body)==='')return $this->result('comment','', 'skipped',['Empty comment skipped.']);$created=$this->service('service.comment')->createByTaskImported((string)$task['target_public_id'],['body'=>$body,'visibility'=>'internal','author_user_id'=>$this->author($job,$payload,(int)($actor['id']??0)),'created_at'=>$this->date((string)($payload['created_at']??$payload['createdAt']??''))],(int)($actor['id']??0));if(!is_array($created)||empty($created['public_id']))throw new RuntimeException('KAITEN_COMMENT_CREATE_FAILED');return $this->result('comment',(string)$created['public_id'],'imported');
    }

    public function attachment(array $job,array $payload,array $actor,string $token,int $maxBytes): array
    {
        $source=$this->id($payload);$workspace=(string)$job['workspace_gid'];$mapping=$this->repo->findMapping((int)$job['connection_id'],$workspace,'attachment',$source);if($mapping&& !empty($mapping['target_public_id']))return $this->result('file',(string)$mapping['target_public_id'],'skipped');$card=(string)($payload['__card_id']??$payload['card_id']??'');$task=$this->repo->findMapping((int)$job['connection_id'],$workspace,'card',$card);if(!$task||empty($task['target_public_id']))throw new RuntimeException('KAITEN_ATTACHMENT_CARD_NOT_READY');$url=trim((string)($payload['download_url']??$payload['url']??$payload['content_url']??''));if($url==='')return $this->result('file','','skipped',['Attachment has no downloadable URL.']);$download=$this->client->downloadAttachment($token,$url,$maxBytes);try{$bytes=file_get_contents((string)$download['path']);if(!is_string($bytes))throw new RuntimeException('KAITEN_ATTACHMENT_READ_FAILED');$file=$this->service('service.file')->create(['entity_type'=>'task','entity_public_id'=>(string)$task['target_public_id'],'name'=>trim((string)($payload['name']??'kaiten-attachment.bin')),'mime_type'=>(string)$download['mime_type'],'content_base64'=>base64_encode($bytes)],[],(int)($actor['id']??0),$actor);if(!is_array($file)||empty($file['public_id']))throw new RuntimeException('KAITEN_FILE_CREATE_FAILED');return $this->result('file',(string)$file['public_id'],'imported');}finally{@unlink((string)$download['path']);}
    }

    public function history(array $job,array $payload,array $actor): array { return $this->result('history','skipped',['History is retained in job payload; CRM audit events are not written automatically.']); }

    private function result(string $type,string $id,string $state,array $warnings=[]): array { return ['target_type'=>$type,'target_public_id'=>$id,'state'=>$state,'warnings'=>$warnings]; }
    private function id(array $p): string { return trim((string)($p['id']??$p['uid']??$p['uuid']??'')); }
    private function parent(array $p): string { return trim((string)($p['parent_id']??$p['parentId']??'')); }
    private function title(array $p): string { return trim((string)($p['name']??$p['title']??'Kaiten item'))?:'Kaiten item'; }
    private function description(array $p): string { return trim((string)($p['description']??$p['desc']??'')); }
    private function cardDescription(array $p): string { $base=$this->description($p);$properties=(array)($p['custom_properties']??$p['customProperties']??[]);$lines=[];foreach($properties as $key=>$value){if(is_array($value))$value=json_encode($value,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);if((string)$value!=='')$lines[]='- '.(string)$key.': '.(string)$value;}return $lines===[]?$base:trim($base."\n\nПоля Kaiten:\n".implode("\n",$lines)); }
    private function priority(array $p): string { $value=strtolower((string)($p['priority']??$p['priority_name']??''));return match($value){ 'critical','urgent','highest'=>'urgent','high'=>'high','low','lowest'=>'low',default=>'normal'}; }
    private function color(string $value): string { $value=trim($value);return preg_match('/^#[0-9a-f]{6}$/i',$value)?$value:'#64748b'; }
    private function date(string $value): ?string { if($value==='')return null;$time=strtotime($value);return $time===false?null:gmdate('Y-m-d H:i:s',$time); }
    private function assignee(array $job,array $p): ?int { $id=(string)($p['responsible_id']??$p['responsibleId']??'');if($id===''){foreach((array)($p['members']??$p['owners']??[]) as $member){$id=$this->id(is_array($member)?$member:['id'=>$member]);if($id!=='')break;}}return$id===''?null:$this->repo->mappedUserId((int)$job['connection_id'],$id); }
    private function author(array $job,array $p,int $fallback): int { $author=$p['created_by']??$p['author']??null;$id=is_array($author)?$this->id($author):(string)$author;return$id!==''?($this->repo->mappedUserId((int)$job['connection_id'],$id)??$fallback):$fallback; }
}
