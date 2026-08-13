<?php
declare(strict_types=1);

namespace Module\Crm\Bitrix24Migration\Cron;

use Module\Crm\Bitrix24Migration\Repository\Bitrix24MigrationRepository;
use Module\Crm\Bitrix24Migration\Service\Bitrix24Client;
use Module\Crm\Bitrix24Migration\Service\Bitrix24Crawler;
use Module\Crm\Bitrix24Migration\Service\Bitrix24ImportService;
use Module\Crm\Bitrix24Migration\Service\Bitrix24TargetWriter;

final class Bitrix24WorkerHandler
{
    public static function run(): string
    {
        global $container;
        if (!$container) return json_encode(['processed'=>0],JSON_UNESCAPED_UNICODE)?:'{}';
        $repo=new Bitrix24MigrationRepository($container->get('db.pdo'));$job=$repo->claimNextJob();if($job===null)return json_encode(['processed'=>0],JSON_UNESCAPED_UNICODE)?:'{}';$lease=(string)($job['lease_token']??'');
        try{$connection=$repo->getConnectionById((int)$job['connection_id']);if(!$connection)throw new \RuntimeException('BITRIX24_CONNECTION_NOT_FOUND');$client=new Bitrix24Client($repo);$client->setConnection($connection);$crawler=new Bitrix24Crawler($client,$repo);$writer=new Bitrix24TargetWriter($container,$repo,$client);$service=new Bitrix24ImportService($repo,$crawler,$writer);$service->processJob((string)$job['public_id'],$lease);$repo->releaseLease((string)$job['public_id'],$lease);return json_encode(['processed'=>1,'job'=>$job['public_id']],JSON_UNESCAPED_UNICODE)?:'{}';}catch(\Throwable){$repo->addLog((int)$job['id'],'error','worker','Bitrix24 worker failed.');try{if($lease===''||$repo->ownsLease((string)$job['public_id'],$lease))$repo->updateJobStatus((string)$job['public_id'],'failed',$lease!==''?$lease:null);}catch(\Throwable){}$repo->releaseLease((string)$job['public_id'],$lease);return json_encode(['processed'=>1,'failed'=>1],JSON_UNESCAPED_UNICODE)?:'{}';}
    }
}
