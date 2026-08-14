<?php
declare(strict_types=1);

namespace Module\Crm\ClickUpMigration\Service;

use Api\System\Library\Container;
use Module\Crm\ClickUpMigration\Repository\ClickUpMigrationRepository;
use RuntimeException;

final class ClickUpTargetWriter
{
    public function __construct(
        private readonly Container $container,
        private readonly ClickUpMigrationRepository $repo,
        private readonly ClickUpClient $client,
    ) {}

    public function service(string $id): mixed { return $this->container->get($id); }
    private function map(array $job, string $type, string $id): ?array { return $id === '' ? null : $this->repo->findMapping((int)$job['connection_id'], $type, $id); }
    private function title(array $p, string $fallback = 'Без названия'): string { return trim((string)($p['name'] ?? $p['title'] ?? $p['content'] ?? $fallback)) ?: $fallback; }
    private function sourceId(array $payload): string
    {
        // The job item ID is canonical: it is also the key persisted in the
        // source-mapping table. Native payload IDs are only the fallback for
        // callers that invoke the writer outside the job importer.
        $source = trim((string)($payload['_source_id'] ?? ''));
        return $source !== '' ? $source : trim((string)($payload['id'] ?? ''));
    }
    private function date(mixed $value): ?string { $v=trim((string)$value); if($v==='')return null; if(is_numeric($v)){ $n=(int)$v; if($n>100000000000)$n=(int)floor($n/1000); return gmdate('Y-m-d H:i:s',$n); } $t=strtotime($v); return $t===false?null:gmdate('Y-m-d H:i:s',$t); }
    private function priority(mixed $value): string { $v=is_array($value)?(int)($value['id']??0):(int)$value; return match($v){1=>'urgent',2=>'high',3=>'normal',4=>'low',default=>'normal'}; }
    private function description(array $p): string
    {
        $value=(string)($p['markdown_description']??$p['description']??$p['text_content']??'');
        $fields=[];
        foreach((array)($p['custom_fields']??[]) as $field){ if(!is_array($field))continue; $name=(string)($field['name']??$field['id']??''); $display=$field['value']??$field['value_text']??$field['type_config']['options']??null; if(is_array($display))$display=json_encode($display,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES); if($name!==''&&$display!==null&&$display!=='')$fields[]='- '.$name.': '.(string)$display; }
        if($fields!==[])$value=rtrim($value)."\n\nПользовательские поля ClickUp:\n".implode("\n",$fields);
        return mb_substr($value,0,65000);
    }

    public function project(array $job, array $payload, array $actor): array
    {
        $source=$this->sourceId($payload); $map=$this->map($job,'space',$source);
        if($map&&$map['target_public_id']!==''){
            $existing=$this->service('service.project')->get((string)$map['target_public_id'],$actor);
            if($existing&&($job['mode']??'import')!=='sync')return $this->result('project',(string)$map['target_public_id'],'skipped');
            if($existing){$updated=$this->service('service.project')->update((string)$map['target_public_id'],['title'=>$this->title($payload,'ClickUp space'),'description'=>$this->description($payload)],$actor);if(!is_array($updated))throw new RuntimeException('CLICKUP_PROJECT_UPDATE_FAILED');return $this->result('project',(string)$map['target_public_id'],'updated');}
        }
        $created=$this->service('service.project')->create(['title'=>$this->title($payload,'ClickUp space'),'description'=>$this->description($payload),'status'=>!empty($payload['archived'])?'archived':'active','priority'=>'normal','task_key_prefix'=>'CU'.strtoupper(substr(hash('sha256',$source),0,4))],$actor);
        if(!is_array($created)||empty($created['public_id']))throw new RuntimeException('CLICKUP_PROJECT_CREATE_FAILED');
        return $this->result('project',(string)$created['public_id'],'imported');
    }

