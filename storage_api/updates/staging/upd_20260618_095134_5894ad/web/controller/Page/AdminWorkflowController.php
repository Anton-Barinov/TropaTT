<?php
declare(strict_types=1);

namespace Web\Controller\Page;

use Web\System\Core\Controller;

final class AdminWorkflowController extends Controller
{
    public function index(): void
    {
        $this->render('page/admin_workflow', [
            'title' => 'Админ: Workflow Rules',
            'route' => 'admin-workflow',
        ]);
    }
}
