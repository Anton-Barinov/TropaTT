<?php
declare(strict_types=1);

namespace Module\Crm\ActiveCollabMigration\Service;

use Module\Crm\ActiveCollabMigration\Repository\ActiveCollabMigrationRepository;
use RuntimeException;

final class ActiveCollabImportService
{
    public function __construct(
        private readonly ActiveCollabMigrationRepository $repo,
        private readonly ActiveCollabClient $client,
        private readonly ActiveCollabCrawler $crawler,
        private readonly ActiveCollabTargetWriter $writer,
    ) {
    }

    public function processJob(string $jobPublicId, ?string $leaseToken = null): void
    {
        $job = $this->repo->getJob($jobPublicId);
        if (!$job) return;
        $connection = $this->repo->getConnectionById((int)$job['connection_id']);
        if (!$connection) throw new RuntimeException('ACTIVECOLLAB_CONNECTION_NOT_FOUND');
        $token = EncryptionService::decrypt((string)($connection['access_token_encrypted'] ?? ''));
        if ($token === null) throw new RuntimeException('ACTIVECOLLAB_CREDENTIAL_DECRYPT_FAILED');
        $this->client->setBaseUrl((string)($connection['base_url'] ?? ''));
        $this->client->setConnectionId((int)$connection['id']);
        $heartbeat = $leaseToken !== null ? fn(): bool => $this->repo->heartbeat($jobPublicId, $leaseToken) : null;
        $cursor = json_decode((string)($job['last_source_cursor'] ?? ''), true);
        $resume = is_array($cursor) && ($cursor['phase'] ?? '') === 'import' && $this->repo->itemCount((int)$job['id']) > 0;
        if (!$resume) {
            $this->repo->updateProgress($jobPublicId, 'crawl', 0, ['message'=>'Загрузка ActiveCollab'], $leaseToken);
            $crawl = $this->crawler->crawl($job, $token, $heartbeat);
            $this->repo->addLog((int)$job['id'], 'info', 'crawl', 'ActiveCollab graph loaded.', $crawl);
            if (($job['mode'] ?? 'import') !== 'dry_run') $this->repo->updateCursor($jobPublicId, json_encode(['phase'=>'import','priority'=>0,'id'=>0], JSON_UNESCAPED_UNICODE), $leaseToken);
        } else $crawl = ['resumed'=>true,'warnings'=>[]];
        if (($job['mode'] ?? 'import') === 'dry_run') {
            $summary=['crawled'=>$crawl,'items'=>$this->repo->itemCounts((int)$job['id'])];
            $this->repo->updateSummary($jobPublicId,$summary,$leaseToken);
            $this->repo->updateProgress($jobPublicId,'dry_run_complete',100,$summary,$leaseToken);
            $this->repo->updateJobStatus($jobPublicId,'completed_with_warnings',$leaseToken);
            return;
        }
        $actor=$this->repo->actor((int)$job['created_by_user_id']);
        $total=max(1,$this->repo->itemCount((int)$job['id']));
        $counts=$this->repo->itemCounts((int)$job['id']);
        $done=array_sum(array_map('intval',array_intersect_key($counts,array_flip(['imported','updated','skipped','failed']))));
        $warnings=(array)($crawl['warnings']??[]);
        $cursor=is_array($cursor)&&$resume?$cursor:['phase'=>'import','priority'=>0,'id'=>0];
        $priority=max(0,(int)($cursor['priority']??0));$lastId=max(0,(int)($cursor['id']??0));
        while(($items=$this->repo->importItemsBatch((int)$job['id'],$priority,$lastId,250))!==[]) {
            foreach($items as $item) {
                if($leaseToken!==null&&!$this->repo->heartbeat($jobPublicId,$leaseToken))throw new RuntimeException('ACTIVECOLLAB_JOB_LEASE_LOST');
                $current=$this->repo->getJob($jobPublicId);$state=(string)($current['status']??'');
                if(in_array($state,['pausing','paused','cancelling','cancelled'],true)){if($state==='pausing')$this->repo->updateJobStatus($jobPublicId,'paused',$leaseToken);if($state==='cancelling')$this->repo->updateJobStatus($jobPublicId,'cancelled',$leaseToken);return;}
                $type=(string)$item['source_type'];$payload=json_decode((string)($item['payload_json']??'{}'),true);$payload=is_array($payload)?$payload:[];
                if(in_array($type,['project','task_list','task','subtask'],true))$payload['_source_project_id']=(string)($item['source_project_id']??$payload['project_id']??'');
                if(in_array($type,['task','subtask'],true))$payload['_source_parent_id']=(string)($item['source_parent_id']??'');
                if(in_array($type,['comment','attachment','time_record'],true))$payload['_task_id']=(string)($item['source_parent_id']??$payload['_task_id']??'');
                $mappingType=match($type){'subtask'=>'task','label'=>'label',default=>$type};
                $existing=$this->repo->findMapping((int)$job['connection_id'],(string)$job['workspace_gid'],$mappingType,(string)$item['source_id']);
                if(empty($item['target_public_id'])&&!empty($existing['target_public_id'])){$item['target_public_id']=(string)$existing['target_public_id'];$item['created_by_job']=(int)($existing['created_by_job_id']??0)===(int)$job['id']?1:0;$this->repo->upsertItem((int)$job['id'],$type,(string)$item['source_id'],['target_type'=>$existing['target_type']??null,'target_public_id'=>$item['target_public_id'],'created_by_job'=>$item['created_by_job']]);}
                $writeTransaction = $this->repo->beginWrite();
                try {
                    $result=match($type){
                        'company'=>$this->writer->company($job,$payload,$actor),
                        'project'=>$this->writer->project($job,$payload,$actor),
                        'task_list'=>$this->writer->taskList($job,$payload,$actor),
                        'label'=>$this->writer->tag($job,$payload),
                        'task','subtask'=>$this->writer->task($job,$payload,$actor),
                        'dependency'=>$this->writer->dependency($job,$payload,$actor),
                        'comment'=>$this->writer->comment($job,$payload,$actor),
                        'attachment'=>$this->writer->attachment($job,$payload,$actor,$token,max(1,(int)($job['target_options']['max_attachment_size_mb']??20))*1024*1024),
                        'time_record'=>$this->writer->timeRecord($job,$payload,$actor),
                        default=>['target_type'=>'','target_public_id'=>'','state'=>'skipped','warnings'=>['Unknown source item skipped.']],
                    };
                    $warnings=array_merge($warnings,(array)($result['warnings']??[]));$target=(string)($result['target_public_id']??'');
                    if($target!=='')$this->repo->upsertMapping((int)$job['connection_id'],(string)$job['workspace_gid'],$mappingType,(string)$item['source_id'],['source_parent_id'=>$item['source_parent_id']?:null,'target_type'=>$result['target_type'],'target_public_id'=>$target,'source_checksum'=>$item['checksum']??null,'target_checksum'=>hash('sha256',$target),'created_by_job_id'=>(int)$job['id']]);
                    $this->repo->upsertItem((int)$job['id'],$type,(string)$item['source_id'],['target_type'=>$result['target_type'],'target_public_id'=>$target,'created_by_job'=>$result['state']==='imported'?1:(int)($item['created_by_job']??0),'status'=>$result['state'],'error_code'=>null,'error_message'=>null]);
                    $this->repo->commitWrite($writeTransaction);
                } catch(\Throwable $e) {
                    $this->repo->rollbackWrite($writeTransaction);
                    $this->repo->upsertItem((int)$job['id'],$type,(string)$item['source_id'],['status'=>'failed','attempts'=>(int)($item['attempts']??0)+1,'error_code'=>'IMPORT_FAILED','error_message'=>'Элемент не импортирован; подробности в журнале.']);
                    $this->repo->addLog((int)$job['id'],'error','import_'.$type,'ActiveCollab item import failed.',['source_type'=>$type,'source_id'=>$item['source_id'],'error_code'=>$e->getCode()?:$e->getMessage()]);
                }
                ++$done;$priority=(int)($item['import_priority']??$priority);$lastId=(int)$item['id'];$this->repo->updateProgress($jobPublicId,'import_'.$type,min(99,($done/$total)*100),['processed'=>$done,'total'=>$total,'warnings'=>count($warnings)],$leaseToken);
            }
            $this->repo->updateCursor($jobPublicId,json_encode(['phase'=>'import','priority'=>$priority,'id'=>$lastId],JSON_UNESCAPED_UNICODE),$leaseToken);
        }
        $summary=['crawled'=>$crawl,'items'=>$this->repo->itemCounts((int)$job['id']),'warnings'=>array_values(array_unique($warnings))];$this->repo->updateSummary($jobPublicId,$summary,$leaseToken);$this->repo->updateProgress($jobPublicId,'completed',100,$summary,$leaseToken);$failed=(int)($summary['items']['failed']??0);$this->repo->updateJobStatus($jobPublicId,$failed>0||$summary['warnings']!==[]?'completed_with_warnings':'completed',$leaseToken);$this->repo->addLog((int)$job['id'],'info','completed','ActiveCollab migration completed.',['failed'=>$failed,'warnings'=>count($summary['warnings'])]);
    }

