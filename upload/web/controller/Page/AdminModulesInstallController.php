<?php
declare(strict_types=1);

namespace Web\Controller\Page;

use Web\System\Core\Controller;

final class AdminModulesInstallController extends Controller
{
    public function index(): void
    {
        $this->render('page/admin_modules_install', [
            'title' => 'Установка модуля',
            'route' => 'admin-modules-install',
        ]);
    }
}
