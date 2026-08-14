<?php
declare(strict_types=1);

namespace Module\Crm\ShtabMigration\Service;

use Api\System\Library\Container;
use Module\Crm\ShtabMigration\Repository\ShtabMigrationRepository;
use RuntimeException;

final class ShtabTargetWriter
{
    public function __construct(private readonly Container $container, private readonly ShtabMigrationRepository $repo) {}
    public function service(string $id): mixed { return $this->container->get($id); }
    private function sourceId(array $p): string { $id=trim((string)($p['_source_id']??''));return $id!==''?$id:trim((string)($p['id']??'')); }
    private function map(array $job,string $type,string $id): ?array { return $id===''?null:$this->repo->findMapping((int)$job['connection_id'],$type,$id); }
    private function mapRaw(array $job,string $type,?string $raw): ?array { $raw=trim((string)$raw);if($raw==='')return null;if($type==='task'&&str_starts_with($raw,'subtask:'))$raw='task:'.substr($raw,8);return $this->map($job,$type,str_starts_with($raw,$type.':')?$raw:$type.':'.$raw); }
    private function title(array $p,string $fallback): string { return trim((string)($p['name']??$p['title']??$p['subject']??$fallback))?:$fallback; }
    private function date(mixed $value): ?string { $v=trim((string)$value);if($v==='')return null;$ts=strtotime($v);return$ts===false?null:gmdate('Y-m-d H:i:s',$ts); }
    private function result(string $type,string $id,string $state,array $warnings=[]): array { return ['target_type'=>$type,'target_public_id'=>$id,'state'=>$state,'warnings'=>$warnings]; }

    public function project(array $job,array $p,array $actor): array
    {
        $source=$this->sourceId($p);$mapping=$this->map($job,'project',$source);$service=$this->service('service.project');$warnings=[];
        if($mapping&&!empty($mapping['target_public_id'])){ $existing=$service->get((string)$mapping['target_public_id'],$actor);if($existing&&($job['mode']??'import')!=='sync')return$this->result('project',(string)$mapping['target_public_id'],'skipped');if($existing){$updated=$service->update((string)$mapping['target_public_id'],['title'=>$this->title($p,'Shtab project'),'description'=>$this->description($p)],$actor);if(!is_array($updated))throw new RuntimeException('SHTAB_PROJECT_UPDATE_FAILED');return$this->result('project',(string)$mapping['target_public_id'],'updated');}}
        $created=null;
        for($attempt=0;$attempt<256;$attempt++){
            $prefix=$this->projectPrefix($job,$source,$attempt);
            if($this->repo->projectKeyPrefixExists($prefix))continue;
            $created=$service->create(['title'=>$this->title($p,'Shtab project'),'description'=>$this->description($p),'status'=>!empty($p['archived'])?'archived':'active','priority'=>'normal','task_key_prefix'=>$prefix],$actor);
            if($created!=='PROJECT_TASK_PREFIX_ALREADY_EXISTS')break;
        }
        if(!is_array($created)||empty($created['public_id']))throw new RuntimeException('SHTAB_PROJECT_CREATE_FAILED');return$this->result('project',(string)$created['public_id'],'imported',$warnings);
    }

    public function tag(array $job,array $p): array
    {
        $source=$this->sourceId($p);$mapping=$this->map($job,'tag',$source);if($mapping&&!empty($mapping['target_public_id']))return$this->result('tag',(string)$mapping['target_public_id'],'skipped');
        $created=$this->service('service.tag')->create(['code'=>'shtab_'.substr(hash('sha256',(string)$job['connection_id'].':'.$source),0,24),'title'=>$this->title($p,'Shtab tag'),'color'=>$this->color((string)($p['color']??'')),'description'=>'Imported from Shtab.app export']);
        if($created==='TAG_CODE_EXISTS'){$list=$this->service('service.tag')->list(['search'=>'shtab_'.substr(hash('sha256',(string)$job['connection_id'].':'.$source),0,24),'limit'=>5]);$created=$list['items'][0]??null;}
        if(!is_array($created)||empty($created['public_id']))throw new RuntimeException('SHTAB_TAG_CREATE_FAILED');return$this->result('tag',(string)$created['public_id'],'imported');
    }

