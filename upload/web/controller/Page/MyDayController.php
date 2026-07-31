<?php
declare(strict_types=1);

namespace Web\Controller\Page;

use Web\System\Core\Controller;

final class MyDayController extends Controller
{
    public function index(): void
    {
        $this->render('page/my_day', [
            'title' => 'Мой день',
            'route' => 'my-day',
        ]);
    }
}
