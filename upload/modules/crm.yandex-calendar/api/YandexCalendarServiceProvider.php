<?php
declare(strict_types=1);

namespace Module\Crm\YandexCalendar;

use Api\System\Library\Container;
use Api\System\Library\Module\AbstractModuleServiceProvider;
use Api\System\Library\Module\ScheduledTask;
use Module\Crm\YandexCalendar\Cron\YandexCalendarWorkerHandler;
use Module\Crm\YandexCalendar\Repository\YandexCalendarRepository;
use Module\Crm\YandexCalendar\Service\YandexCalDavClient;
use Module\Crm\YandexCalendar\Service\YandexCalendarSyncService;

final class YandexCalendarServiceProvider extends AbstractModuleServiceProvider
{
    public function register(Container $container): void
    {
        $container->factory('module.yandex_calendar.repository', static fn(Container $c): YandexCalendarRepository => new YandexCalendarRepository($c->get('db.pdo')));
        $container->factory('module.yandex_calendar.client', static fn(Container $c): YandexCalDavClient => new YandexCalDavClient(self::moduleConfig($c)));
        $container->factory('module.yandex_calendar.sync', static fn(Container $c): YandexCalendarSyncService => new YandexCalendarSyncService($c->get('module.yandex_calendar.repository'), $c->get('module.yandex_calendar.client'), $c->get('db.pdo'), self::moduleConfig($c)));
    }

    /** @return array<string,mixed> */
    private static function moduleConfig(Container $container): array
    {
        if (!$container->has('module.config')) return [];
        $config = $container->get('module.config')->getAll('crm.yandex-calendar');
        return is_array($config) ? $config : [];
    }

    public function getPermissions(): array { return ['module.yandex-calendar.view','module.yandex-calendar.manage','module.yandex-calendar.sync']; }
    public function getMenuItems(): array { return [['route'=>'module-yandex-calendar','label'=>'Яндекс.Календарь','icon'=>'<i class="fa-solid fa-calendar-days"></i>','permission'=>'module.yandex-calendar.view','parent'=>null]]; }
    public function getScheduledTasks(): array { return [new ScheduledTask(name:'sync_yandex_calendars',description:'Synchronize private Yandex Calendar CalDAV connections',schedule:'*/15 * * * *',handler:[YandexCalendarWorkerHandler::class,'run'],enabled:true,timeout:240,overlapAllowed:false,notifyOnError:true)]; }
    public function getAssets(): array { return ['css'=>['web/assets/css/yandex-calendar.css']]; }
    public function getConfig(): array { return ['request_timeout_seconds'=>30,'max_retries'=>4,'sync_interval_minutes'=>15,'sync_days_past'=>90,'sync_days_future'=>365,'max_events_per_sync'=>5000]; }
}
