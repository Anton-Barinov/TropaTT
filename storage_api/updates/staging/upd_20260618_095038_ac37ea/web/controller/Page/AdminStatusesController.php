<?php
declare(strict_types=1);

namespace Web\Controller\Page;

use Web\System\Core\Controller;

final class AdminStatusesController extends Controller
{
    public function index(): void
    {
        $this->render('page/admin_statuses', [
            'title' => 'Админ: Статусы',
            'route' => 'admin-statuses',
        ]);
    }
}
