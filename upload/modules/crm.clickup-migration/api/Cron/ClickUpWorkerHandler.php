<?php
declare(strict_types=1);

namespace Module\Crm\ClickUpMigration\Cron;

use Module\Crm\ClickUpMigration\Repository\ClickUpMigrationRepository;
use Module\Crm\ClickUpMigration\Service\ClickUpClient;
use Module\Crm\ClickUpMigration\Service\ClickUpCrawler;
use Module\Crm\ClickUpMigration\Service\ClickUpImportService;
use Module\Crm\ClickUpMigration\Service\ClickUpTargetWriter;

final class ClickUpWorkerHandler
{
    public static function run(): string
    {
        global $container;if(!$container)return json_encode(['processed'=>0],JSON_UNESCAPED_UNICODE)?:'{}';$repo=new ClickUpMigrationRepository($container->get('db.pdo'));$job=$repo->claimNextJob();if($job===null)return json_encode(['processed'=>0],JSON_UNESCAPED_UNICODE)?:'{}';$lease=(string)($job['lease_token']??'');$client=new ClickUpClient($repo);$service=new ClickUpImportService($repo,$client,new ClickUpCrawler($client,$repo),new ClickUpTargetWriter($container,$repo,$client));try{$service->processJob((string)$job['public_id'],$lease);$repo->releaseLease((string)$job['public_id'],$lease);return json_encode(['processed'=>1,'job'=>$job['public_id']],JSON_UNESCAPED_UNICODE)?:'{}';}catch(\Throwable){$repo->addLog((int)$job['id'],'error','worker','ClickUp worker failed.');if($lease===''||$repo->ownsLease((string)$job['public_id'],$lease)){try{$repo->updateJobStatus((string)$job['public_id'],'failed',$lease!==''?$lease:null);}catch(\Throwable){}$repo->releaseLease((string)$job['public_id'],$lease);}return json_encode(['processed'=>1,'failed'=>1],JSON_UNESCAPED_UNICODE)?:'{}';}
    }
}
