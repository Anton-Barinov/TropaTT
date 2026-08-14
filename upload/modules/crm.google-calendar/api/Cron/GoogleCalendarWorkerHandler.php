<?php
declare(strict_types=1);

namespace Module\Crm\GoogleCalendar\Cron;

use Module\Crm\GoogleCalendar\Repository\GoogleCalendarRepository;
use Module\Crm\GoogleCalendar\Service\GoogleCalendarClient;
use Module\Crm\GoogleCalendar\Service\GoogleCalendarSyncService;

final class GoogleCalendarWorkerHandler
{
    public static function run(): string
    {
        global $container;
        if (!$container) return json_encode(['processed'=>0]) ?: '{}';
        $repo=new GoogleCalendarRepository($container->get('db.pdo'));$client=new GoogleCalendarClient($repo);$service=new GoogleCalendarSyncService($repo,$client,$container->get('db.pdo'));$processed=0;$failed=0;
        foreach($repo->activeConnections() as $connection){try{$service->sync((int)$connection['id'],(int)$connection['user_id']);$processed++;}catch(\Throwable){$failed++;}}
        return json_encode(['processed'=>$processed,'failed'=>$failed],JSON_UNESCAPED_UNICODE) ?: '{}';
    }
}
