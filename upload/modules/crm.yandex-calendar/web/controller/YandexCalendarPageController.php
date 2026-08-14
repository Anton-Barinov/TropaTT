<?php
declare(strict_types=1);

namespace Module\Crm\YandexCalendar\Controller;

use Web\System\Core\Controller;

final class YandexCalendarPageController extends Controller
{
    public function index(): void
    {
        $this->render(__DIR__ . '/../template/page/yandex_calendar.php', ['title' => 'Яндекс.Календарь', 'route' => 'module-yandex-calendar']);
    }
}
