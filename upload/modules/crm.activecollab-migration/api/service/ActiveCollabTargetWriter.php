<?php
declare(strict_types=1);

namespace Module\Crm\ActiveCollabMigration\Service;

use Api\System\Library\Container;
use Module\Crm\ActiveCollabMigration\Repository\ActiveCollabMigrationRepository;
use RuntimeException;

final class ActiveCollabTargetWriter
{
    public function __construct(
        private readonly Container $container,
        private readonly ActiveCollabMigrationRepository $repo,
        private readonly ActiveCollabClient $client,
    ) {
    }

    public function service(string $id): mixed { return $this->container->get($id); }

    public function company(array $job, array $payload, array $actor): array
    {
        $source = $this->id($payload['id'] ?? null);
        $mapping = $this->repo->findMapping((int)$job['connection_id'], (string)$job['workspace_gid'], 'company', $source);
        if ($mapping && !empty($mapping['target_public_id'])) {
            if (($job['mode'] ?? 'import') !== 'sync') return $this->result('company', (string)$mapping['target_public_id'], 'skipped');
            $updated = $this->service('service.company')->update((string)$mapping['target_public_id'], ['title'=>$this->title($payload,'ActiveCollab company'),'email'=>(string)($payload['email']??'')], $actor);
            if (!is_array($updated)) throw new RuntimeException('ACTIVECOLLAB_COMPANY_UPDATE_FAILED');
            return $this->result('company', (string)$mapping['target_public_id'], 'updated');
        }
        $created = $this->service('service.company')->create(['title'=>$this->title($payload,'ActiveCollab company'),'email'=>(string)($payload['email']??''),'status'=>$this->active($payload)?'active':'archived'], $actor);
        if (!is_array($created) || empty($created['public_id'])) throw new RuntimeException('ACTIVECOLLAB_COMPANY_CREATE_FAILED');
        return $this->result('company', (string)$created['public_id'], 'imported');
    }

    public function project(array $job, array $payload, array $actor): array
    {
        $source = $this->id($payload['id'] ?? null);
        $workspace = (string)$job['workspace_gid'];
        $mapping = $this->repo->findMapping((int)$job['connection_id'], $workspace, 'project', $source);
        if ($mapping && !empty($mapping['target_public_id'])) {
            if (($job['mode'] ?? 'import') !== 'sync') return $this->result('project', (string)$mapping['target_public_id'], 'skipped');
            $updated = $this->service('service.project')->update((string)$mapping['target_public_id'], ['title'=>$this->title($payload,'ActiveCollab project'),'description'=>$this->description($payload),'status'=>$this->active($payload)?'active':'archived'], $actor);
            if (!is_array($updated)) throw new RuntimeException('ACTIVECOLLAB_PROJECT_UPDATE_FAILED');
            return $this->result('project', (string)$mapping['target_public_id'], 'updated');
        }
        $companyId = $this->id($payload['company_id'] ?? null);
        $company = $companyId !== '' ? $this->repo->findMapping((int)$job['connection_id'], $workspace, 'company', $companyId) : null;
        $created = $this->service('service.project')->create([
            'title'=>$this->title($payload,'ActiveCollab project'),
            'description'=>$this->description($payload),
            'status'=>$this->active($payload)?'active':'archived',
            'priority'=>'normal',
            'client_public_id'=>is_array($company)&&!empty($company['target_public_id'])?(string)$company['target_public_id']:null,
            'task_key_prefix'=>'AC'.strtoupper(substr(hash('sha256',$workspace.':'.$source),0,6)),
        ], $actor);
        if (!is_array($created) || empty($created['public_id'])) throw new RuntimeException(is_string($created)?'ACTIVECOLLAB_'.$created:'ACTIVECOLLAB_PROJECT_CREATE_FAILED');
        return $this->result('project', (string)$created['public_id'], 'imported');
    }

