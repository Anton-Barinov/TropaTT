<?php
declare(strict_types=1);

namespace Web\Controller\Page;

use Web\System\Core\Controller;

final class AdminEstimatesController extends Controller
{
    public function index(): void
    {
        $this->render('page/admin_estimates', [
            'title' => 'Админ: Наборы оценок',
            'route' => 'admin-estimates',
        ]);
    }
}
