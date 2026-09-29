<?php
declare(strict_types=1);

namespace Web\Controller\Page;

use Web\System\Core\Controller;

final class AdminLanguagesController extends Controller
{
    public function index(): void
    {
        $this->render('page/admin_languages', [
            'title' => 'Языки и локализация',
            'route' => 'admin-languages',
        ]);
    }
}