    public function module(array $job, array $payload, array $actor): array
    {
        $type=(string)($payload['_source_type']??'folder'); $source=$this->sourceId($payload); $map=$this->map($job,$type,$source);
        if($map&&$map['target_public_id']!=='')return $this->result('project_module',(string)$map['target_public_id'],'skipped');
        $spaceId=(string)($payload['_space_id']??$payload['_source_project_id']??$payload['space_id']??''); $space=$this->map($job,'space',$spaceId);
        if(!$space||empty($space['target_public_id']))throw new RuntimeException('CLICKUP_MODULE_PROJECT_NOT_READY');
        $created=$this->service('service.project_module')->create(['project_public_id'=>$space['target_public_id'],'title'=>$this->title($payload,'ClickUp folder/list'),'description'=>'Imported from ClickUp '.($type==='folder'?'folder':'list').' '.$source,'status'=>!empty($payload['archived'])?'archived':'planned','sort_order'=>(int)($payload['orderindex']??$payload['order']??$payload['position']??0)],$actor);
        if(!is_array($created)||empty($created['public_id']))throw new RuntimeException('CLICKUP_MODULE_CREATE_FAILED');
        return $this->result('project_module',(string)$created['public_id'],'imported');
    }

    public function label(array $job, array $payload): array
    {
        $source=$this->sourceId($payload); $map=$this->map($job,'tag',$source); if($map&&$map['target_public_id']!=='')return $this->result('tag',(string)$map['target_public_id'],'skipped');
        $code='clickup_'.substr(hash('sha256',(string)$job['connection_id'].':'.$source),0,24); $created=$this->service('service.tag')->create(['code'=>$code,'title'=>$this->title($payload,'ClickUp tag'),'color'=>$this->color((string)($payload['tag_fg']??$payload['color']??'')),'description'=>'Imported from ClickUp tag '.$source]);
        if($created==='TAG_CODE_EXISTS'){$list=$this->service('service.tag')->list(['search'=>$code,'limit'=>5]);$created=$list['items'][0]??null;}
        if(!is_array($created)||empty($created['public_id']))throw new RuntimeException('CLICKUP_TAG_CREATE_FAILED'); return $this->result('tag',(string)$created['public_id'],'imported');
    }

    public function task(array $job, array $payload, array $actor): array
    {
        $source=$this->sourceId($payload); $map=$this->map($job,'task',$source); $spaceId=(string)($payload['_space_id']??$payload['space']['id']??''); $project=$this->map($job,'space',$spaceId);
        if(!$project||empty($project['target_public_id']))throw new RuntimeException('CLICKUP_TASK_PROJECT_NOT_READY');
        $warnings=[]; $assignee=null; $unmappedAssignees=0; $assigneeCount=0; foreach((array)($payload['assignees']??[]) as $user){$id=(string)($user['id']??$user);if($id==='')continue;++$assigneeCount;$mapped=$this->repo->mappedUserId((int)$job['connection_id'],$id);if($mapped!==null&&$assignee===null)$assignee=$mapped;if($mapped===null)++$unmappedAssignees;}
        if ($unmappedAssignees > 0) $warnings[] = $unmappedAssignees . ' ClickUp assignee(s) have no active CRM user mapping.';
        if ($assigneeCount > 1 && $assignee !== null) $warnings[] = 'ClickUp task has multiple assignees; CRM preserves only the first mapped assignee.';
        $sourceStatus=(string)($payload['status']['status']??$payload['status']??'new'); $status=$this->normalizeStatus($sourceStatus,(string)($payload['status']['type']??''));
        $input=['project_public_id'=>$project['target_public_id'],'title'=>$this->title($payload,'ClickUp task'),'description'=>$this->description($payload),'status'=>$status,'priority'=>$this->priority($payload['priority']??0),'due_at'=>$this->date($payload['due_date']??$payload['due']??null),'start_at'=>$this->date($payload['start_date']??$payload['start']??null),'assignee_user_id'=>$assignee,'source_type'=>'clickup','source_id'=>$source,'source_url'=>(string)($payload['url']??''),'source_payload_json'=>$payload,'created_at'=>$this->date($payload['date_created']??null),'updated_at'=>$this->date($payload['date_updated']??null)];
        $parent=(string)($payload['parent_id']??$payload['parent']??''); if($parent!==''){ $parentMap=$this->map($job,'task',$parent); if(empty($parentMap['target_public_id']))throw new RuntimeException('CLICKUP_PARENT_TASK_NOT_READY'); $input['parent_task_public_id']=$parentMap['target_public_id']; }
        $target='';$state='imported';$write=true;
        if($map&&!empty($map['target_public_id'])){$target=(string)$map['target_public_id'];if(($job['mode']??'import')==='sync'){$updated=$this->service('service.task')->update($target,$input,(int)($actor['id']??0),$actor);if(!is_array($updated))throw new RuntimeException('CLICKUP_TASK_UPDATE_FAILED');$state='updated';}else{$state='skipped';$write=false;}}
        else{$created=$this->service('service.task')->create($input,$actor);if(!is_array($created)||empty($created['public_id']))throw new RuntimeException('CLICKUP_TASK_CREATE_FAILED');$target=(string)$created['public_id'];}
        if($write){foreach((array)($payload['tags']??[]) as $tag){$name=trim((string)($tag['name']??$tag));if($name==='')continue;$tagId=$spaceId.':'.hash('sha256',mb_strtolower($name));$tagMap=$this->map($job,'tag',$tagId);if(!empty($tagMap['target_public_id'])){try{$this->service('service.tag')->attachToTask($target,(string)$tagMap['target_public_id'],$actor);}catch(\Throwable){$warnings[]='A ClickUp tag could not be attached.';}}}}
        $moduleId=(string)($payload['_list_id']??$payload['list']['id']??'');$moduleMap=$this->map($job,'list',$moduleId);if($write&&$moduleMap&&$moduleMap['target_type']==='project_module'){try{$this->service('service.project_module')->addTasks((string)$moduleMap['target_public_id'],['task_public_ids'=>[$target]],$actor);}catch(\Throwable){$warnings[]='Task could not be added to its ClickUp list module.';}}
        return $this->result('task',$target,$state,$warnings);
    }

