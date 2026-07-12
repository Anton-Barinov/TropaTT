<?php
declare(strict_types=1);

namespace Web\Controller\Page;

use Web\System\Core\Controller;

final class CompaniesController extends Controller
{
    public function index(): void
    {
        $this->render('page/companies', [
            'title' => 'Компании',
            'route' => 'companies',
        ]);
    }
}

