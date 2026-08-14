<?php
declare(strict_types=1);

namespace Module\Crm\WorksectionMigration\Service;

use Api\System\Library\Container;
use Module\Crm\WorksectionMigration\Repository\WorksectionMigrationRepository;
use RuntimeException;

final class WorksectionTargetWriter
{
    public function __construct(
        private readonly Container $container,
        private readonly WorksectionMigrationRepository $repo,
        private readonly WorksectionClient $client,
    ) {
    }

    public function service(string $id): mixed { return $this->container->get($id); }

    private function map(array $job, string $type, string $id): ?array { return $id === '' ? null : $this->repo->findMapping((int)$job['connection_id'], (string)$job['workspace_gid'], $type, $id); }

    public function projectGroup(array $job, array $payload, array $actor): array
    {
        $source = $this->id($payload['id'] ?? null);
        $mapping = $this->map($job, 'project_group', $source);
        if ($mapping && !empty($mapping['target_public_id'])) return $this->result('company', (string)$mapping['target_public_id'], 'skipped');
        $client = $payload['client'] ?? null;
        $clientTitle = is_array($client) ? trim((string)($client['name'] ?? $client['title'] ?? '')) : trim((string)($client ?? ''));
        if ($clientTitle === '') {
            // A project folder without a client has no counterparty mapping.
            return $this->result('company', 'skipped', 'skipped', ['Worksection project folder has no client and was not imported as a company.']);
        }
        $created = $this->service('service.company')->create(['title'=>$this->title($payload, $clientTitle),'email'=>is_array($client)?trim((string)($client['email'] ?? '')):'','status'=>'active'], $actor);
        if (!is_array($created) || empty($created['public_id'])) throw new RuntimeException('WORKSECTION_COMPANY_CREATE_FAILED');
        return $this->result('company', (string)$created['public_id'], 'imported');
    }

    public function project(array $job, array $payload, array $actor): array
    {
        $source = $this->id($payload['id'] ?? null);
        $workspace = (string)$job['workspace_gid'];
        $mapping = $this->map($job, 'project', $source);
        if ($mapping && !empty($mapping['target_public_id'])) {
            if (($job['mode'] ?? 'import') !== 'sync') return $this->result('project', (string)$mapping['target_public_id'], 'skipped');
            $updated = $this->service('service.project')->update((string)$mapping['target_public_id'], ['title'=>$this->title($payload,'Worksection project'),'description'=>$this->description($payload),'status'=>$this->active($payload)?'active':'archived'], $actor);
            if (!is_array($updated)) throw new RuntimeException('WORKSECTION_PROJECT_UPDATE_FAILED');
            return $this->result('project', (string)$mapping['target_public_id'], 'updated');
        }
        $groupSource = $this->id($payload['group'] ?? $payload['group_id'] ?? null);
        $company = $groupSource !== '' ? $this->map($job, 'project_group', $groupSource) : null;
        $created = $this->service('service.project')->create([
            'title'=>$this->title($payload,'Worksection project'),
            'description'=>$this->description($payload),
            'status'=>$this->active($payload)?'active':'archived',
            'priority'=>'normal',
            'client_public_id'=>is_array($company)&&!empty($company['target_public_id'])?(string)$company['target_public_id']:null,
            'task_key_prefix'=>'WS'.strtoupper(substr(hash('sha256',$workspace.':'.$source),0,6)),
        ], $actor);
        if (!is_array($created) || empty($created['public_id'])) throw new RuntimeException(is_string($created)?'WORKSECTION_'.$created:'WORKSECTION_PROJECT_CREATE_FAILED');
        return $this->result('project', (string)$created['public_id'], 'imported');
    }

    public function tag(array $job, array $payload): array
    {
        $source = $this->id($payload['id'] ?? null);
        if ($source === '') $source = 'name_' . hash('sha256', strtolower(trim((string)($payload['name'] ?? ''))));
        $workspace = (string)$job['workspace_gid'];
        $mapping = $this->map($job, 'label', $source);
        if ($mapping && !empty($mapping['target_public_id'])) return $this->result('tag', (string)$mapping['target_public_id'], 'skipped');
        $code = 'worksection_'.substr(hash('sha256',$workspace.':'.$source),0,24);
        $created = $this->service('service.tag')->create(['code'=>$code,'title'=>$this->title($payload,'Worksection tag'),'color'=>$this->color((string)($payload['color']??'')),'description'=>'Imported from Worksection tag '.$source]);
        if ($created === 'TAG_CODE_EXISTS') {
            $found = $this->service('service.tag')->list(['search'=>$code,'limit'=>100]);
            $created = null;
            foreach ((array)($found['items'] ?? []) as $candidate) {
                if (is_array($candidate) && (string)($candidate['code'] ?? '') === $code) { $created = $candidate; break; }
            }
        }
        if (!is_array($created) || empty($created['public_id'])) throw new RuntimeException('WORKSECTION_TAG_CREATE_FAILED');
        return $this->result('tag', (string)$created['public_id'], 'imported');
    }

