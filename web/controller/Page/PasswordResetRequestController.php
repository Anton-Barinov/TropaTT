<?php
declare(strict_types=1);

namespace Web\Controller\Page;

use Web\System\Core\Controller;

final class PasswordResetRequestController extends Controller
{
    public function index(): void
    {
        $this->render('page/password_reset_request', [
            'title' => 'Восстановление пароля',
            'route' => 'password-reset-request',
        ]);
    }
}
