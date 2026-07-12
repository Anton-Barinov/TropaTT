<?php
declare(strict_types=1);

namespace Web\Controller\Page;

use Web\System\Core\Controller;

final class AdminRolesController extends Controller
{
    public function index(): void
    {
        $this->render('page/admin_roles', [
            'title' => 'Админ: Роли',
            'route' => 'admin-roles',
        ]);
    }
}
