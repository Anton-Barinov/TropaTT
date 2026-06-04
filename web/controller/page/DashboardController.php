<?php
declare(strict_types=1);

namespace Web\Controller\Page;

use Web\System\Core\Controller;

final class DashboardController extends Controller
{
    public function index(): void
    {
        $this->render('page/dashboard', [
            'title' => 'Дашборд',
            'route' => 'dashboard',
        ]);
    }
}
