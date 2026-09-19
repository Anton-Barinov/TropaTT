<?php
declare(strict_types=1);

namespace Web\Controller\Page;

use Web\System\Core\Controller;

final class AdminController extends Controller
{
    public function index(): void
    {
        $this->render('page/admin', [
            'title' => 'Администрирование',
            'route' => 'admin',
        ]);
    }
}
