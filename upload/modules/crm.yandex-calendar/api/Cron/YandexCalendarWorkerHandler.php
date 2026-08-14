<?php
declare(strict_types=1);

namespace Module\Crm\YandexCalendar\Cron;

use Module\Crm\YandexCalendar\Repository\YandexCalendarRepository;
use Module\Crm\YandexCalendar\Service\YandexCalDavClient;
use Module\Crm\YandexCalendar\Service\YandexCalendarSyncService;

final class YandexCalendarWorkerHandler
{
    public static function run(): string
    {
        global $container;
        if (!$container) return json_encode(['processed'=>0,'failed'=>0]) ?: '{}';
        $repo = new YandexCalendarRepository($container->get('db.pdo'));
        $config = $container->has('module.config') ? $container->get('module.config')->getAll('crm.yandex-calendar') : [];
        $config = is_array($config) ? $config : [];
        $service = new YandexCalendarSyncService($repo, new YandexCalDavClient($config), $container->get('db.pdo'), $config);
        $processed = 0; $failed = 0;
        foreach ($repo->orphanedConnections() as $item) { try { $service->disconnect((int)$item['id'], (int)$item['user_id']); } catch (\Throwable) { $failed++; } }
        foreach ($repo->activeConnections() as $item) { try { $service->sync((int)$item['id'], (int)$item['user_id']); $processed++; } catch (\Throwable) { $failed++; } }
        return json_encode(['processed'=>$processed,'failed'=>$failed], JSON_UNESCAPED_UNICODE) ?: '{}';
    }
}
