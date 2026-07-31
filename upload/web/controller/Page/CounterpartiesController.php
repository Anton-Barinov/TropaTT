<?php
declare(strict_types=1);

namespace Web\Controller\Page;

use Web\System\Core\Controller;

final class CounterpartiesController extends Controller
{
    public function index(): void
    {
        $this->render('page/counterparties', [
            'title' => 'Контрагенты',
            'route' => 'counterparties',
        ]);
    }
}
