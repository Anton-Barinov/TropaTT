<?php
declare(strict_types=1);

namespace Web\Controller\Page;

use Web\System\Core\Controller;

final class LoginController extends Controller
{
    public function index(): void
    {
        $this->render('page/login', [
            'title' => 'Вход',
            'route' => 'login',
        ]);
    }
}