    public function task(array $job, array $payload, array $actor): array
    {
        $source = $this->id($payload['id'] ?? null);
        $workspace = (string)$job['workspace_gid'];
        $connection = (int)$job['connection_id'];
        $mapping = $this->map($job, 'task', $source);
        $sourceKey = 'ws:' . hash('sha256', (string)$connection . ':' . $workspace . ':' . $source);
        if (!$mapping) {
            $recoveredTarget = $this->repo->findTaskTargetBySource($sourceKey);
            if ($recoveredTarget !== null) {
                return $this->result('task', $recoveredTarget, 'skipped', ['Восстановлена ранее созданная задача Worksection без сохранённого mapping.']);
            }
        }
        $project = $this->map($job, 'project', $this->id($payload['_source_project_id'] ?? $payload['project_id'] ?? null));
        if (!$project || empty($project['target_public_id'])) throw new RuntimeException('WORKSECTION_TASK_PROJECT_NOT_READY');
        $assigneeSource = $this->id($payload['user'] ?? $payload['assignee'] ?? null);
        if ($assigneeSource === '' && is_array($payload['user'] ?? null)) $assigneeSource = $this->id($payload['user']['id'] ?? null);
        if ($assigneeSource === '' && is_array($payload['assignee'] ?? null)) $assigneeSource = $this->id($payload['assignee']['id'] ?? null);
        $assignee = $assigneeSource !== '' ? $this->repo->mappedUserId($connection, $assigneeSource) : null;
        $warnings = [];
        if ($assigneeSource !== '' && $assignee === null) $warnings[] = 'Исполнитель Worksection не сопоставлен с пользователем CRM.';
        $status = $this->status($payload);
        $input = ['project_public_id'=>(string)$project['target_public_id'],'title'=>$this->title($payload,'Worksection task'),'description'=>$this->description($payload),'status'=>$status,'priority'=>$this->priority($payload),'due_at'=>$this->date($payload['date_end']??$payload['due_at']??$payload['deadline']??null),'start_at'=>$this->date($payload['date_start']??$payload['start_at']??null),'assignee_user_id'=>$assignee,'archived'=>!$this->active($payload),'source_type'=>'worksection','source_id'=>$sourceKey,'source_url'=>(string)($payload['url']??$payload['link']??''),'source_payload_json'=>$payload,'created_at'=>$this->date($payload['date_added']??$payload['created_at']??null),'updated_at'=>$this->date($payload['updated_at']??$payload['date_added']??null)];
        $parent = $this->id($payload['_source_parent_id'] ?? $payload['parent'] ?? $payload['parent_id'] ?? null);
        if ($parent === '' && is_array($payload['parent'] ?? null)) $parent = $this->id($payload['parent']['id'] ?? null);
        if ($parent !== '') {
            $parentMapping = $this->map($job, 'task', $parent);
            if (empty($parentMapping['target_public_id'])) throw new RuntimeException('WORKSECTION_TASK_PARENT_NOT_READY');
            $input['parent_task_public_id'] = (string)$parentMapping['target_public_id'];
        }
        $taskService = $this->service('service.task');
        if ($mapping && !empty($mapping['target_public_id'])) {
            $target=(string)$mapping['target_public_id'];
            if (($job['mode']??'import')==='sync') { $updated=$taskService->update($target,$input,(int)($actor['id']??0),$actor); if(!is_array($updated))throw new RuntimeException('WORKSECTION_TASK_UPDATE_FAILED'); return $this->result('task',$target,'updated',$warnings); }
            return $this->result('task',$target,'skipped',$warnings);
        }
        $created=$taskService->create($input,$actor); if(!is_array($created)||empty($created['public_id']))throw new RuntimeException(is_string($created)?'WORKSECTION_'.$created:'WORKSECTION_TASK_CREATE_FAILED');
        $target=(string)$created['public_id'];
        foreach($this->labels($payload) as $label){$labelId=$this->id($label['id']??$label);$tagMapping=$this->map($job,'label',$labelId);if(!empty($tagMapping['target_public_id'])){try{$this->service('service.tag')->attachToTask($target,(string)$tagMapping['target_public_id'],$actor);}catch(\Throwable){$warnings[]='Не удалось прикрепить метку.';}}}
        return $this->result('task',$target,'imported',$warnings);
    }

