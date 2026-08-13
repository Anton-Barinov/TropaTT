<?php
declare(strict_types=1);

namespace Module\Crm\AsanaMigration\Service;

use Api\System\Library\Container;
use Module\Crm\AsanaMigration\Repository\AsanaMigrationRepository;
use RuntimeException;

final class AsanaTargetWriter
{
    public function __construct(
        private readonly Container $container,
        private readonly AsanaMigrationRepository $repo,
        private readonly AsanaClient $client,
    ) {
    }

    public function service(string $id): mixed { return $this->container->get($id); }

    /** @return array{target_type:string,target_public_id:string,state:string,warnings:array<int,string>} */
    public function project(array $job, array $payload, array $actor): array
    {
        $source = (string)($payload['gid'] ?? ''); $workspace = (string)$job['workspace_gid'];
        $mapping = $this->repo->findMapping((int)$job['connection_id'], $workspace, 'project', $source); $warnings = [];
        if ($mapping && !empty($mapping['target_public_id'])) {
            $existing = $this->service('service.project')->get((string)$mapping['target_public_id'], $actor);
            if ($existing) {
                if (($job['mode'] ?? 'import') !== 'sync') return ['target_type'=>'project','target_public_id'=>(string)$mapping['target_public_id'],'state'=>'skipped','warnings'=>[]];
                $updated = $this->service('service.project')->update((string)$mapping['target_public_id'], ['title'=>$this->title($payload),'description'=>$this->description($payload)], $actor);
                return ['target_type'=>'project','target_public_id'=>(string)$mapping['target_public_id'],'state'=>is_array($updated)?'updated':'warning','warnings'=>is_array($updated)?[]:['Project update failed.']];
            }
            $warnings[] = 'Stored project mapping no longer resolves; a new project was created.';
        }
        $prefix = 'AS' . strtoupper(substr(hash('sha256', $source), 0, 4));
        $created = $this->service('service.project')->create(['title'=>$this->title($payload),'description'=>$this->description($payload),'status'=>(!empty($payload['archived'])?'archived':'active'),'priority'=>'normal','task_key_prefix'=>$prefix], $actor);
        if (!is_array($created) || empty($created['public_id'])) throw new RuntimeException('ASANA_PROJECT_CREATE_FAILED');
        return ['target_type'=>'project','target_public_id'=>(string)$created['public_id'],'state'=>'imported','warnings'=>$warnings];
    }

    /** @return array{target_type:string,target_public_id:string,state:string,warnings:array<int,string>} */
    public function section(array $job, array $payload, array $actor): array
    {
        $source=(string)($payload['gid']??''); $workspace=(string)$job['workspace_gid'];
        $mapping=$this->repo->findMapping((int)$job['connection_id'],$workspace,'section',$source);
        if($mapping && !empty($mapping['target_public_id'])) return ['target_type'=>'project_module','target_public_id'=>(string)$mapping['target_public_id'],'state'=>'skipped','warnings'=>[]];
        $projectGid=(string)($payload['_source_project_gid']??'');
        if($projectGid==='') throw new RuntimeException('ASANA_SECTION_PROJECT_REQUIRED');
        $project=$this->repo->findMapping((int)$job['connection_id'],$workspace,'project',$projectGid);
        if(!$project || empty($project['target_public_id'])) throw new RuntimeException('ASANA_SECTION_PROJECT_NOT_READY');
        $created=$this->service('service.project_module')->create(['project_public_id'=>$project['target_public_id'],'title'=>trim((string)($payload['name']??'Untitled')),'description'=>'Imported from Asana section '.$source,'status'=>'planned','sort_order'=>0],$actor);
        if(!is_array($created)||empty($created['public_id'])) throw new RuntimeException('ASANA_SECTION_CREATE_FAILED');
        return ['target_type'=>'project_module','target_public_id'=>(string)$created['public_id'],'state'=>'imported','warnings'=>[]];
    }

    /** @return array{target_type:string,target_public_id:string,state:string,warnings:array<int,string>} */
    public function tag(array $job, array $payload): array
    {
        $source=(string)($payload['gid']??''); $workspace=(string)$job['workspace_gid']; $mapping=$this->repo->findMapping((int)$job['connection_id'],$workspace,'tag',$source);
        if($mapping && !empty($mapping['target_public_id'])) return ['target_type'=>'tag','target_public_id'=>(string)$mapping['target_public_id'],'state'=>'skipped','warnings'=>[]];
        $code='asana_'.substr(hash('sha256',$workspace.':'.$source),0,24);
        $created=$this->service('service.tag')->create(['code'=>$code,'title'=>trim((string)($payload['name']??'Asana tag')),'color'=>$this->color((string)($payload['color']??'')),'description'=>'Imported from Asana tag '.$source]);
        if($created==='TAG_CODE_EXISTS'){ $list=$this->service('service.tag')->list(['search'=>$code,'limit'=>5]); $created=$list['items'][0]??null; }
        if(!is_array($created)||empty($created['public_id'])) throw new RuntimeException('ASANA_TAG_CREATE_FAILED');
        return ['target_type'=>'tag','target_public_id'=>(string)$created['public_id'],'state'=>'imported','warnings'=>[]];
    }

