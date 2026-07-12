<?php
declare(strict_types=1);

namespace Web\Controller\Page;

use Web\System\Core\Controller;

final class TimeAnalyticsController extends Controller
{
    public function index(): void
    {
        $this->render('page/time_analytics', [
            'title' => 'Учет времени — аналитика',
            'route' => 'time-analytics',
        ]);
    }
}
