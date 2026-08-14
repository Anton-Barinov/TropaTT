<?php
declare(strict_types=1);

namespace Module\Crm\KaitenMigration\Cron;

use Module\Crm\KaitenMigration\Repository\KaitenMigrationRepository;
use Module\Crm\KaitenMigration\Service\KaitenClient;
use Module\Crm\KaitenMigration\Service\KaitenCrawler;
use Module\Crm\KaitenMigration\Service\KaitenImportService;
use Module\Crm\KaitenMigration\Service\KaitenTargetWriter;

final class KaitenWorkerHandler
{
    public static function run(): string
    {
        global $container;
        if (!$container) return json_encode(['processed'=>0],JSON_UNESCAPED_UNICODE)?:'{}';
        $repo=new KaitenMigrationRepository($container->get('db.pdo'));$job=$repo->claimNextJob();
        if($job===null)return json_encode(['processed'=>0],JSON_UNESCAPED_UNICODE)?:'{}';
        $lease=(string)($job['lease_token']??'');$client=new KaitenClient($repo);$crawler=new KaitenCrawler($client,$repo);$writer=new KaitenTargetWriter($container,$repo,$client);$service=new KaitenImportService($repo,$client,$crawler,$writer);
        try{$service->processJob((string)$job['public_id'],$lease);$repo->releaseLease((string)$job['public_id'],$lease);return json_encode(['processed'=>1,'job'=>$job['public_id']],JSON_UNESCAPED_UNICODE)?:'{}';}catch(\Throwable){$repo->addLog((int)$job['id'],'error','worker','Kaiten worker failed.');if($lease===''||$repo->ownsLease((string)$job['public_id'],$lease)){try{$repo->updateJobStatus((string)$job['public_id'],'failed',$lease);}catch(\Throwable){}$repo->releaseLease((string)$job['public_id'],$lease);}return json_encode(['processed'=>1,'failed'=>1],JSON_UNESCAPED_UNICODE)?:'{}';}
    }
}
