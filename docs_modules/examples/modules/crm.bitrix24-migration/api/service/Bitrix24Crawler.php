<?php
declare(strict_types=1);

namespace Module\Crm\Bitrix24Migration\Service;

use Module\Crm\Bitrix24Migration\Repository\Bitrix24MigrationRepository;
use RuntimeException;

final class Bitrix24Crawler
{
    private const CRM_COMMENT_TYPES = ['company','contact','lead','deal','invoice','quote'];

    public function __construct(private readonly Bitrix24Client $client, private readonly Bitrix24MigrationRepository $repo) {}

    /** @return array<string,mixed> */
    public function crawl(array $job, ?callable $heartbeat = null): array
    {
        $scope=(array)($job['source_scope']??[]);$options=(array)($job['target_options']??[]);
        $types=array_values(array_filter(array_map('strval',(array)($scope['entities']??['department','user','company','contact','lead','deal','project','task']))));
        $includeComments=(bool)($options['include_comments']??$scope['include_comments']??true);$includeFiles=(bool)($options['include_files']??$scope['include_files']??false);$includeProducts=(bool)($options['include_products']??$scope['include_products']??false);$includeArchived=(bool)($options['include_archived']??$scope['include_archived']??false);$eventsFrom=trim((string)($options['events_from']??$scope['events_from']??''));$eventsTo=trim((string)($options['events_to']??$scope['events_to']??''));
        $stats=['entities'=>[],'comments'=>0,'files'=>0,'product_rows'=>0,'warnings'=>[]];$eventOwnerIds=[0];if(in_array('event',$types,true)){try{$eventOwnerIds=array_values(array_filter(array_map(static fn(array $user):int=>(int)($user['ID']??$user['id']??0),$this->client->users()),static fn(int $id):bool=>$id>0));if($eventOwnerIds===[])$eventOwnerIds=[0];}catch(\Throwable $e){$stats['warnings'][]='Event owners unavailable: '.$this->safeError($e);}}
        if (array_intersect($types, ['task','deal','invoice','quote','activity','event']) !== []) {
            $synthetic=['ID'=>'__bitrix24_tasks__','TITLE'=>'Битрикс24: импортированные задачи и CRM-активности','DESCRIPTION'=>'Служебный проект для сущностей Битрикс24 без отдельного проекта.','_source_type'=>'task_project'];
            $this->store($job,'task_project',$synthetic,'__bitrix24_tasks__');
        }
        $collections=[
            'department'=>fn():array=>$this->client->departments(),'user'=>fn():array=>$this->client->users(),'company'=>fn():array=>$this->client->companies(),'contact'=>fn():array=>$this->client->contacts(),'lead'=>fn():array=>$this->client->leads(),'deal'=>fn():array=>$this->client->deals(),'invoice'=>fn():array=>$this->client->invoices(),'quote'=>fn():array=>$this->client->quotes(),'product'=>fn():array=>$this->client->products(),'project'=>fn():array=>$this->client->projects($includeArchived),'task'=>fn():array=>$this->client->tasks(),'activity'=>fn():array=>$this->client->activities(),'event'=>fn():array=>$this->client->events($eventsFrom!==''?$eventsFrom:null,$eventsTo!==''?$eventsTo:null,$eventOwnerIds),'file'=>fn():array=>$this->client->files(),
        ];
        foreach($types as $type){
            if($heartbeat!==null&&!$heartbeat())throw new RuntimeException('BITRIX24_JOB_LEASE_LOST');
            if(!isset($collections[$type])){$stats['warnings'][]='Unknown entity type skipped: '.$type;continue;}
            try{$rows=$collections[$type]();if($type==='task')$rows=$this->sortTasksParentFirst($rows);$stats['entities'][$type]=count($rows);foreach($rows as $row){$this->store($job,$type,$row);$id=$this->sourceId($row);if($id==='' )continue;
                    if($includeComments&&in_array($type,self::CRM_COMMENT_TYPES,true))$this->crawlComments($job,$type,$id,$stats);
                    if($type==='task'&&$includeComments)$this->crawlTaskComments($job,$id,$stats);
                    if($includeProducts&&in_array($type,['deal','invoice','quote'],true))$this->crawlProductRows($job,$type,$id,$stats);
                }}catch(\Throwable $e){$stats['warnings'][]=$type.' collection failed: '.$this->safeError($e);$this->repo->addLog((int)$job['id'],'warning','crawl','Bitrix24 collection failed.',['source_type'=>$type]);}
        }
        if($includeFiles&&in_array('file',$types,true)===false){try{$rows=$this->client->files();$stats['entities']['file']=count($rows);foreach($rows as $row){$this->store($job,'file',$row);$stats['files']++;}}catch(\Throwable $e){$stats['warnings'][]='File collection failed: '.$this->safeError($e);}}
        return$stats;
    }