    public function taskList(array $job, array $payload, array $actor): array
    {
        $source = $this->id($payload['id'] ?? null);
        $workspace = (string)$job['workspace_gid'];
        $mapping = $this->repo->findMapping((int)$job['connection_id'], $workspace, 'task_list', $source);
        if ($mapping && !empty($mapping['target_public_id'])) return $this->result('project_module', (string)$mapping['target_public_id'], 'skipped');
        $project = $this->repo->findMapping((int)$job['connection_id'], $workspace, 'project', $this->id($payload['_source_project_id'] ?? $payload['project_id'] ?? null));
        if (!$project || empty($project['target_public_id'])) throw new RuntimeException('ACTIVECOLLAB_TASK_LIST_PROJECT_NOT_READY');
        $created = $this->service('service.project_module')->create(['project_public_id'=>(string)$project['target_public_id'],'title'=>$this->title($payload,'Task list'),'description'=>'Imported from ActiveCollab task list '.$source,'status'=>'planned','sort_order'=>(int)($payload['position']??$payload['sort_order']??0)], $actor);
        if (!is_array($created) || empty($created['public_id'])) throw new RuntimeException('ACTIVECOLLAB_TASK_LIST_CREATE_FAILED');
        return $this->result('project_module', (string)$created['public_id'], 'imported');
    }

    public function tag(array $job, array $payload): array
    {
        $source = $this->id($payload['id'] ?? null);
        $workspace = (string)$job['workspace_gid'];
        $mapping = $this->repo->findMapping((int)$job['connection_id'], $workspace, 'label', $source);
        if ($mapping && !empty($mapping['target_public_id'])) return $this->result('tag', (string)$mapping['target_public_id'], 'skipped');
        $code = 'activecollab_'.substr(hash('sha256',$workspace.':'.$source),0,24);
        $created = $this->service('service.tag')->create(['code'=>$code,'title'=>$this->title($payload,'ActiveCollab label'),'color'=>$this->color((string)($payload['color']??'')),'description'=>'Imported from ActiveCollab label '.$source]);
        if ($created === 'TAG_CODE_EXISTS') {
            $found = $this->service('service.tag')->list(['search'=>$code,'limit'=>100]);
            $created = null;
            foreach ((array)($found['items'] ?? []) as $candidate) {
                if (is_array($candidate) && (string)($candidate['code'] ?? '') === $code) { $created = $candidate; break; }
            }
        }
        if (!is_array($created) || empty($created['public_id'])) throw new RuntimeException('ACTIVECOLLAB_TAG_CREATE_FAILED');
        return $this->result('tag', (string)$created['public_id'], 'imported');
    }

