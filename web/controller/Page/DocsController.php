<?php
declare(strict_types=1);

namespace Web\Controller\Page;

use Web\System\Core\Controller;

final class DocsController extends Controller
{
    public function index(): void
    {
        $this->render('page/docs', [
            'title' => 'Документация',
            'route' => 'docs',
        ]);
    }
}
