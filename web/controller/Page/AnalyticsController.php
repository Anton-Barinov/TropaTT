<?php
declare(strict_types=1);

namespace Web\Controller\Page;

use Web\System\Core\Controller;

final class AnalyticsController extends Controller
{
    public function index(): void
    {
        $this->render('page/analytics', [
            'title' => 'Аналитика',
            'route' => 'analytics',
        ]);
    }
}