    public function user(array $job,array $p): array { return$this->result('user','', 'skipped',['Shtab user was added to manual CRM mapping; no CRM user was created automatically.']); }

    public function task(array $job,array $p,array $actor): array
    {
        $source=$this->sourceId($p);$mapping=$this->map($job,'task',$source);$project=$this->mapRaw($job,'project',$p['project_id']??$p['project']??$p['project_gid']??'');if(!$project||empty($project['target_public_id']))throw new RuntimeException('SHTAB_TASK_PROJECT_NOT_READY');
        $warnings=[];$assignee=null;$assignees=$this->split($p['assignee_id']??$p['assignee_ids']??$p['assignee']??'');$unmapped=0;foreach($assignees as $user){$candidates=[$user];if(str_starts_with($user,'user:'))$candidates[]=substr($user,5);$mapped=null;foreach(array_unique($candidates) as $candidate){$mapped=$this->repo->mappedUserId((int)$job['connection_id'],$candidate);if($mapped!==null)break;}if($mapped!==null&&$assignee===null)$assignee=$mapped;if($mapped===null)++$unmapped;}if($unmapped>0)$warnings[]=$unmapped.' Shtab assignee(s) have no active CRM mapping.';if(count($assignees)>1&&$assignee!==null)$warnings[]='Multiple Shtab assignees found; CRM preserves the first mapped user.';
        $parentRaw=trim((string)($p['parent_id']??$p['parent_task_id']??''));$parent=$parentRaw!==''?$this->mapRaw($job,'task',$parentRaw):null;if($parentRaw!==''&&empty($parent['target_public_id']))throw new RuntimeException('SHTAB_PARENT_TASK_NOT_READY');
        $input=['project_public_id'=>$project['target_public_id'],'title'=>$this->title($p,'Shtab task'),'description'=>$this->description($p),'status'=>$this->status($p['status']??'new'),'priority'=>$this->priority($p['priority']??'normal'),'due_at'=>$this->date($p['due_at']??$p['due_date']??$p['deadline']??null),'start_at'=>$this->date($p['start_at']??$p['start_date']??null),'assignee_user_id'=>$assignee,'source_type'=>'shtab','source_id'=>$source,'source_url'=>(string)($p['url']??$p['link']??''),'source_payload_json'=>$p,'created_at'=>$this->date($p['created_at']??null),'updated_at'=>$this->date($p['updated_at']??null)];if($parent&&!empty($parent['target_public_id']))$input['parent_task_public_id']=$parent['target_public_id'];
        $service=$this->service('service.task');$target='';$state='imported';
        if($mapping&&!empty($mapping['target_public_id'])){$target=(string)$mapping['target_public_id'];if(($job['mode']??'import')==='sync'){$updated=$service->update($target,$input,(int)($actor['id']??0),$actor);if(!is_array($updated))throw new RuntimeException('SHTAB_TASK_UPDATE_FAILED');$state='updated';}else{$state='skipped';}}
        else{$created=$service->create($input,$actor);if(!is_array($created)||empty($created['public_id']))throw new RuntimeException('SHTAB_TASK_CREATE_FAILED');$target=(string)$created['public_id'];}
        foreach($this->split($p['tags']??$p['labels']??'') as $tag){$tagId=hash('sha256','tag:'.mb_strtolower($tag));$tagMap=$this->map($job,'tag',$tagId);if($tagMap&&!empty($tagMap['target_public_id'])){try{$this->service('service.tag')->attachToTask($target,(string)$tagMap['target_public_id'],$actor);}catch(\Throwable){$warnings[]='A Shtab tag could not be attached.';}}}
        return$this->result('task',$target,$state,$warnings);
    }