    /** @return array{target_type:string,target_public_id:string,state:string,warnings:array<int,string>} */
    public function task(array $job, array $payload, array $actor): array
    {
        $source=(string)($payload['gid']??''); $workspace=(string)$job['workspace_gid']; $mapping=$this->repo->findMapping((int)$job['connection_id'],$workspace,'task',$source); $warnings=[];
        $projectGid=(string)($payload['_source_project_gid']??''); $project=$this->repo->findMapping((int)$job['connection_id'],$workspace,'project',$projectGid);
        if(!$project || empty($project['target_public_id'])) throw new RuntimeException('ASANA_TASK_PROJECT_NOT_READY');
        $assignee=null; if(is_array($payload['assignee']??null)&&!empty($payload['assignee']['gid'])) $assignee=$this->repo->mappedUserId((int)$job['connection_id'],(string)$payload['assignee']['gid']);
        $input=['project_public_id'=>(string)$project['target_public_id'],'title'=>trim((string)($payload['name']??'Untitled')),'description'=>(string)($payload['html_notes']??$payload['notes']??''),'status'=>!empty($payload['completed'])?'completed':'new','priority'=>'normal','due_at'=>$this->date((string)($payload['due_at']??''))?:$this->date((string)($payload['due_on']??'')),'start_at'=>$this->date((string)($payload['start_at']??''))?:$this->date((string)($payload['start_on']??'')),'assignee_user_id'=>$assignee,'source_type'=>'asana','source_id'=>$source,'source_url'=>(string)($payload['permalink_url']??''),'source_payload_json'=>$payload,'created_at'=>$this->date((string)($payload['created_at']??'')),'updated_at'=>$this->date((string)($payload['modified_at']??''))];
        $parent=(string)($payload['_source_parent_gid']??''); if($parent!==''){ $parentMapping=$this->repo->findMapping((int)$job['connection_id'],$workspace,'task',$parent); if(!empty($parentMapping['target_public_id'])) $input['parent_task_public_id']=(string)$parentMapping['target_public_id']; else $warnings[]='Parent task mapping is not ready.'; }
        $taskService=$this->service('service.task');
        if($mapping && !empty($mapping['target_public_id'])){
            $target=(string)$mapping['target_public_id'];
            if(($job['mode']??'import')==='sync'){ $updated=$taskService->update($target,$input,(int)($actor['id']??0),$actor); if(!is_array($updated))$warnings[]='Task update failed.'; $state='updated'; } else $state='skipped';
        } else { $created=$taskService->create($input,$actor); if(!is_array($created)||empty($created['public_id'])) throw new RuntimeException(is_string($created)?'ASANA_'.$created:'ASANA_TASK_CREATE_FAILED'); $target=(string)$created['public_id']; $state='imported'; }
        foreach((array)($payload['tags']??[]) as $tag){$tagGid=(string)($tag['gid']??'');$tagMapping=$this->repo->findMapping((int)$job['connection_id'],$workspace,'tag',$tagGid);if(!empty($tagMapping['target_public_id'])){try{$this->service('service.tag')->attachToTask($target,(string)$tagMapping['target_public_id'],$actor);}catch(\Throwable){$warnings[]='Tag attachment failed.';}}}
        $sectionGid=$this->sectionGid($payload); if($sectionGid!==''){ $sectionMapping=$this->repo->findMapping((int)$job['connection_id'],$workspace,'section',$sectionGid); if(!empty($sectionMapping['target_public_id'])){try{$this->service('service.project_module')->addTasks((string)$sectionMapping['target_public_id'],['task_public_ids'=>[$target]],$actor);}catch(\Throwable){$warnings[]='Task could not be added to its project module.';}} }
        return ['target_type'=>'task','target_public_id'=>$target,'state'=>$state,'warnings'=>$warnings];
    }

    /** @return array{target_type:string,target_public_id:string,state:string,warnings:array<int,string>} */
    public function dependency(array $job, array $payload, array $actor): array
    {
        $source = (string)($payload['source_task_gid'] ?? '') . ':' . (string)($payload['depends_on_task_gid'] ?? '');
        $workspace = (string)$job['workspace_gid'];
        $mapping = $this->repo->findMapping((int)$job['connection_id'], $workspace, 'dependency', $source);
        if ($mapping && !empty($mapping['target_public_id'])) return ['target_type' => 'dependency', 'target_public_id' => (string)$mapping['target_public_id'], 'state' => 'skipped', 'warnings' => []];
        $task = $this->repo->findMapping((int)$job['connection_id'], $workspace, 'task', (string)($payload['source_task_gid'] ?? ''));
        $dependsOn = $this->repo->findMapping((int)$job['connection_id'], $workspace, 'task', (string)($payload['depends_on_task_gid'] ?? ''));
        if (!$task || empty($task['target_public_id']) || !$dependsOn || empty($dependsOn['target_public_id'])) throw new RuntimeException('ASANA_DEPENDENCY_TASK_NOT_READY');
        $created = $this->service('service.dependency')->create([
            'task_public_id' => (string)$task['target_public_id'],
            'depends_on_task_public_id' => (string)$dependsOn['target_public_id'],
            'dependency_type' => (string)($payload['dependency_type'] ?? 'FS'),
        ], $actor);
        if (!is_array($created) || empty($created['public_id'])) throw new RuntimeException('ASANA_DEPENDENCY_CREATE_FAILED');
        return ['target_type' => 'dependency', 'target_public_id' => (string)$created['public_id'], 'state' => 'imported', 'warnings' => []];
    }

