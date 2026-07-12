<?php
declare(strict_types=1);

namespace Web\Controller\Page;

use Web\System\Core\Controller;

final class KnowledgePageController extends Controller
{
    public function index(): void
    {
        $this->render('page/knowledge_page', [
            'title' => 'Страница базы знаний',
            'route' => 'knowledge-page',
        ]);
    }
}
