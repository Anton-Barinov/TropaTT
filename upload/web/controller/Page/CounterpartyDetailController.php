<?php
declare(strict_types=1);

namespace Web\Controller\Page;

use Web\System\Core\Controller;

final class CounterpartyDetailController extends Controller
{
    public function index(): void
    {
        $this->render('page/counterparty_detail', [
            'title' => 'Карточка контрагента',
            'route' => 'counterparty-detail',
        ]);
    }
}
