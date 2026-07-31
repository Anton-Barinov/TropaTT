<?php
declare(strict_types=1);

namespace Web\Controller\Page;

use Web\System\Core\Controller;

final class RecycleBinController extends Controller
{
    public function index(): void
    {
        $this->render('page/recycle_bin', [
            'title' => 'Корзина',
            'route' => 'recycle-bin',
        ]);
    }
}
