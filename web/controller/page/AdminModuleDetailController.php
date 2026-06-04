<?php
declare(strict_types=1);

namespace Web\Controller\Page;

use Web\System\Core\Controller;

final class AdminModuleDetailController extends Controller
{
    public function index(): void
    {
        $this->render('page/admin_module_detail', [
            'title' => 'Детали модуля',
            'route' => 'admin-module-detail',
        ]);
    }
}
