<?php
declare(strict_types=1);

namespace Web\Controller\Page;

use Web\System\Core\Controller;

final class KnowledgeController extends Controller
{
    public function index(): void
    {
        $this->render('page/knowledge', [
            'title' => 'База знаний',
            'route' => 'knowledge',
        ]);
    }
}