    public function comment(array $job,array $p,array $actor): array
    {
        $source=$this->sourceId($p);$mapping=$this->map($job,'comment',$source);if($mapping&&!empty($mapping['target_public_id']))return$this->result('comment',(string)$mapping['target_public_id'],'skipped');$task=$this->mapRaw($job,'task',$p['task_id']??$p['task']??'');if(empty($task['target_public_id']))throw new RuntimeException('SHTAB_COMMENT_TASK_NOT_READY');$body=(string)($p['text']??$p['comment']??$p['body']??$p['description']??'');if(trim($body)==='')return$this->result('comment','', 'skipped',['Empty Shtab comment skipped.']);$authorSource=(string)($p['user_id']??$p['author_id']??'');$author=$this->repo->mappedUserId((int)$job['connection_id'],$authorSource);if($author===null&&str_starts_with($authorSource,'user:'))$author=$this->repo->mappedUserId((int)$job['connection_id'],substr($authorSource,5));$warnings=[];$input=['body'=>$body,'created_at'=>$this->date($p['created_at']??$p['date']??null)];if($author!==null)$input['author_user_id']=$author;else if(!empty($p['user_id']))$warnings[]='Shtab comment author is not mapped; migration actor was used.';$created=$this->service('service.comment')->createByTaskImported((string)$task['target_public_id'],$input,(int)($actor['id']??0));if(!is_array($created)||empty($created['public_id']))throw new RuntimeException('SHTAB_COMMENT_CREATE_FAILED');return$this->result('comment',(string)$created['public_id'],'imported',$warnings);
    }

    public function unsupported(array $job,array $p,string $type): array { return$this->result($type,'','skipped',['Shtab '.$type.' export is preserved in the job report but has no verified CRM mapping yet.']); }

    private function description(array $p): string { $value=(string)($p['description']??$p['desc']??$p['text']??'');$known=['id','name','title','description','desc','text','status','priority','project_id','project','parent_id','parent_task_id','assignee_id','assignee_ids','assignee','due_at','due_date','deadline','start_at','start_date','tags','labels'];$extra=[];foreach($p as $key=>$val){if(str_starts_with((string)$key,'_')||in_array($key,$known,true)||is_array($val)||$val==='')continue;$extra[]='- '.$key.': '.$val;}if($extra!==[])$value=rtrim($value)."\n\nПоля Shtab.app:\n".implode("\n",$extra);return mb_substr($value,0,65000); }
    private function status(mixed $value): string {$v=mb_strtolower(trim(is_array($value)?(string)($value['name']??$value['code']??''): (string)$value));return match($v){'done','completed','complete','closed','готово','завершено'=>'done','in_progress','in progress','active','working','в работе'=>'in_progress','blocked','заблокировано'=>'blocked',default=>'new'};}
    private function priority(mixed $value): string {$v=mb_strtolower(trim(is_array($value)?(string)($value['name']??$value['code']??''):(string)$value));return match($v){'urgent','critical','критический','1'=>'urgent','high','important','важный','2'=>'high','low','lowest','низкий','4'=>'low',default=>'normal'};}
    private function projectPrefix(array $job,string $source,int $attempt): string
    { $hash=hash('sha256',(string)($job['connection_id']??0).':'.$source.':'.$attempt);return'SH'.strtoupper(substr($hash,0,8)); }
    private function color(string $value): string { return preg_match('/^#[0-9a-f]{6}$/i',$value)?$value:'#64748b'; }
    /** @return array<int,string> */ private function split(mixed $value): array {if(is_array($value))$value=implode(',',$value);return array_values(array_filter(array_map('trim',preg_split('/[,;|\n]+',(string)$value)?:[]),static fn(string $v):bool=>$v!==''));}
}