    public function task(array $job, array $payload, array $actor): array
    {
        $source = $this->id($payload['id'] ?? null);
        $workspace = (string)$job['workspace_gid'];
        $connection = (int)$job['connection_id'];
        $mapping = $this->repo->findMapping($connection, $workspace, 'task', $source);
        $sourceKey = 'ac:' . hash('sha256', $workspace . ':' . $source);
        if (!$mapping) {
            $recoveredTarget = $this->repo->findTaskTargetBySource($sourceKey);
            if ($recoveredTarget !== null) {
                return $this->result('task', $recoveredTarget, 'skipped', ['Восстановлена ранее созданная задача ActiveCollab без сохранённого mapping.']);
            }
        }
        $project = $this->repo->findMapping($connection, $workspace, 'project', $this->id($payload['_source_project_id'] ?? $payload['project_id'] ?? null));
        if (!$project || empty($project['target_public_id'])) throw new RuntimeException('ACTIVECOLLAB_TASK_PROJECT_NOT_READY');
        $assigneeSource = $this->id($payload['assignee_id'] ?? ($payload['assignee']['id'] ?? null));
        $assignee = $assigneeSource !== '' ? $this->repo->mappedUserId($connection, $assigneeSource) : null;
        $warnings = [];
        if ($assigneeSource !== '' && $assignee === null) $warnings[] = 'Исполнитель ActiveCollab не сопоставлен с пользователем CRM.';
        $status = $this->status($payload);
        $input = ['project_public_id'=>(string)$project['target_public_id'],'title'=>$this->title($payload,'ActiveCollab task'),'description'=>$this->description($payload),'status'=>$status,'priority'=>$this->priority($payload),'due_at'=>$this->date($payload['due_on']??$payload['due_at']??$payload['due_date']??null),'start_at'=>$this->date($payload['start_at']??$payload['start_on']??null),'assignee_user_id'=>$assignee,'archived'=>!$this->active($payload),'source_type'=>'activecollab','source_id'=>$sourceKey,'source_url'=>(string)($payload['url_path']??$payload['permalink_url']??''),'source_payload_json'=>$payload,'created_at'=>$this->date($payload['created_at']??$payload['created_on']??null),'updated_at'=>$this->date($payload['updated_at']??$payload['updated_on']??null)];
        $parent = $this->id($payload['_source_parent_id'] ?? $payload['parent_id'] ?? $payload['parent_task_id'] ?? null);
        if ($parent !== '') {
            $parentMapping = $this->repo->findMapping($connection, $workspace, 'task', $parent);
            if (empty($parentMapping['target_public_id'])) throw new RuntimeException('ACTIVECOLLAB_TASK_PARENT_NOT_READY');
            $input['parent_task_public_id'] = (string)$parentMapping['target_public_id'];
        }
        $taskService = $this->service('service.task');
        if ($mapping && !empty($mapping['target_public_id'])) {
            $target=(string)$mapping['target_public_id'];
            if (($job['mode']??'import')==='sync') { $updated=$taskService->update($target,$input,(int)($actor['id']??0),$actor); if(!is_array($updated))throw new RuntimeException('ACTIVECOLLAB_TASK_UPDATE_FAILED'); return $this->result('task',$target,'updated',$warnings); }
            return $this->result('task',$target,'skipped',$warnings);
        }
        $created=$taskService->create($input,$actor); if(!is_array($created)||empty($created['public_id']))throw new RuntimeException(is_string($created)?'ACTIVECOLLAB_'.$created:'ACTIVECOLLAB_TASK_CREATE_FAILED');
        $target=(string)$created['public_id'];
        foreach($this->labels($payload) as $label){$labelId=$this->id($label['id']??$label);$tagMapping=$this->repo->findMapping($connection,$workspace,'label',$labelId);if(!empty($tagMapping['target_public_id'])){try{$this->service('service.tag')->attachToTask($target,(string)$tagMapping['target_public_id'],$actor);}catch(\Throwable){$warnings[]='Не удалось прикрепить метку.';}}}
        $listId=$this->id($payload['task_list_id']??$payload['tasklist_id']??null);if($listId!==''){ $list=$this->repo->findMapping($connection,$workspace,'task_list',$listId);if(!empty($list['target_public_id'])){try{$this->service('service.project_module')->addTasks((string)$list['target_public_id'],['task_public_ids'=>[$target]],$actor);}catch(\Throwable){$warnings[]='Не удалось добавить задачу в список.';}}}
        return $this->result('task',$target,'imported',$warnings);
    }

    public function dependency(array $job, array $payload, array $actor): array
    {
        $workspace=(string)$job['workspace_gid'];$source=(string)($payload['source_task_id']??'').':'.(string)($payload['depends_on_task_id']??'');$mapping=$this->repo->findMapping((int)$job['connection_id'],$workspace,'dependency',$source);if($mapping&&!empty($mapping['target_public_id']))return $this->result('dependency',(string)$mapping['target_public_id'],'skipped');
        $task=$this->repo->findMapping((int)$job['connection_id'],$workspace,'task',(string)($payload['source_task_id']??''));$depends=$this->repo->findMapping((int)$job['connection_id'],$workspace,'task',(string)($payload['depends_on_task_id']??''));if(!$task||empty($task['target_public_id'])||!$depends||empty($depends['target_public_id']))throw new RuntimeException('ACTIVECOLLAB_DEPENDENCY_TASK_NOT_READY');
        $created=$this->service('service.dependency')->create(['task_public_id'=>(string)$task['target_public_id'],'depends_on_task_public_id'=>(string)$depends['target_public_id'],'dependency_type'=>(string)($payload['dependency_type']??'FS')],$actor);if(!is_array($created)||empty($created['public_id']))throw new RuntimeException('ACTIVECOLLAB_DEPENDENCY_CREATE_FAILED');return $this->result('dependency',(string)$created['public_id'],'imported');
    }