    public function checklist(array $job, array $payload, array $actor): array
    {
        $source=$this->sourceId($payload);$map=$this->map($job,'checklist',$source);if($map&&$map['target_public_id']!=='')return $this->result('checklist',(string)$map['target_public_id'],'skipped');$task=$this->map($job,'task',(string)($payload['_task_id']??$payload['task_id']??''));if(empty($task['target_public_id']))throw new RuntimeException('CLICKUP_CHECKLIST_TASK_NOT_READY');$created=$this->service('service.checklist')->createImported((string)$task['target_public_id'],['title'=>$this->title($payload,'Checklist'),'created_at'=>$this->date($payload['date_created']??null)],$actor);if(!is_array($created)||empty($created['public_id']))throw new RuntimeException('CLICKUP_CHECKLIST_CREATE_FAILED');return $this->result('checklist',(string)$created['public_id'],'imported');
    }

    public function checklistItem(array $job, array $payload, array $actor): array
    {
        $source=$this->sourceId($payload);$map=$this->map($job,'checklist_item',$source);if($map&&$map['target_public_id']!=='')return $this->result('checklist_item',(string)$map['target_public_id'],'skipped');$parent=$this->map($job,'checklist',(string)($payload['_checklist_id']??''));if(empty($parent['target_public_id']))throw new RuntimeException('CLICKUP_CHECKLIST_PARENT_NOT_READY');$created=$this->service('service.checklist')->createItemImported((string)$parent['target_public_id'],['title'=>$this->title($payload,'Checklist item'),'is_done'=>!empty($payload['resolved'])||($payload['status']??'')==='resolved','sort_order'=>(int)($payload['orderindex']??0)],$actor);if(!is_array($created)||empty($created['public_id']))throw new RuntimeException('CLICKUP_CHECKLIST_ITEM_CREATE_FAILED');return $this->result('checklist_item',(string)$created['public_id'],'imported');
    }

