<?php
declare(strict_types=1);

namespace Web\Controller\Page;

use Web\System\Core\Controller;

final class AdminJobsController extends Controller
{
    public function index(): void
    {
        $this->render('page/admin_jobs', [
            'title' => 'Админ: Задания импорта, экспорта и AI',
            'route' => 'admin-jobs',
        ]);
    }
}
