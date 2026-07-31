<?php
declare(strict_types=1);

namespace Web\Controller\Page;

use Web\System\Core\Controller;

final class AdminCalendarController extends Controller
{
    public function index(): void
    {
        $this->render('page/admin_calendar', [
            'title' => 'Админ: Производственный календарь',
            'route' => 'admin-calendar',
        ]);
    }
}
