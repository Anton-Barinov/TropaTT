<?php
declare(strict_types=1);

use Module\Crm\OutlookCalendar\Web\Controller\OutlookCalendarWebController;

return [
    'module-outlook-calendar' => [OutlookCalendarWebController::class, 'index'],
];
