<?php
declare(strict_types=1);

namespace Web\Controller\Page;

use Web\System\Core\Controller;

final class IntakeController extends Controller
{
    public function index(): void
    {
        $this->render('intake/index', [
            'title' => 'Входящие',
            'route' => 'intake',
        ]);
    }
}
