<?php
declare(strict_types=1);

namespace Web\Controller\Page;

use Web\System\Core\Controller;

final class AdminUsersController extends Controller
{
    public function index(): void
    {
        $this->render('page/admin_users', [
            'title' => 'Админ: Пользователи',
            'route' => 'admin-users',
        ]);
    }
}
