<?php
declare(strict_types=1);

namespace Web\Controller\Page;

use Web\System\Core\Controller;
use Web\System\I18n\I18n;

final class LoginController extends Controller
{
    public function index(): void
    {
        $this->render('page/login', [
            'title' => 'Вход',
            'route' => 'login',
            'available_locales' => I18n::getEnabledLocales($this->baseDir),
        ]);
    }
}
