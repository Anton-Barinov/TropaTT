<?php
declare(strict_types=1);

namespace Web\Controller\Page;

use Web\System\Core\Controller;

final class ApprovalsController extends Controller
{
    public function index(): void
    {
        $this->render('page/approvals', [
            'title' => 'Админ: Согласования',
            'route' => 'approvals',
        ]);
    }
}
