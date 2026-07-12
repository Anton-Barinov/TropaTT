<?php
declare(strict_types=1);

namespace Web\Controller\Page;

use Web\System\Core\Controller;

final class AdminAiController extends Controller
{
    public function index(): void
    {
        $this->render('page/admin_ai', [
            'title' => 'Админ: AI-провайдеры',
            'route' => 'admin-ai',
        ]);
    }
}
