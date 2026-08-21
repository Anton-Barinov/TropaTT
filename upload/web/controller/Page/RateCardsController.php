<?php
declare(strict_types=1);

namespace Web\Controller\Page;

use Web\System\Core\Controller;

final class RateCardsController extends Controller
{
    public function index(): void
    {
        $this->render('page/rate_cards', [
            'title' => 'Прайс-листы',
            'route' => 'rate-cards',
        ]);
    }
}
