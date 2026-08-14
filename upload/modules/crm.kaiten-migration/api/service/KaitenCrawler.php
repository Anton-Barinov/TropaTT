<?php
declare(strict_types=1);

namespace Module\Crm\KaitenMigration\Service;

use Module\Crm\KaitenMigration\Repository\KaitenMigrationRepository;
use RuntimeException;

final class KaitenCrawler
{
    public function __construct(private readonly KaitenClient $client, private readonly KaitenMigrationRepository $repo) {}

    /** @return array<string,mixed> */
    public function crawl(array $job, string $token, ?callable $heartbeat = null): array
    {
        $scope=(array)($job['source_scope']??[]);$options=(array)($job['target_options']??[]);$includeArchived=(bool)($scope['include_archived']??$options['include_archived']??false);$includeComments=(bool)($options['include_comments']??true);$includeAttachments=(bool)($options['include_attachments']??false);$includeHistory=(bool)($options['include_history']??false);$maxCards=max(0,(int)($scope['max_cards']??0));$selectedSpaces=array_values(array_filter(array_map('strval',(array)($scope['space_ids']??[]))));$selectedBoards=array_values(array_filter(array_map('strval',(array)($scope['board_ids']??[]))));$stats=['spaces'=>0,'boards'=>0,'columns'=>0,'cards'=>0,'subcards'=>0,'comments'=>0,'attachments'=>0,'history'=>0,'tags'=>0,'users'=>0,'custom_fields'=>0,'warnings'=>[]];
        foreach($this->client->users($token) as $user){if($this->sourceId($user)!==''){$this->repo->upsertUserMapping((int)$job['connection_id'],$user);$stats['users']++;}}
        foreach($this->client->tags($token) as $tag){$id=$this->sourceId($tag);if($id==='')continue;$this->repo->upsertItem((int)$job['id'],'tag',$id,['status'=>'pending','checksum'=>$this->checksum($tag),'payload_json'=>$tag]);$stats['tags']++;}
        foreach($this->client->customFields($token) as $field){$id=$this->sourceId($field);if($id==='')continue;$this->repo->upsertItem((int)$job['id'],'custom_field',$id,['status'=>'pending','checksum'=>$this->checksum($field),'payload_json'=>$field]);$stats['custom_fields']++;}
        foreach($this->client->spaces($token,$includeArchived) as $space){if($heartbeat!==null&&!$heartbeat())throw new RuntimeException('KAITEN_JOB_LEASE_LOST');$spaceId=$this->sourceId($space);if($spaceId===''||($selectedSpaces!==[]&&!in_array($spaceId,$selectedSpaces,true)))continue;$stats['spaces']++;$this->repo->upsertItem((int)$job['id'],'space',$spaceId,['status'=>'pending','checksum'=>$this->checksum($space),'payload_json'=>$space]);
            foreach($this->client->boards($token,$spaceId,$includeArchived) as $board){if($heartbeat!==null&&!$heartbeat())throw new RuntimeException('KAITEN_JOB_LEASE_LOST');$boardId=$this->sourceId($board);if($boardId===''||($selectedBoards!==[]&&!in_array($boardId,$selectedBoards,true)))continue;$stats['boards']++;$board['__space_id']=$spaceId;$this->repo->upsertItem((int)$job['id'],'board',$boardId,['source_parent_id'=>$spaceId,'source_project_id'=>$spaceId,'status'=>'pending','checksum'=>$this->checksum($board),'payload_json'=>$board]);
                foreach($this->client->columns($token,$boardId) as $column){$columnId=$this->sourceId($column);if($columnId==='')continue;$this->repo->upsertItem((int)$job['id'],'column',$columnId,['source_parent_id'=>$boardId,'source_project_id'=>$spaceId,'status'=>'pending','checksum'=>$this->checksum($column),'payload_json'=>$column]);$stats['columns']++;}
                try{$this->client->eachCards($token,['board_id'=>$boardId,'condition'=>$includeArchived?'1,2':'1'],function(array $card)use($job,$token,$spaceId,$boardId,$includeComments,$includeAttachments,$includeHistory,$heartbeat,$maxCards,&$stats):bool{if($maxCards>0&&$stats['cards']>=$maxCards)return false;if($heartbeat!==null&&!$heartbeat())throw new RuntimeException('KAITEN_JOB_LEASE_LOST');$cardId=$this->sourceId($card);if($cardId==='')return true;$parentId=$this->parentId($card);$type=$parentId!==''?'subcard':'card';$card['__space_id']=$spaceId;$card['__board_id']=$boardId;$this->repo->upsertItem((int)$job['id'],$type,$cardId,['source_parent_id'=>$parentId!==''?$parentId:$boardId,'source_project_id'=>$spaceId,'source_updated_at'=>$this->date((string)($card['updated_at']??$card['updatedAt']??'')),'status'=>'pending','checksum'=>$this->checksum($card),'payload_json'=>$card]);$stats['cards']++;if($type==='subcard')$stats['subcards']++;
                    if($includeComments)foreach($this->client->comments($token,$cardId)as$comment){$id=$this->sourceId($comment);if($id==='')continue;$comment['__card_id']=$cardId;$this->repo->upsertItem((int)$job['id'],'comment',$id,['source_parent_id'=>$cardId,'source_project_id'=>$spaceId,'status'=>'pending','checksum'=>$this->checksum($comment),'payload_json'=>$comment]);$stats['comments']++;}
                    if($includeAttachments)foreach($this->client->attachments($token,$cardId)as$file){$id=$this->sourceId($file);if($id==='')continue;$file['__card_id']=$cardId;$this->repo->upsertItem((int)$job['id'],'attachment',$id,['source_parent_id'=>$cardId,'source_project_id'=>$spaceId,'status'=>'pending','checksum'=>$this->checksum($file),'payload_json'=>$file]);$stats['attachments']++;}
                    if($includeHistory)foreach($this->client->history($token,$cardId)as$event){$id=$this->sourceId($event)?:hash('sha256',$cardId.':'.$this->checksum($event));$event['__card_id']=$cardId;$this->repo->upsertItem((int)$job['id'],'history',$id,['source_parent_id'=>$cardId,'source_project_id'=>$spaceId,'status'=>'pending','checksum'=>$this->checksum($event),'payload_json'=>$event]);$stats['history']++;}return!($maxCards>0&&$stats['cards']>=$maxCards);});}catch(\Throwable $e){if(!in_array($e->getMessage(),['KAITEN_NOT_FOUND','KAITEN_HTTP_404'],true))throw$e;$stats['warnings'][]='Board '.$boardId.' cards could not be fully loaded.';$this->repo->addLog((int)$job['id'],'warning','crawl','Kaiten board discovery failed.',['board_id'=>$boardId]);}
            }
        }
        return$stats;
    }
    private function sourceId(array $item):string{return trim((string)($item['id']??$item['uid']??$item['uuid']??''));}
    private function parentId(array $card):string{return trim((string)($card['parent_id']??$card['parentId']??''));}
    private function checksum(array $value):string{return hash('sha256',(string)json_encode($value,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_PRESERVE_ZERO_FRACTION));}
    private function date(string $value):?string{if($value==='')return null;$time=strtotime($value);return$time===false?null:gmdate('Y-m-d H:i:s',$time);}
}
