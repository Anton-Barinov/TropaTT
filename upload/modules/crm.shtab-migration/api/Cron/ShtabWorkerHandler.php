<?php
declare(strict_types=1);

namespace Module\Crm\ShtabMigration\Cron;

use Module\Crm\ShtabMigration\Repository\ShtabMigrationRepository;
use Module\Crm\ShtabMigration\Service\ShtabExportCrawler;
use Module\Crm\ShtabMigration\Service\ShtabExportParser;
use Module\Crm\ShtabMigration\Service\ShtabImportService;
use Module\Crm\ShtabMigration\Service\ShtabTargetWriter;

final class ShtabWorkerHandler
{
    public static function run(): string
    {
        global $container;
        if (!$container) return json_encode(['processed'=>0], JSON_UNESCAPED_UNICODE) ?: '{}';
        $repo=new ShtabMigrationRepository($container->get('db.pdo'));$job=$repo->claimNextJob();
        if($job===null)return json_encode(['processed'=>0],JSON_UNESCAPED_UNICODE)?:'{}';
        $lease=(string)($job['lease_token']??'');$service=new ShtabImportService($repo,new ShtabExportCrawler(new ShtabExportParser(),$repo),new ShtabTargetWriter($container,$repo));
        try{$service->processJob((string)$job['public_id'],$lease);$repo->releaseLease((string)$job['public_id'],$lease);return json_encode(['processed'=>1,'job'=>$job['public_id']],JSON_UNESCAPED_UNICODE)?:'{}';}
        catch(\Throwable){$repo->addLog((int)$job['id'],'error','worker','Shtab migration worker failed.');try{$repo->updateJobStatus((string)$job['public_id'],'failed',$lease!==''?$lease:null);}catch(\Throwable){}$repo->releaseLease((string)$job['public_id'],$lease);return json_encode(['processed'=>1,'failed'=>1],JSON_UNESCAPED_UNICODE)?:'{}';}
    }
}
