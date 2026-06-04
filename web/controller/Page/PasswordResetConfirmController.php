<?php
declare(strict_types=1);

namespace Web\Controller\Page;

use Web\System\Core\Controller;

final class PasswordResetConfirmController extends Controller
{
    public function index(): void
    {
        $this->render('page/password_reset_confirm', [
            'title' => 'Подтверждение сброса пароля',
            'route' => 'password-reset-confirm',
        ]);
    }
}