    /** @return array{target_type:string,target_public_id:string,state:string,warnings:array<int,string>} */
    public function comment(array $job,array $payload,array $actor): array
    {
        $source=(string)($payload['gid']??'');$workspace=(string)$job['workspace_gid'];$mapping=$this->repo->findMapping((int)$job['connection_id'],$workspace,'comment',$source); if($mapping&& !empty($mapping['target_public_id']))return ['target_type'=>'comment','target_public_id'=>(string)$mapping['target_public_id'],'state'=>'skipped','warnings'=>[]];
        $taskGid=(string)($payload['_source_task_gid']??'');$task=$this->repo->findMapping((int)$job['connection_id'],$workspace,'task',$taskGid);if(!$task||empty($task['target_public_id']))throw new RuntimeException('ASANA_COMMENT_TASK_NOT_READY');
        $created=$this->service('service.comment')->createByTaskImported((string)$task['target_public_id'],['body'=>(string)($payload['html_text']??$payload['text']??''),'created_at'=>$payload['created_at']??null,'author_user_id'=>$this->commentAuthorId((int)$job['connection_id'],$payload,(int)($actor['id']??0))],(int)($actor['id']??0));
        if(!is_array($created)||empty($created['public_id']))throw new RuntimeException('ASANA_COMMENT_CREATE_FAILED');
        return ['target_type'=>'comment','target_public_id'=>(string)$created['public_id'],'state'=>'imported','warnings'=>[]];
    }

    /** @return array{target_type:string,target_public_id:string,state:string,warnings:array<int,string>} */
    public function attachment(array $job,array $payload,array $actor,string $token,int $maxBytes): array
    {
        $source=(string)($payload['gid']??'');$workspace=(string)$job['workspace_gid'];$mapping=$this->repo->findMapping((int)$job['connection_id'],$workspace,'attachment',$source);if($mapping&& !empty($mapping['target_public_id']))return ['target_type'=>'file','target_public_id'=>(string)$mapping['target_public_id'],'state'=>'skipped','warnings'=>[]];
        $taskGid=(string)($payload['_source_task_gid']??'');$task=$this->repo->findMapping((int)$job['connection_id'],$workspace,'task',$taskGid);if(!$task||empty($task['target_public_id']))throw new RuntimeException('ASANA_ATTACHMENT_TASK_NOT_READY');
        $url=(string)($payload['download_url']??'');if($url==='')return ['target_type'=>'file','target_public_id'=>'','state'=>'skipped','warnings'=>['Attachment has no download URL.']];
        $download=$this->client->downloadAttachment($token,$url,$maxBytes);$path=(string)$download['path'];
        try{$content=file_get_contents($path);if(!is_string($content))throw new RuntimeException('ASANA_ATTACHMENT_READ_FAILED');$created=$this->service('service.file')->create(['entity_type'=>'task','entity_public_id'=>(string)$task['target_public_id'],'name'=>trim((string)($payload['name']??'attachment.bin')),'mime_type'=>(string)($download['mime_type']??'application/octet-stream'),'content_base64'=>base64_encode($content)],[],(int)($actor['id']??0),$actor);if(!is_array($created)||empty($created['public_id']))throw new RuntimeException('ASANA_FILE_CREATE_FAILED');return ['target_type'=>'file','target_public_id'=>(string)$created['public_id'],'state'=>'imported','warnings'=>[]];}finally{@unlink($path);}
    }

    private function sectionGid(array $payload): string { foreach((array)($payload['memberships']??[]) as $membership){if(is_array($membership)&&is_array($membership['section']??null)&&!empty($membership['section']['gid']))return (string)$membership['section']['gid'];}return ''; }
    private function commentAuthorId(int $connection,array $payload,int $fallback): int { $author=$payload['created_by']??null;return is_array($author)&&!empty($author['gid'])?($this->repo->mappedUserId($connection,(string)$author['gid'])??$fallback):$fallback; }
    private function title(array $payload): string { return trim((string)($payload['name']??'Untitled')); }
    private function description(array $payload): string { return trim((string)($payload['notes']??'')); }
    private function date(string $value): ?string { if($value==='')return null;$time=strtotime($value);return $time===false?null:gmdate('Y-m-d H:i:s',$time); }
    private function color(string $value): string { return preg_match('/^#[0-9a-f]{6}$/i',$value)?$value:'#64748b'; }
}