    public function comment(array $job, array $payload, array $actor): array
    {
        $source=$this->sourceId($payload);$map=$this->map($job,'comment',$source);if($map&&$map['target_public_id']!=='')return $this->result('comment',(string)$map['target_public_id'],'skipped');$task=$this->map($job,'task',(string)($payload['_task_id']??$payload['task_id']??''));if(empty($task['target_public_id']))throw new RuntimeException('CLICKUP_COMMENT_TASK_NOT_READY');$body=(string)($payload['comment_text']??$payload['comment']??$payload['text']??'');if(trim($body)==='')return $this->result('comment','skipped','skipped',['Empty ClickUp comment skipped.']);$warnings=[];$commenter=(string)($payload['user']['id']??$payload['creator']['id']??$payload['user_id']??'');$authorId=$commenter!==''?$this->repo->mappedUserId((int)$job['connection_id'],$commenter):null;if($commenter!==''&&$authorId===null)$warnings[]='ClickUp comment author has no active CRM user mapping; migration actor was used.';$input=['body'=>$body,'created_at'=>$this->date($payload['date']??$payload['date_created']??null)];if($authorId!==null)$input['author_user_id']=$authorId;$created=$this->service('service.comment')->createByTaskImported((string)$task['target_public_id'],$input,(int)($actor['id']??0));if(!is_array($created)||empty($created['public_id']))throw new RuntimeException('CLICKUP_COMMENT_CREATE_FAILED');return $this->result('comment',(string)$created['public_id'],'imported',$warnings);
    }

    public function attachment(array $job, array $payload, array $actor, string $token, int $maxBytes): array
    {
        $source = $this->sourceId($payload);
        $existing = $this->map($job, 'attachment', $source);
        if ($existing !== null && (string)($existing['target_public_id'] ?? '') !== '') {
            return $this->result('file', (string)$existing['target_public_id'], 'skipped');
        }
        $url=trim((string)($payload['url']??$payload['attachment_url']??''));$task=$this->map($job,'task',(string)($payload['_task_id']??''));if(empty($task['target_public_id']))return $this->result('file','skipped','skipped',['Attachment target task is missing.']);if($url==='')return $this->result('file','skipped','skipped',['Attachment has no downloadable URL.']);
        try {
            $download = $this->client->downloadAttachment($token, $url, $maxBytes);
            try {
                $bytes = file_get_contents((string)$download['path']);
                if (!is_string($bytes)) throw new RuntimeException('CLICKUP_ATTACHMENT_READ_FAILED');
                $created = $this->service('service.file')->create([
                    'entity_type' => 'task', 'entity_public_id' => (string)$task['target_public_id'],
                    'name' => trim((string)($payload['title'] ?? $payload['name'] ?? 'clickup-attachment.bin')),
                    'mime_type' => (string)($download['mime_type'] ?? 'application/octet-stream'),
                    'content_base64' => base64_encode($bytes),
                ], [], (int)($actor['id'] ?? 0), $actor);
                if (!is_array($created) || empty($created['public_id'])) throw new RuntimeException('CLICKUP_FILE_CREATE_FAILED');
                return $this->result('file', (string)$created['public_id'], 'imported');
            } finally { @unlink((string)$download['path']); }
        } catch (\Throwable $e) {
            return $this->result('file', 'skipped', 'skipped', ['ClickUp attachment was not downloaded: ' . ($e->getMessage() === 'CLICKUP_ATTACHMENT_TOO_LARGE' ? 'file is too large.' : 'URL is not an allowed official ClickUp URL or download failed.')]);
        }
    }

