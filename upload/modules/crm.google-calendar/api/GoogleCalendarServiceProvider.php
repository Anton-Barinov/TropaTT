<?php
declare(strict_types=1);

namespace Module\Crm\GoogleCalendar;

use Api\System\Library\Container;
use Api\System\Library\Module\AbstractModuleServiceProvider;
use Api\System\Library\Module\ScheduledTask;
use Module\Crm\GoogleCalendar\Cron\GoogleCalendarWorkerHandler;
use Module\Crm\GoogleCalendar\Repository\GoogleCalendarRepository;
use Module\Crm\GoogleCalendar\Service\GoogleCalendarClient;
use Module\Crm\GoogleCalendar\Service\GoogleCalendarSyncService;

final class GoogleCalendarServiceProvider extends AbstractModuleServiceProvider
{
    public function register(Container $container): void
    {
        $container->factory('module.google_calendar.repository', static fn(Container $c): GoogleCalendarRepository => new GoogleCalendarRepository($c->get('db.pdo')));
        $container->factory('module.google_calendar.client', static fn(Container $c): GoogleCalendarClient => new GoogleCalendarClient($c->get('module.google_calendar.repository')));
        $container->factory('module.google_calendar.sync', static fn(Container $c): GoogleCalendarSyncService => new GoogleCalendarSyncService($c->get('module.google_calendar.repository'), $c->get('module.google_calendar.client'), $c->get('db.pdo')));
    }

    public function getPermissions(): array
    {
        return ['module.google-calendar.view', 'module.google-calendar.manage', 'module.google-calendar.sync'];
    }

    public function getMenuItems(): array
    {
        return [['route' => 'module-google-calendar', 'label' => 'Google Calendar', 'icon' => '<i class="fa-solid fa-calendar-days"></i>', 'permission' => 'module.google-calendar.view', 'parent' => null]];
    }

    public function getScheduledTasks(): array
    {
        return [new ScheduledTask(
            name: 'sync_google_calendars',
            description: 'Synchronize private Google Calendar connections',
            schedule: '*/15 * * * *',
            handler: [GoogleCalendarWorkerHandler::class, 'run'],
            enabled: true,
            timeout: 240,
            overlapAllowed: false,
            notifyOnError: true
        )];
    }

    public function getAssets(): array
    {
        return ['js' => ['web/assets/js/google-calendar.js'], 'css' => ['web/assets/css/google-calendar.css']];
    }

    public function getConfig(): array
    {
        return ['request_timeout_seconds' => 30, 'max_retries' => 5, 'sync_interval_minutes' => 15, 'max_events_per_page' => 2500, 'enable_push' => false];
    }
}
