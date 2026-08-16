<?php
declare(strict_types=1);

namespace Web\Controller\Page;

use Web\System\Core\Controller;

final class CyclesController extends Controller
{
    public function index(): void
    {
        $projectPublicId = (string)($_GET['project_public_id'] ?? '');

        $this->render('page/cycles', [
            'title' => 'Циклы (Спринты)',
            'route' => 'cycles',
            'project_public_id' => $projectPublicId,
        ]);
    }
}
