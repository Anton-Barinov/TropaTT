<?php
declare(strict_types=1);

namespace Web\Controller\Page;

use Web\System\Core\Controller;

final class CalendarController extends Controller
{
    public function index(): void
    {
        $this->render('page/calendar', [
            'title' => 'Календарь',
            'route' => 'calendar',
        ]);
    }
}