    public function rollback(string $jobPublicId,array $actor): void
    {
        $job=$this->repo->beginRollback($jobPublicId);if(!$job)throw new RuntimeException('ACTIVECOLLAB_ROLLBACK_REQUIRES_TERMINAL_JOB');$lease=(string)($job['lease_token']??'');$warnings=[];
        try{
            $cursor=json_decode((string)($job['last_source_cursor']??''),true);$before=is_array($cursor)?max(1,(int)($cursor['before_id']??PHP_INT_MAX)):PHP_INT_MAX;
            while(($items=$this->repo->rollbackItemsBatch((int)$job['id'],$before,250))!==[]){$batchWarning=false;foreach($items as $item){if(!$this->repo->heartbeat($jobPublicId,$lease))throw new RuntimeException('ACTIVECOLLAB_ROLLBACK_LEASE_LOST');if((int)($item['created_by_job']??0)!==1||empty($item['target_public_id']))continue;try{$type=(string)$item['target_type'];if($this->repo->targetReferencedByOtherJob((int)$job['id'],$type,(string)$item['target_public_id'])){$warnings[]=(string)$item['source_id'];$this->repo->upsertItem((int)$job['id'],(string)$item['source_type'],(string)$item['source_id'],['status'=>'rollback_preserved_shared','error_code'=>'TARGET_SHARED_BY_OTHER_JOB','error_message'=>'Target preserved because another job refers to it.']);continue;}$serviceId=match($type){'company'=>'service.company','project'=>'service.project','project_module'=>'service.project_module','task'=>'service.task','tag'=>'service.tag','dependency'=>'service.dependency','comment'=>'service.comment','file'=>'service.file','worklog'=>'service.worklog',default=>''};if($serviceId==='')continue;$service=$this->writer->service($serviceId);$deleted=$type==='tag'?$service->delete((string)$item['target_public_id']):$service->delete((string)$item['target_public_id'],$actor);if($deleted!==true)throw new RuntimeException('ACTIVECOLLAB_ROLLBACK_TARGET_NOT_DELETED');$this->repo->upsertItem((int)$job['id'],(string)$item['source_type'],(string)$item['source_id'],['status'=>'rolled_back']);}catch(\Throwable $e){$batchWarning=true;$warnings[]=(string)$item['source_id'];$this->repo->upsertItem((int)$job['id'],(string)$item['source_type'],(string)$item['source_id'],['status'=>'rollback_failed','error_code'=>'ROLLBACK_FAILED','error_message'=>'CRM object was not removed.']);$this->repo->addLog((int)$job['id'],'warning','rollback','ActiveCollab target was not removed.',['source_id'=>$item['source_id'],'error_code'=>$e->getMessage()]);}}if($batchWarning)break;$before=min(array_map('intval',array_column($items,'id')));$this->repo->updateCursor($jobPublicId,json_encode(['phase'=>'rollback','before_id'=>$before],JSON_UNESCAPED_UNICODE),$lease);}
            $warnings=array_values(array_unique($warnings));$this->repo->updateSummary($jobPublicId,['rollback_warnings'=>$warnings],$lease);$this->repo->updateProgress($jobPublicId,'rolled_back',100,['warnings'=>count($warnings)],$lease);$this->repo->updateJobStatus($jobPublicId,$warnings===[]?'rolled_back':'rolled_back_with_warnings',$lease);$this->repo->releaseLease($jobPublicId,$lease);
        }catch(\Throwable $e){try{if($this->repo->ownsLease($jobPublicId,$lease)){$this->repo->updateJobStatus($jobPublicId,'rollback_failed',$lease);$this->repo->releaseLease($jobPublicId,$lease);}}catch(\Throwable){}throw $e;}
    }
}