    public function dependency(array $job, array $payload, array $actor): array
    {
        $workspace=(string)$job['workspace_gid'];$source=(string)($payload['source_task_id']??'').':'.(string)($payload['depends_on_task_id']??'');$mapping=$this->map($job,'dependency',$source);if($mapping&&!empty($mapping['target_public_id']))return $this->result('dependency',(string)$mapping['target_public_id'],'skipped');
        $task=$this->map($job,'task',(string)($payload['source_task_id']??''));$depends=$this->map($job,'task',(string)($payload['depends_on_task_id']??''));if(!$task||empty($task['target_public_id'])||!$depends||empty($depends['target_public_id']))throw new RuntimeException('WORKSECTION_DEPENDENCY_TASK_NOT_READY');
        $created=$this->service('service.dependency')->create(['task_public_id'=>(string)$task['target_public_id'],'depends_on_task_public_id'=>(string)$depends['target_public_id'],'dependency_type'=>(string)($payload['dependency_type']??'FS')],$actor);if(!is_array($created)||empty($created['public_id']))throw new RuntimeException('WORKSECTION_DEPENDENCY_CREATE_FAILED');return $this->result('dependency',(string)$created['public_id'],'imported');
    }

    public function comment(array $job, array $payload, array $actor): array
    {
        $workspace=(string)$job['workspace_gid'];$source=$this->id($payload['id']??$payload['comment_id']??null);$mapping=$this->map($job,'comment',$source);if($mapping&&!empty($mapping['target_public_id']))return $this->result('comment',(string)$mapping['target_public_id'],'skipped');
        $task=$this->map($job,'task',$this->id($payload['_task_id']??$payload['task_id']??null));if(!$task||empty($task['target_public_id']))throw new RuntimeException('WORKSECTION_COMMENT_TASK_NOT_READY');
        $mappedAuthor=$this->repo->mappedUserId((int)$job['connection_id'],$this->id($payload['user']??$payload['author_id']??($payload['author']['id']??null)));
        $author=!empty($actor['is_root'])&&$mappedAuthor!==null?$mappedAuthor:(int)($actor['id']??0);
        $body=(string)($payload['text']??$payload['body']??$payload['comment']??'');if(trim($body)==='')return $this->result('comment','skipped','skipped',['Пустой комментарий пропущен.']);
        $created=$this->service('service.comment')->createByTaskImported((string)$task['target_public_id'],['body'=>$body,'created_at'=>$payload['date_added']??$payload['created_at']??null,'author_user_id'=>$author],(int)($actor['id']??0));if(!is_array($created)||empty($created['public_id']))throw new RuntimeException('WORKSECTION_COMMENT_CREATE_FAILED');return $this->result('comment',(string)$created['public_id'],'imported');
    }

    public function attachment(array $job, array $payload, array $actor, string $token, int $maxBytes): array
    {
        $workspace=(string)$job['workspace_gid'];$source=$this->id($payload['id']??$payload['file_id']??null);$mapping=$this->map($job,'attachment',$source);if($mapping&&!empty($mapping['target_public_id']))return $this->result('file',(string)$mapping['target_public_id'],'skipped');
        $task=$this->map($job,'task',$this->id($payload['_task_id']??$payload['task_id']??null));if(!$task||empty($task['target_public_id']))throw new RuntimeException('WORKSECTION_ATTACHMENT_TASK_NOT_READY');
        $fileId=$this->id($payload['id']??$payload['file_id']??null);
        if ($fileId === '') return $this->result('file','skipped','skipped',['У вложения нет идентификатора для скачивания.']);
        $download=$this->client->downloadFile($token,$fileId,$maxBytes);$path=(string)$download['path'];
        try{$content=file_get_contents($path);if(!is_string($content))throw new RuntimeException('WORKSECTION_ATTACHMENT_READ_FAILED');$created=$this->service('service.file')->create(['entity_type'=>'task','entity_public_id'=>(string)$task['target_public_id'],'name'=>$this->fileName((string)($payload['name']??$payload['filename']??'attachment.bin')),'mime_type'=>(string)($download['mime_type']??$payload['mime_type']??'application/octet-stream'),'content_base64'=>base64_encode($content)],[],(int)($actor['id']??0),$actor);if(!is_array($created)||empty($created['public_id']))throw new RuntimeException('WORKSECTION_FILE_CREATE_FAILED');return $this->result('file',(string)$created['public_id'],'imported');}finally{@unlink($path);}
    }