    private function store(array $job,string $type,array $row,?string $forcedId=null,?string $parent=null): void
    {
        $id=$forcedId??$this->sourceId($row);if($id==='')return;
        $row['_source_id']=$id;$row['_source_type']=$type;if($parent!==null)$row['_source_parent_id']=$parent;
        if($type==='user')$this->repo->upsertUserMapping((int)$job['connection_id'],$row);
        if($type==='task'){$parentId=(string)($row['PARENT_ID']??$row['parentId']??'');$type=$parentId!==''?'subtask':'task';$row['_source_type']=$type;$row['_source_parent_id']=$parentId!==''?$parentId:null;}
        $this->repo->upsertItem((int)$job['id'],$type,$id,['source_parent_id'=>$row['_source_parent_id']??null,'status'=>'pending','checksum'=>$this->checksum($row),'source_updated_at'=>$this->date($row['DATE_MODIFY']??$row['CHANGED_DATE']??$row['dateModify']??''),'payload_json'=>$this->payloadForStorage($row,$type)]);
    }
    private function crawlComments(array $job,string $type,string $id,array &$stats): void
    {
        try{foreach($this->client->comments($type,$id) as $comment){$comment['_source_parent_type']=$type;$comment['_source_parent_id']=$id;$this->store($job,'timeline_comment',$comment,(string)($comment['ID']??$comment['id']??''),$type.':'.$id);$stats['comments']++;$this->storeCommentFiles($job,$comment,$stats);}}catch(\Throwable $e){$stats['warnings'][]='Comments unavailable for '.$type.' '.$id.': '.$this->safeError($e);}
    }
    private function crawlTaskComments(array $job,string $id,array &$stats): void
    {
        try{foreach($this->client->taskComments($id) as $comment){$comment['_source_parent_type']='task';$comment['_source_parent_id']=$id;$commentId=(string)($comment['ID']??$comment['id']??'');$this->store($job,'comment',$comment,$commentId,$id);$stats['comments']++;$this->storeCommentFiles($job,$comment,$stats);}}catch(\Throwable $e){$stats['warnings'][]='Task comments unavailable for '.$id.': '.$this->safeError($e);}
    }
    private function storeCommentFiles(array $job,array $comment,array &$stats): void
    {
        $options=(array)($job['target_options']??[]);if(!(bool)($options['include_files']??$options['includeFiles']??false))return;foreach((array)($comment['FILES']??$comment['files']??$comment['ATTACHED_OBJECTS']??$comment['attached_objects']??[]) as $key=>$file){if(!is_array($file))continue;$file['_source_parent_id']=(string)($comment['ID']??$comment['id']??'');$file['_download_url']=(string)($file['DOWNLOAD_URL']??$file['download_url']??$file['urlDownload']??'');$this->store($job,'file',$file,(string)($file['id']??$file['ID']??$comment['ID'].'-file-'.$key),(string)($comment['ID']??$comment['id']??''));$stats['files']++;}
    }
    private function crawlProductRows(array $job,string $type,string $id,array &$stats): void
    {
        $ownerType=$type==='deal'?'D':($type==='invoice'?'I':'Q');try{foreach($this->client->productRows($ownerType,$id) as $idx=>$row){$row['_owner_type']=$type;$row['_owner_id']=$id;$this->store($job,'product_row',$row,$type.':'.$id.':'.($row['id']??$idx),$type.':'.$id);$stats['product_rows']++;}}catch(\Throwable $e){$stats['warnings'][]='Product rows unavailable for '.$type.' '.$id.': '.$this->safeError($e);}
    }
    private function sortTasksParentFirst(array $rows): array
    {
        $parents=[];$records=[];
        foreach($rows as $index=>$row){$id=$this->sourceId($row);$parent=trim((string)($row['PARENT_ID']??$row['parentId']??''));if($id!=='')$parents[$id]=$parent;$records[]=['row'=>$row,'index'=>$index,'id'=>$id,'parent'=>$parent];}
        $depths=[];
        foreach($records as $record){$current=$record['id'];$seen=[];$depth=0;while($current!==''&&!isset($seen[$current])&&isset($parents[$current])&&$parents[$current]!==''){$seen[$current]=true;$current=$parents[$current];++$depth;if($depth>=1000)break;}$depths[$record['id']]=$depth;}
        usort($records,static function(array $a,array $b)use($depths):int{$depthCompare=($depths[$a['id']]??0)<=>($depths[$b['id']]??0);return$depthCompare!==0?$depthCompare:$a['index']<=>$b['index'];});
        return array_values(array_map(static fn(array $record):array=>$record['row'],$records));
    }
    private function payloadForStorage(array $row,string $type): array
    {
        if($type!=='file')return$row;
        foreach(['DOWNLOAD_URL','download_url','urlDownload','_download_url','URL','url'] as $key){$value=trim((string)($row[$key]??''));if($value!==''&&$this->isEphemeralFileUrl($value))unset($row[$key]);}
        return$row;
    }
    private function isEphemeralFileUrl(string $url): bool
    {
        $path=(string)(parse_url($url,PHP_URL_PATH)??'');$query=(string)(parse_url($url,PHP_URL_QUERY)??'');
        if(str_contains(strtolower($path),'/rest/download'))return true;
        $parsed=[];parse_str($query,$parsed);
        foreach(array_keys($parsed) as $key){if(in_array(strtolower((string)$key),['auth','token','access_token','refresh_token','signature','sig','expires','x-amz-signature','x-amz-credential'],true))return true;}
        return false;
    }
    private function sourceId(array $row): string { return trim((string)($row['ID']??$row['id']??$row['ID']??$row['GROUP_ID']??'')); }
    private function checksum(array $row): string { return hash('sha256',(string)json_encode($row,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_PRESERVE_ZERO_FRACTION)); }
    private function date(mixed $value): ?string {$v=trim((string)$value);if($v==='')return null;$ts=strtotime($v);return$ts===false?null:gmdate('Y-m-d H:i:s',$ts);}
    private function safeError(\Throwable $e): string { $message=$e->getMessage();return preg_replace('/(access_token|refresh_token|auth|token|secret)[^\\s]*/i', '[redacted]', $message)??'request failed'; }
}
