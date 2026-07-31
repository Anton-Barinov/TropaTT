<?php
declare(strict_types=1);

namespace Web\Controller\Page;

use Web\System\Core\Controller;

final class AdminModulesController extends Controller
{
    public function index(): void
    {
        $this->render('page/admin_modules', [
            'title' => 'Модули',
            'route' => 'admin-modules',
        ]);
    }
}
