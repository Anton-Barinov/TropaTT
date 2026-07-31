<?php
declare(strict_types=1);

namespace Web\Controller\Page;

use Web\System\Core\Controller;

final class GlobalSearchController extends Controller
{
    public function index(): void
    {
        $this->render('page/global_search', [
            'title' => 'Поиск по TropaTT',
            'route' => 'global-search',
        ]);
    }
}
