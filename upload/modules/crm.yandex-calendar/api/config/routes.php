<?php
declare(strict_types=1);

use Module\Crm\YandexCalendar\Controller\YandexCalendarController;

return [
    ['methods' => ['POST'], 'route' => '/connections', 'controller' => YandexCalendarController::class, 'action' => 'connect', 'auth' => true, 'required_permissions' => ['module.yandex-calendar.manage']],
    ['methods' => ['GET'], 'route' => '/connections', 'controller' => YandexCalendarController::class, 'action' => 'connections', 'auth' => true, 'required_permissions' => ['module.yandex-calendar.view']],
    ['methods' => ['DELETE'], 'route' => '/connections/{public_id}', 'controller' => YandexCalendarController::class, 'action' => 'disconnect', 'auth' => true, 'required_permissions' => ['module.yandex-calendar.manage']],
    ['methods' => ['POST'], 'route' => '/connections/{public_id}/test', 'controller' => YandexCalendarController::class, 'action' => 'test', 'auth' => true, 'required_permissions' => ['module.yandex-calendar.manage']],
    ['methods' => ['POST'], 'route' => '/connections/{public_id}/sync', 'controller' => YandexCalendarController::class, 'action' => 'sync', 'auth' => true, 'required_permissions' => ['module.yandex-calendar.sync']],
    ['methods' => ['PATCH'], 'route' => '/calendars/{public_id}', 'controller' => YandexCalendarController::class, 'action' => 'updateCalendar', 'auth' => true, 'required_permissions' => ['module.yandex-calendar.manage']],
];
