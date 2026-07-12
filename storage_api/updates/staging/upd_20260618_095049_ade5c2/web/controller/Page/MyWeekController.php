<?php
declare(strict_types=1);

namespace Web\Controller\Page;

use Web\System\Core\Controller;

final class MyWeekController extends Controller
{
    public function index(): void
    {
        $this->render('page/my_week', [
            'title' => 'Моя неделя',
            'route' => 'my-week',
        ]);
    }
}