    public function timeEntry(array $job, array $payload, array $actor): array
    {
        $source = $this->sourceId($payload);
        $map = $this->map($job, 'time_entry', $source);
        $task = $this->map($job, 'task', (string)($payload['_task_id'] ?? $payload['task_id'] ?? ''));
        if (empty($task['target_public_id'])) throw new RuntimeException('CLICKUP_TIME_TASK_NOT_READY');
        $duration = (int)($payload['duration'] ?? 0);
        if ($duration < 0) return $this->result('worklog', 'skipped', 'skipped', ['Running ClickUp timer was not imported.']);
        $minutes = max(1, (int)ceil($duration / 60000));
        if (empty($actor['is_root'])) return $this->result('worklog', 'skipped', 'skipped', ['Time entries require a root worker to preserve the source user attribution.']);
        $userId = (string)($payload['user']['id'] ?? $payload['user_id'] ?? '');
        $userPublic = $this->repo->mappedUserPublicId((int)$job['connection_id'], $userId);
        if ($userPublic === null) return $this->result('worklog', 'skipped', 'skipped', ['Time entry user is not mapped to a CRM user.']);
        $start = $this->date($payload['start'] ?? $payload['start_time'] ?? null);
        $end = $this->date($payload['end'] ?? $payload['end_time'] ?? null);
        $input = [
            'task_public_id' => (string)$task['target_public_id'],
            'minutes_spent' => $minutes,
            'note' => (string)($payload['description'] ?? $payload['note'] ?? ''),
            'logged_at' => $start ?? gmdate('Y-m-d H:i:s'),
            'started_at' => $start,
            'ended_at' => $end,
        ];
        if ($map && !empty($map['target_public_id'])) {
            if (($job['mode'] ?? 'import') !== 'sync') return $this->result('worklog', (string)$map['target_public_id'], 'skipped');
            $updated = $this->service('service.worklog')->update((string)$map['target_public_id'], $input, $actor);
            if (!is_array($updated)) throw new RuntimeException('CLICKUP_WORKLOG_UPDATE_FAILED');
            return $this->result('worklog', (string)$map['target_public_id'], 'updated');
        }
        $created = $this->service('service.worklog')->create($input + ['user_public_id' => $userPublic], $actor);
        if (!is_array($created) || empty($created['public_id'])) throw new RuntimeException('CLICKUP_WORKLOG_CREATE_FAILED');
        return $this->result('worklog', (string)$created['public_id'], 'imported');
    }

    public function dependency(array $job, array $payload, array $actor): array
    {
        $source=$this->sourceId($payload);$map=$this->map($job,'dependency',$source);if($map&&$map['target_public_id']!=='')return $this->result('dependency',(string)$map['target_public_id'],'skipped');$task=$this->map($job,'task',(string)($payload['task_id']??''));$depends=$this->map($job,'task',(string)($payload['depends_on_task_id']??$payload['depends_on']??''));if(empty($task['target_public_id'])||empty($depends['target_public_id']))throw new RuntimeException('CLICKUP_DEPENDENCY_TASK_NOT_READY');$type=strtoupper((string)($payload['type']??'FS'));$type=in_array($type,['FS','SS','FF','SF','BLOCKS'],true)?$type:'FS';$created=$this->service('service.dependency')->create(['task_public_id'=>$task['target_public_id'],'depends_on_task_public_id'=>$depends['target_public_id'],'dependency_type'=>$type],$actor);if(!is_array($created)||empty($created['public_id']))throw new RuntimeException('CLICKUP_DEPENDENCY_CREATE_FAILED');return $this->result('dependency',(string)$created['public_id'],'imported');
    }

    public function goal(array $job, array $payload, array $actor): array { return $this->result('goal','skipped','skipped',['ClickUp goals were discovered but no equivalent CRM goal entity is available.']); }
    private function result(string $type,string $id,string $state,array $warnings=[]): array{return ['target_type'=>$type,'target_public_id'=>$id==='skipped'?'':$id,'state'=>$state,'warnings'=>$warnings];}
    private function normalizeStatus(string $value, string $type): string
    {
        if ($type === 'closed') return 'done';
        $status = mb_strtolower(trim($value));
        if (in_array($status, ['done','complete','completed','closed','resolved','finished'], true)) return 'done';
        if (in_array($status, ['in progress','in_progress','active','working','started','open'], true)) return 'in_progress';
        if ($status === 'blocked') return 'blocked';
        // Custom ClickUp statuses are retained in source_payload_json; only
        // known CRM-safe codes are sent to TaskService.
        return 'new';
    }
    private function color(string $value): string { return preg_match('/^#[0-9a-f]{6}$/i', $value) ? $value : '#64748b'; }
}
