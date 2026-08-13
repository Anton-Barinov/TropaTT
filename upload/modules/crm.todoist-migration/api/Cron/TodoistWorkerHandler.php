<?php
declare(strict_types=1);

namespace Module\Crm\TodoistMigration\Cron;

use Module\Crm\TodoistMigration\Repository\TodoistMigrationRepository;
use Module\Crm\TodoistMigration\Service\TodoistClient;
use Module\Crm\TodoistMigration\Service\TodoistCrawler;
use Module\Crm\TodoistMigration\Service\TodoistImportService;
use Module\Crm\TodoistMigration\Service\TodoistTargetWriter;

final class TodoistWorkerHandler
{
    public static function run(): string
    {
        global $container;if(!$container)return json_encode(['processed'=>0],JSON_UNESCAPED_UNICODE)?:'{}';$repo=new TodoistMigrationRepository($container->get('db.pdo'));$job=$repo->claimNextJob();if($job===null)return json_encode(['processed'=>0],JSON_UNESCAPED_UNICODE)?:'{}';$lease=(string)($job['lease_token']??'');$client=new TodoistClient($repo);$service=new TodoistImportService($repo,$client,new TodoistCrawler($client,$repo),new TodoistTargetWriter($container,$repo,$client));try{$service->processJob((string)$job['public_id'],$lease);$repo->releaseLease((string)$job['public_id'],$lease);return json_encode(['processed'=>1,'job'=>$job['public_id']],JSON_UNESCAPED_UNICODE)?:'{}';}catch(\Throwable){$repo->addLog((int)$job['id'],'error','worker','Todoist worker failed.');if($lease===''||$repo->ownsLease((string)$job['public_id'],$lease)){try{$repo->updateJobStatus((string)$job['public_id'],'failed',$lease!==''?$lease:null);}catch(\Throwable){}$repo->releaseLease((string)$job['public_id'],$lease);}return json_encode(['processed'=>1,'failed'=>1],JSON_UNESCAPED_UNICODE)?:'{}';}
    }
}
