<?php
declare(strict_types=1);

namespace Web\Controller\Page;

use Web\System\Core\Controller;

final class AppController extends Controller
{
    public function index(): void
    {
        $this->render('page/app', [
            'title' => 'Приложение',
            'route' => 'app',
        ]);
    }
}
