<?php
declare(strict_types=1);

namespace Web\Controller\Page;

use Web\System\Core\Controller;

final class AdminKnowledgeController extends Controller
{
    public function index(): void
    {
        $this->render('page/admin_knowledge', [
            'title' => 'Настройки базы знаний',
            'route' => 'admin-knowledge',
        ]);
    }
}
