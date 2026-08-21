<?php
declare(strict_types=1);

namespace Web\Controller\Page;

use Web\System\Core\Controller;

final class MyEarningsController extends Controller
{
    public function index(): void
    {
        $this->render('page/my_earnings', [
            'title' => 'Моё вознаграждение',
            'route' => 'my-earnings',
        ]);
    }
}
