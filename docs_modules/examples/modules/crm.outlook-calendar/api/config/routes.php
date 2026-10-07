<?php
declare(strict_types=1);

use Module\Crm\OutlookCalendar\Controller\OutlookCalendarApiController;

return [
    ['methods' => ['GET'], 'route' => '/status', 'controller' => OutlookCalendarApiController::class, 'action' => 'getStatus', 'auth' => true],
];