    public function timeRecord(array $job, array $payload, array $actor): array
    {
        $workspace=(string)$job['workspace_gid'];$source=$this->id($payload['id']??$payload['cost_id']??null);$mapping=$this->map($job,'time_record',$source);if($mapping&&!empty($mapping['target_public_id']))return $this->result('worklog',(string)$mapping['target_public_id'],'skipped');
        $userSource=$this->id($payload['user']??$payload['user_from']??$payload['user_id']??null);
        if ($userSource === '' && is_array($payload['user_from'] ?? null)) $userSource = $this->id($payload['user_from']['id'] ?? null);
        $userPublic=$userSource!==''?$this->repo->mappedUserPublicId((int)$job['connection_id'],$userSource):null;if($userPublic===null)throw new RuntimeException('WORKSECTION_TIME_RECORD_USER_UNMAPPED');
        $taskId=$this->id($payload['_task_id']??$payload['task_id']??null);if($taskId===''&&is_array($payload['task']??null))$taskId=$this->id($payload['task']['id']??null);
        $task=$taskId!==''?$this->map($job,'task',$taskId):null;
        if ($taskId !== '' && (!$task || empty($task['target_public_id']))) throw new RuntimeException('WORKSECTION_TIME_RECORD_TASK_NOT_READY');
        $minutes=$this->minutes($payload);if($minutes<=0)return $this->result('worklog','skipped','skipped',['Нулевая запись времени пропущена.']);
        $date=$this->date($payload['date']??$payload['date_added']??$payload['created_at']??null)??gmdate('Y-m-d H:i:s');
        $note=trim((string)($payload['comment']??$payload['description']??$payload['note']??''));
        $note=mb_substr('[Worksection] '.($note!==''?$note:'time record'),0,65000);
        $input=['user_public_id'=>$userPublic,'minutes_spent'=>$minutes,'note'=>$note,'logged_at'=>$date,'started_at'=>$date];
        if ($task !== null && !empty($task['target_public_id'])) $input['task_public_id']=(string)$task['target_public_id'];
        $created=$this->service('service.worklog')->create($input,$actor);if(!is_array($created)||empty($created['public_id']))throw new RuntimeException('WORKSECTION_TIME_RECORD_CREATE_FAILED');return $this->result('worklog',(string)$created['public_id'],'imported');
    }

    private function result(string $type,string $target,string $state,array $warnings=[]): array { return ['target_type'=>$type,'target_public_id'=>$target,'state'=>$state,'warnings'=>$warnings]; }
    private function id(mixed $value): string { if(is_array($value))$value=$value['id']??$value['user_id']??$value['task_id']??'';return is_scalar($value)?trim((string)$value):''; }
    private function title(array $p,string $fallback): string { return mb_substr(trim((string)($p['name']??$p['title']??$fallback))?:$fallback,0,255); }
    private function description(array $p): string { return trim((string)($p['text']??$p['description']??$p['body']??'')); }
    private function active(array $p): bool { foreach(['is_archived','archived','is_trashed','deleted'] as $k)if(array_key_exists($k,$p)&&$this->bool($p[$k])===true)return false;return true; }
    private function status(array $p): string { if(!$this->active($p))return 'archived';if(!empty($p['is_completed'])||in_array(strtolower((string)($p['status']??'')),['done','closed','completed'],true))return 'done';return 'new'; }
    private function priority(array $p): string { $v=strtolower((string)($p['priority']??$p['priority_name']??''));return match($v){'urgent','critical','highest','high'=>'high','low','lowest'=>'low',default=>'normal'}; }
    private function bool(mixed $v): ?bool { if(is_bool($v))return$v;if(is_numeric($v))return(int)$v!==0;if(is_string($v))return match(strtolower(trim($v))){ '1','true','yes','on'=>true,'0','false','no','off'=>false,default=>null};return null; }
    private function date(mixed $v): ?string { if(!is_scalar($v)||trim((string)$v)==='')return null;if(is_numeric($v)&&(int)$v>=100000000)return gmdate('Y-m-d H:i:s',(int)$v);$t=strtotime((string)$v);return$t===false?null:gmdate('Y-m-d H:i:s',$t); }
    private function color(string $v): string { return preg_match('/^#[0-9a-f]{6}$/i',$v)?$v:'#64748b'; }
    private function labels(array $p): array { foreach(['tags','labels'] as $k)if(isset($p[$k])&&is_array($p[$k]))return array_values(array_filter(array_map(static fn(mixed$v):array=>is_array($v)?$v:['id'=>(string)$v,'name'=>(string)$v],$p[$k])));return[]; }
    private function minutes(array $p): int { $value=$p['time']??$p['hours']??$p['minutes']??null;if(is_string($value)&&preg_match('/^(\d+)\s*:\s*([0-5]\d)$/',trim($value),$m))return((int)$m[1]*60)+(int)$m[2];if(is_numeric($value))return max(0,(int)round((float)$value*60));return 0; }
    private function fileName(string $name): string { $name=basename(str_replace('\\','/',$name));return trim($name)!==''?$name:'attachment.bin'; }
}
