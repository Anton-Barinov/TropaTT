<?php
declare(strict_types=1);

namespace Module\Crm\GoogleCalendar\Controller;

use Web\System\Core\Controller;

final class GoogleCalendarPageController extends Controller
{
    public function index(): void
    {
        $this->render(__DIR__ . '/../template/page/google_calendar.php', ['title' => 'Google Calendar', 'route' => 'module-google-calendar']);
    }
}
