<?php
declare(strict_types=1);

namespace Web\Controller\Page;

use Web\System\Core\Controller;

final class AdminSlaController extends Controller
{
    public function index(): void
    {
        $this->render('page/admin_sla', [
            'title' => 'Админ: SLA Policies',
            'route' => 'admin-sla',
        ]);
    }
}