    public function comment(array $job, array $payload, array $actor): array
    {
        $workspace=(string)$job['workspace_gid'];$source=$this->id($payload['id']??$payload['comment_id']??null);$mapping=$this->repo->findMapping((int)$job['connection_id'],$workspace,'comment',$source);if($mapping&&!empty($mapping['target_public_id']))return $this->result('comment',(string)$mapping['target_public_id'],'skipped');
        $task=$this->repo->findMapping((int)$job['connection_id'],$workspace,'task',$this->id($payload['_task_id']??$payload['task_id']??null));if(!$task||empty($task['target_public_id']))throw new RuntimeException('ACTIVECOLLAB_COMMENT_TASK_NOT_READY');
        $mappedAuthor=$this->repo->mappedUserId((int)$job['connection_id'],$this->id($payload['user_id']??$payload['author_id']??($payload['author']['id']??null)));
        // Historical authors are trusted only for root imports; a non-root
        // runner must not impersonate another CRM user through a mapping.
        $author=!empty($actor['is_root'])&&$mappedAuthor!==null?$mappedAuthor:(int)($actor['id']??0);
        $body=(string)($payload['body']??$payload['content']??$payload['text']??$payload['comment']??'');if(trim($body)==='')return $this->result('comment','skipped','skipped',['Пустой комментарий пропущен.']);
        $created=$this->service('service.comment')->createByTaskImported((string)$task['target_public_id'],['body'=>$body,'created_at'=>$payload['created_at']??$payload['created_on']??null,'author_user_id'=>$author],(int)($actor['id']??0));if(!is_array($created)||empty($created['public_id']))throw new RuntimeException('ACTIVECOLLAB_COMMENT_CREATE_FAILED');return $this->result('comment',(string)$created['public_id'],'imported');
    }

    public function attachment(array $job, array $payload, array $actor, string $token, int $maxBytes): array
    {
        $workspace=(string)$job['workspace_gid'];$source=$this->id($payload['id']??$payload['attachment_id']??null);$mapping=$this->repo->findMapping((int)$job['connection_id'],$workspace,'attachment',$source);if($mapping&&!empty($mapping['target_public_id']))return $this->result('file',(string)$mapping['target_public_id'],'skipped');
        $task=$this->repo->findMapping((int)$job['connection_id'],$workspace,'task',$this->id($payload['_task_id']??$payload['task_id']??null));if(!$task||empty($task['target_public_id']))throw new RuntimeException('ACTIVECOLLAB_ATTACHMENT_TASK_NOT_READY');
        $url=(string)($payload['download_url']??$payload['url']??$payload['content_url']??'');if($url==='')return $this->result('file','skipped','skipped',['У вложения нет URL скачивания.']);
        $download=$this->client->downloadAttachment($token,$url,$maxBytes);$path=(string)$download['path'];
        try{$content=file_get_contents($path);if(!is_string($content))throw new RuntimeException('ACTIVECOLLAB_ATTACHMENT_READ_FAILED');$created=$this->service('service.file')->create(['entity_type'=>'task','entity_public_id'=>(string)$task['target_public_id'],'name'=>$this->fileName((string)($payload['name']??$payload['filename']??'attachment.bin')),'mime_type'=>(string)($download['mime_type']??$payload['mime_type']??'application/octet-stream'),'content_base64'=>base64_encode($content)],[],(int)($actor['id']??0),$actor);if(!is_array($created)||empty($created['public_id']))throw new RuntimeException('ACTIVECOLLAB_FILE_CREATE_FAILED');return $this->result('file',(string)$created['public_id'],'imported');}finally{@unlink($path);}
    }

