<?php
declare(strict_types=1);

namespace Module\Crm\OutlookCalendar\Web\Controller;

use Web\System\Core\Controller;

final class OutlookCalendarWebController extends Controller
{
    public function index(): void
    {
        $this->render(__DIR__ . '/../template/page/crm_outlook_calendar.php', [
            'title' => 'Outlook Календарь',
            'route' => 'module-outlook-calendar',
        ]);
    }
}
