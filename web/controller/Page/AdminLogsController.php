<?php
declare(strict_types=1);

namespace Web\Controller\Page;

use Web\System\Core\Controller;

final class AdminLogsController extends Controller
{
    public function index(): void
    {
        $this->render('page/admin_logs', [
            'title' => 'Админ: Логи',
            'route' => 'admin-logs',
        ]);
    }
}