    public function timeRecord(array $job, array $payload, array $actor): array
    {
        $workspace=(string)$job['workspace_gid'];$source=$this->id($payload['id']??$payload['time_record_id']??null);$mapping=$this->repo->findMapping((int)$job['connection_id'],$workspace,'time_record',$source);if($mapping&&!empty($mapping['target_public_id']))return $this->result('worklog',(string)$mapping['target_public_id'],'skipped');
        $userSource=$this->id($payload['user_id']??$payload['user']['id']??null);$userPublic=$userSource!==''?$this->repo->mappedUserPublicId((int)$job['connection_id'],$userSource):null;if($userPublic===null)throw new RuntimeException('ACTIVECOLLAB_TIME_RECORD_USER_UNMAPPED');
        $task=$this->repo->findMapping((int)$job['connection_id'],$workspace,'task',$this->id($payload['_task_id']??$payload['task_id']??null));if(!$task||empty($task['target_public_id']))throw new RuntimeException('ACTIVECOLLAB_TIME_RECORD_TASK_NOT_READY');
        $minutes=$this->minutes($payload);if($minutes<=0)return $this->result('worklog','skipped','skipped',['Нулевая запись времени пропущена.']);$date=$this->date($payload['record_date']??$payload['date']??$payload['created_at']??null)??gmdate('Y-m-d H:i:s');$note=trim((string)($payload['summary']??$payload['description']??$payload['note']??''));$note=mb_substr('[ActiveCollab] '.($note!==''?$note:'time record')."\nBillable: ".(!empty($payload['billable_status'])?'yes':'no'),0,65000);
        $created=$this->service('service.worklog')->create(['user_public_id'=>$userPublic,'task_public_id'=>(string)$task['target_public_id'],'minutes_spent'=>$minutes,'note'=>$note,'logged_at'=>$date,'started_at'=>$date],$actor);if(!is_array($created)||empty($created['public_id']))throw new RuntimeException('ACTIVECOLLAB_TIME_RECORD_CREATE_FAILED');return $this->result('worklog',(string)$created['public_id'],'imported');
    }

    private function result(string $type,string $target,string $state,array $warnings=[]): array { return ['target_type'=>$type,'target_public_id'=>$target,'state'=>$state,'warnings'=>$warnings]; }
    private function id(mixed $value): string { if(is_array($value))$value=$value['id']??$value['user_id']??'';return is_scalar($value)?trim((string)$value):''; }
    private function title(array $p,string $fallback): string { return mb_substr(trim((string)($p['name']??$p['title']??$fallback))?:$fallback,0,255); }
    private function description(array $p): string { return trim((string)($p['description']??$p['body']??$p['notes']??'')); }
    private function active(array $p): bool { foreach(['is_trashed','is_archived','archived'] as $k)if(array_key_exists($k,$p)&&$this->bool($p[$k])===true)return false;return true; }
    private function status(array $p): string { if(!$this->active($p))return 'archived';if(!empty($p['is_completed'])||!empty($p['completed']))return 'done';return 'new'; }
    private function priority(array $p): string { $v=strtolower((string)($p['priority']??$p['priority_name']??''));return match($v){'urgent','critical','highest'=>'urgent','high'=>'high','low','lowest'=>'low',default=>'normal'}; }
    private function bool(mixed $v): ?bool { if(is_bool($v))return$v;if(is_numeric($v))return(int)$v!==0;if(is_string($v))return match(strtolower(trim($v))){ '1','true','yes','on'=>true,'0','false','no','off'=>false,default=>null};return null; }
    private function date(mixed $v): ?string { if(!is_scalar($v)||trim((string)$v)==='')return null;$t=strtotime((string)$v);return$t===false?null:gmdate('Y-m-d H:i:s',$t); }
    private function color(string $v): string { return preg_match('/^#[0-9a-f]{6}$/i',$v)?$v:'#64748b'; }
    private function labels(array $p): array { foreach(['labels','tags'] as $k)if(isset($p[$k])&&is_array($p[$k]))return array_values(array_filter(array_map(static fn(mixed$v):array=>is_array($v)?$v:['id'=>(string)$v,'name'=>(string)$v],$p[$k])));return[]; }
    private function minutes(array $p): int { if(isset($p['minutes'])&&is_numeric($p['minutes']))return max(0,(int)$p['minutes']);if(isset($p['value'])&&is_numeric($p['value']))return max(0,(int)round((float)$p['value']*60));if(isset($p['hours'])&&is_numeric($p['hours']))return max(0,(int)round((float)$p['hours']*60));return 0; }
    private function fileName(string $name): string { $name=basename(str_replace('\\','/',$name));return trim($name)!==''?$name:'attachment.bin'; }
}
